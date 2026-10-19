<?php
namespace App\Http\Controllers\Back;

use App\Exports\CompanyAttendanceReportSendStatusExcel;
use App\Exports\CompanyAttendanceSheetExport;
use App\Http\Controllers\Controller;
use App\Mail\CompanyAttendanceIndividualReportMail;
use App\Mail\CompanyAttendanceReportMail;
use App\Models\Back\Company;
use App\Models\Back\CompanyAttendanceReport;
use App\Models\Back\CompanyAttendanceReportsEmail;
use App\Models\Back\CompanyAttendanceReportsTrainee;
use App\Services\CompanyAttendanceReportService;
use App\Services\CompanyMigrationHelper;
use Carbon\Carbon;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PDF;
use Spatie\QueryBuilder\QueryBuilder;

class CompanyAttendanceReportController extends Controller
{
    public function index()
    {
        $this->authorize('submit-company-attendance-report');

        $reports = QueryBuilder::for(CompanyAttendanceReport::class)
            ->with('company', 'created_by')
            ->withCount(['trainees' => function($q) {
                $q->where('active', true);
            }])
            ->defaultSort('-created_at')
            ->allowedFilters(['company.name_ar', 'status', 'date_from', 'number'])
            ->allowedSorts(['number'])
            ->paginate()
            ->withQueryString();

        return Inertia::render('Back/Reports/CompanyAttendance/Index', [
            'reports' => $reports,
        ])->table(function ($table) {
            $table->disableGlobalSearch();

            $table->addSearchRows([
                'number' => __('words.number'),
                'company.name_ar' => __('words.company'),
                'date_from' => __('words.date'),
            ]);

            $table->addFilter('status', __('words.status'), [
                CompanyAttendanceReport::STATUS_REVIEW => __('words.review'),
                CompanyAttendanceReport::STATUS_APPROVED => __('words.approved'),
            ]);
        });
    }

    public function create()
    {
        return Inertia::render('Back/Reports/CompanyAttendance/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        $date_from = Carbon::parse($request->period['startDate'])->setTimezone('Asia/Riyadh')->startOfDay();
        $date_to = Carbon::parse($request->period['endDate'])->setTimezone('Asia/Riyadh')->endOfDay();
        $company = Company::findOrFail($request->company_id);

        DB::beginTransaction();
        $previousReport = CompanyAttendanceReport::where('company_id', $company->id)->latest()->first();

        $report = CompanyAttendanceReport::create([
            'company_id' => $company->id,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'with_logo' => false,
            'template_type' => 'default',
        ]);

        // Get all trainees including deleted ones and those with resignations
        $allTrainees = collect();
        
        // 1. Active trainees (not deleted)
        $activeTrainees = $company->trainees()->get();
        $allTrainees = $allTrainees->merge($activeTrainees);
        
        // 2. Trainees with resignations AND deleted (soft deleted) - ONLY THESE SHOULD BE INCLUDED
        $resignationTrainees = $company->resignations()
            ->whereIn('status', ['new', 'sent']) // Include both new and sent resignations
            ->where('resignation_date', '>=', $date_from) // Resignation date should be within or after report period
            ->with(['trainees' => function($q) {
                $q->onlyTrashed(); // ONLY deleted trainees
            }])
            ->get()
            ->flatMap(function($resignation) {
                return $resignation->trainees;
            });
        
        $allTrainees = $allTrainees->merge($resignationTrainees);
        
        // Remove duplicates and attach to report
        $uniqueTraineeIds = $allTrainees->unique('id')->pluck('id');
        
        // Filter out trainees who have leave during the report period
        $traineesWithoutLeave = $uniqueTraineeIds->filter(function($traineeId) use ($date_from, $date_to) {
            $hasLeave = \App\Models\Back\TraineeLeave::where('trainee_id', $traineeId)
                ->where(function($query) use ($date_from, $date_to) {
                    // Check if leave overlaps with report period
                    $query->where(function($q) use ($date_from, $date_to) {
                        // Leave starts before or during report period and ends during or after report period
                        $q->where('from_date', '<=', $date_to)
                          ->where('to_date', '>=', $date_from);
                    });
                })
                ->whereIn('status', ['pending', 'approved']) // Only consider pending or approved leaves
                ->exists();
            
            return !$hasLeave; // Return true if trainee doesn't have leave (should be included)
        });
        
        // Get settings from previous report to preserve user preferences (if exists)
        $previousTraineesSettings = collect();
        if ($previousReport) {
            $previousTraineesSettings = CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $previousReport->id)
                ->get()
                ->keyBy('trainee_id');
                
            \Log::info('Debug - Store: Preserving settings from previous report:', [
                'previous_report_id' => $previousReport->id,
                'new_report_id' => $report->id,
                'previous_trainees_count' => $previousTraineesSettings->count(),
                'preserved_inactive_trainees' => $previousTraineesSettings->where('active', false)->count(),
            ]);
        }
        
        // Prepare trainee data with start_date and end_date for resigned trainees
        // AND preserve previous settings if they exist
        $traineeData = [];
        foreach ($traineesWithoutLeave as $traineeId) {
            $trainee = $allTrainees->firstWhere('id', $traineeId);
            
            // Check if this trainee has a resignation
            $resignation = $company->resignations()
                ->whereIn('status', ['new', 'sent'])
                ->whereHas('trainees', function($q) use ($traineeId) {
                    $q->where('trainees.id', $traineeId); // Specify table name to avoid ambiguity
                })
                ->first();
                
            // Get previous settings for this trainee if they exist
            $previousSettings = $previousTraineesSettings->get($traineeId);
            
            if ($resignation && $trainee->trashed()) {
                // This is a resigned and deleted trainee
                $traineeData[$traineeId] = [
                    'active' => $previousSettings ? $previousSettings->active : true, // Preserve previous active status
                    'status' => $previousSettings ? $previousSettings->status : null, // Preserve previous status
                    'comment' => $previousSettings ? $previousSettings->comment : null, // Preserve previous comment
                    'start_date' => $date_from, // Start from report start date
                    'end_date' => $report->boundTraineeDates(
                        $date_from,
                        Carbon::parse($resignation->resignation_date)->endOfDay()
                    )[1],
                ];
            } else {
                // This is an active trainee - preserve previous settings if exist
                $traineeData[$traineeId] = [
                    'active' => $previousSettings ? $previousSettings->active : true, // Preserve previous active status, default true for new trainees
                    'status' => $previousSettings ? $previousSettings->status : null, // Preserve previous status
                    'comment' => $previousSettings ? $previousSettings->comment : null, // Preserve previous comment
                    'start_date' => $previousSettings && $previousSettings->start_date ? $previousSettings->start_date : null, // Preserve custom dates if exist
                    'end_date' => $previousSettings && $previousSettings->end_date ? $previousSettings->end_date : null,
                ];
            }
        }
        
        $report->trainees()->attach($traineeData);

        // If there is an old report, use the previous emails. Otherwise, copy the company's emails.
        if ($previousReport) {
            $report->emails()->createMany($previousReport->emails()->get()->map(function ($email) {
                return [
                    'type' => $email->type,
                    'email' => $email->email,
                ];
            })->toArray());
        } else {
            if ($company->email) {
                $companyEmails = preg_split('/[,;\n]+/', $company->email) ?: [];
                foreach ($companyEmails as $email) {
                    $email = strtolower(trim((string) $email));
                    if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }
                    $report->emails()->create(['type' => 'to', 'email' => $email]);
                }
            }
            $report->emails()->create(['type' => 'cc', 'email' => auth()->user()->email]);
        }

        DB::commit();

        return redirect()->route('back.reports.company-attendance.show', $report->id);
    }

    /**
     * @param $id
     * @return \Inertia\Response
     */
    public function edit($id)
    {
        $report = CompanyAttendanceReport::with('company')->findOrFail($id);

        $report->status = 1;
        $report->approved_at = null;
        $report->approved_by_id = null;
        $report->save();

        return Inertia::render('Back/Reports/CompanyAttendance/Edit', [
            'report' => $report,
        ]);
    }

    public function show($id)
    {
        $report = CompanyAttendanceReport::with('company')
            ->with('approved_by')
            ->with('trainees')
            ->with('emails_to')
            ->with('emails_cc')
            ->with('emails_bcc')
            ->findOrFail($id);

        return Inertia::render('Back/Reports/CompanyAttendance/Show',[
            'report' => $report,
        ]);
    }

    public function destroy($id)
    {
        $r = CompanyAttendanceReport::findOrFail($id);
        abort_if($r->status === CompanyAttendanceReport::STATUS_APPROVED, 404);

        $r->delete();
        return redirect()->route('back.reports.company-attendance.index');
    }

    public function attach($id, Request $request)
    {
        $request->validate(['trainee_id' => 'required|exists:trainees,id']);
        $record = CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $id)
            ->where('trainee_id', $request->trainee_id)
            ->first();
        $record->active = true;
        $record->save();
        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    public function detach($id, Request $request)
    {
        $request->validate(['trainee_id' => 'required|exists:trainees,id']);
        $record = CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $id)
            ->where('trainee_id', $request->trainee_id)
            ->first();
        
        if ($record) {
            $record->active = false;
            $record->save();
        }
        
        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    public function update($id, Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'period' => 'nullable',
            'with_attendance_times' => 'nullable|boolean',
            'with_logo' => 'nullable|boolean',
            'template_type' => 'nullable|in:default,simple,modern,gradient,classic,royal',
        ]);

        $report = CompanyAttendanceReport::findOrFail($id);

        if ($request->has('period')) {
            $date_from = Carbon::parse($request->period['startDate'])->setTimezone('Asia/Riyadh')->startOfDay();
            $date_to = Carbon::parse($request->period['endDate'])->setTimezone('Asia/Riyadh')->endOfDay();
            $company = Company::findOrFail($request->company_id);
            $report->update([
                'company_id' => $company->id,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'with_attendance_times' => $request->with_attendance_times,
                'with_logo' => $request->with_logo,
                'template_type' => $request->template_type ?? 'default',
            ]);
            $report->save();
        } else {
            $report->update($request->except('_token'));
        }

        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    public function preview($id)
    {
        $pdf = CompanyAttendanceReportService::makePdf($id);

        // Debug: Log the wkhtmltopdf binary path being used
        \Log::info('WKHTMLTOPDF Binary Path Debug (CompanyAttendance)', [
            'config_value' => config('snappy.pdf.binary'),
            'env_value' => env('WKHTML_PDF_BINARY'),
            'default_path' => base_path('vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64'),
        ]);

        return $pdf->inline();
    }

    public function approve($id)
    {
        app()->make(CompanyAttendanceReportService::class)->approve($id);

        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    public function send($id)
    {
        $report = CompanyAttendanceReport::findOrFail($id);

        // make sure to remove any spaces from emails
        foreach ($report->emails as $email) {
            $email->update([
                'email' => Str::replace(' ', '', $email->email),
            ]);
        }

        // معالجة TO emails
        $toEmails = $report->emails_to()->pluck('email')->filter(function($email) {
            return !empty(trim($email)) && filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        })->map(function($email) {
            return trim($email);
        })->toArray();

        // معالجة CC emails (BCC في الواقع)
        $bccEmails = $report->emails_cc()->pluck('email')->filter(function($email) {
            return !empty(trim($email)) && filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        })->map(function($email) {
            return trim($email);
        })->toArray();

        // التأكد من وجود مستلمين
        $totalRecipients = count($toEmails) + count($bccEmails);
        
        if ($totalRecipients === 0) {
            \Log::error('No recipients found for attendance report email', [
                'report_id' => $id,
                'to_emails' => $toEmails,
                'bcc_emails' => $bccEmails
            ]);
            throw new \Exception('لا توجد عناوين بريد إلكتروني صحيحة للإرسال');
        }

        // إنشاء instance الإيميل
        $mailInstance = null;
        
        if (!empty($toEmails)) {
            $mailInstance = Mail::to($toEmails);
        } else if (!empty($bccEmails)) {
            // إذا لم تكن هناك TO emails، استخدم أول BCC كـ TO
            $mailInstance = Mail::to($bccEmails[0]);
            array_shift($bccEmails);
        }
        
        // إضافة BCC emails - استخدام مصفوفة واحدة بدلاً من loop
        if (!empty($bccEmails)) {
            $mailInstance->bcc($bccEmails);
        }

        // تسجيل تفاصيل الإرسال
        \Log::info('Sending attendance report email', [
            'report_id' => $id,
            'to_count' => count($toEmails),
            'bcc_count' => count($bccEmails),
            'to_emails' => $toEmails,
            'bcc_emails' => $bccEmails
        ]);

        $mailInstance->send(new CompanyAttendanceReportMail($report->id));



        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    public function clone($id)
    {
        $clone = app()->make(CompanyAttendanceReportService::class)->clone($id);

        return redirect()->route('back.reports.company-attendance.show', $clone->id);
    }

    public function sendReport()
    {
        return Inertia::render('Back/Reports/CompanyAttendance/SendReport');
    }

    public function sendReportDownload(Request $request)
    {
        $request->validate([
            'date' => 'required',
        ]);

        $start = Carbon::parse($request->date, 'Asia/Riyadh')->startOfMonth()->startOfDay();
        $end = Carbon::parse($request->date, 'Asia/Riyadh')->endOfMonth()->endOfDay();

        $companies = Company::with(['company_attendance_reports' => function($q) use ($start, $end) {
            $q->whereBetween('date_to', [$start, $end])
                ->where('status', CompanyAttendanceReport::STATUS_APPROVED);
        }])->get();

        return Excel::download(new CompanyAttendanceReportSendStatusExcel($companies, $start, $end), now()->format('Y-m-d').'-company-attendance-report.xlsx');
    }

    public function toggleSelect($id)
    {
        $report = CompanyAttendanceReport::findOrFail($id);
        if ($report->trainees()->where('active', true)->count()) {
            $report->trainees()->update(['active' => false]);
        } else {
            $report->trainees()->update(['active' => true]);
        }
        return redirect()->route('back.reports.company-attendance.show', $id);
    }
    public function excel($report_id)
    {
        // زيادة الذاكرة والوقت للتعامل مع البيانات الكبيرة
        ini_set('memory_limit', '1024M');
        set_time_limit(600); // 10 دقائق
        
        $report = CompanyAttendanceReport::findOrFail($report_id);
        $courseName = $report->company->name_ar;
        $sessionDate = $report->date_from->format('Y-m-d');
        return Excel::download(new CompanyAttendanceSheetExport($report), $courseName.'-'.$sessionDate.'-.xlsx');
    }

    public function individual($report_id, $trainee_id)
    {
        $record = CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $report_id)
            ->where('trainee_id', $trainee_id)
            ->with('trainee', 'report')
            ->first();

        return Inertia::render('Back/Reports/CompanyAttendance/Individual', [
            'record' => $record,
        ]);
    }

    public function individualPdf($report_id, $trainee_id)
    {
        $pdf = CompanyAttendanceReportService::makeIndividualPdf($report_id, $trainee_id, request()->boolean('with_attendance_times'));
        
        // Debug: Log the wkhtmltopdf binary path being used
        \Log::info('WKHTMLTOPDF Binary Path Debug (Individual)', [
            'config_value' => config('snappy.pdf.binary'),
            'env_value' => env('WKHTML_PDF_BINARY'),
            'default_path' => base_path('vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64'),
        ]);
        
        return $pdf->inline();
    }

    public function individualEmail($report_id, $trainee_id)
    {
        $report = CompanyAttendanceReport::findOrFail($report_id);

        Mail::to($report->emails_to()->pluck('email') ?: null)
            ->bcc($report->emails_cc()->pluck('email') ?: null)
            ->send(new CompanyAttendanceIndividualReportMail($report->id, $trainee_id, request()->boolean('with_attendance_times')));

        return redirect()->route('back.reports.company-attendance.show', $report->id);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $id
     * @return void
     */
    public function addEmail(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email:rfc,dns',
            'type' => 'required|in:to,cc,bcc',
        ]);

        $company_attendance = CompanyAttendanceReport::findOrFail($id);
        if (!$company_attendance->emails()->where('type', $request->type)->where('email', $request->email)->exists()) {
            $company_attendance->emails()->create($request->except('_token'));
        }

        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $id
     * @return void
     */
    public function addEmailInBulk(Request $request, $id)
    {
        $request->validate([
            'email.*' => 'required|email:rfc,dns',
            'type' => 'required|in:to,cc,bcc',
        ]);

        $company_attendance = CompanyAttendanceReport::findOrFail($id);

        foreach ($request->email as $email) {
            if (!$company_attendance->emails()->where('type', $request->type)->where('email', $email)->exists()) {
                $company_attendance->emails()->create([
                    'email' => $email,
                    'type' => $request->type,
                ]);
            }
        }

        return redirect()->route('back.reports.company-attendance.show', $id);
    }

    public function removeEmail($report_id, $id)
    {
        CompanyAttendanceReportsEmail::findOrFail($id)->delete();
        return redirect()->route('back.reports.company-attendance.show', $report_id);
    }

    public function clearAllEmails($report_id)
    {
        $report = CompanyAttendanceReport::findOrFail($report_id);
        $report->emails()->delete();
        return redirect()->route('back.reports.company-attendance.show', $report_id);
    }

    public function addTrainee($report_id, Request $request)
    {
        $request->validate([
            'trainee_id' => 'required|exists:trainees,id',
        ]);

        $report = CompanyAttendanceReport::findOrFail($report_id);

        $report->trainees()->attach($request->trainee_id);

        return redirect()->route('back.reports.company-attendance.show', $report_id);
    }

    public function emailApprove($id)
{
    // Debugging
    dd("here");

    // Fetch the report by ID
    $report = CompanyAttendanceReport::findOrFail($id);
    
    // Update the approved_at timestamp
    $report->update(['approved_at' => now()]);
    
    // Redirect back to the report view with a success message
    return redirect()->route('back.reports.company-attendance.show', $id)->with('success', 'تم اعتماد التقرير بنجاح.');
}

public function updateTemplate($id, Request $request)
{
    $request->validate([
        'template_type' => 'required|in:default,simple,modern,gradient,classic,royal,elegant',
    ]);

    $report = CompanyAttendanceReport::findOrFail($id);
    $templateType = $request->input('template_type');
    
    // تنظيف القيمة والتأكد من أنها صحيحة
    $templateType = trim($templateType);
    if (empty($templateType) || !in_array($templateType, ['default', 'simple', 'modern', 'gradient', 'classic', 'royal', 'elegant'])) {
        $templateType = 'default';
    }
    
    // تحديث القيمة مباشرة
    $report->template_type = $templateType;
    $report->save();

    return redirect()->route('back.reports.company-attendance.show', $id)
        ->with('success', 'تم تحديث القالب بنجاح إلى: ' . $this->getTemplateName($templateType));
}

private function getTemplateName($templateType)
{
    switch ($templateType) {
        case 'simple':
            return 'القالب المبسط';
        case 'modern':
            return 'القالب الحديث';
        case 'gradient':
            return 'القالب المتدرج';
        case 'classic':
            return 'القالب الكلاسيكي';
        case 'royal':
            return 'القالب الملكي';
        case 'elegant':
            return 'القالب الأنيق';
        default:
            return 'القالب الافتراضي';
    }
}

}
