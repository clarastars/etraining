<?php

namespace App\Console\Commands;

use App\Models\Back\Company;
use App\Models\Back\CompanyAttendanceReport;
use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Services\CompanyAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AutomateCompanyAttendanceSheetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     **/
    protected $signature = 'company-attendance-sheets:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $from_date = Carbon::parse('2026-09-01')->startOfDay();
        $to_date = Carbon::parse('2026-09-29')->endOfDay();
        $this->createReportsBasedOnTraineedInvoiced($from_date, $to_date);
        return 1;
    }

    public function createReportsBasedOnTraineedInvoiced(Carbon $from_date, Carbon $to_date)
    {
        //$companies = [
        //    'ed1bcd52-5fe0-488c-9dd6-2436d5f93ca8',
        //];

        $company_id = 'beff7800-b94b-4831-8044-8cdcb152087a';

        $select_invoices_from = ['2026-08-01', '2026-08-31'];

        //foreach ($companies as $company_id) {
            $count = Company::with('invoices')
                // ->where('id', $company_id)
                ->whereHas('invoices', function ($query) use (
                    $from_date,
                    $select_invoices_from
                ) {
                    $query->whereBetween('to_date', $select_invoices_from);
                })->count();
            $this->info('Found companies with invoices: '.$count);

            Company::with('invoices')
                // ->where('id', $company_id)
                    ->whereHas('invoices', function ($query) use (
                    $from_date,
                    $select_invoices_from
                ) {
                    $query->whereBetween('to_date', $select_invoices_from);
                })->count();

            // Companies that don't have invoices in the past month, to skip.
            $companies_with_invoices = Company::with('invoices')
                // ->where('id', $company_id)
                ->whereHas('invoices', function (
                    $query
                ) use ($from_date, $select_invoices_from) {
                    $query->whereBetween('to_date', $select_invoices_from);
                })->pluck('id');
            $companies_without_invoices = Company::whereNotIn('id', $companies_with_invoices)->pluck('name_ar');
            foreach ($companies_without_invoices as $name_ar) {
                $this->info('No invoices for company: '.$name_ar);
            }

            Company::with('invoices')
                // ->where('id', $company_id)
                ->whereHas('invoices', function ($query) use (
                    $from_date,
                    $select_invoices_from
                ) {
                    $query->whereBetween('to_date', $select_invoices_from);
                })->chunk(20, function ($companies) use ($from_date, $to_date, $select_invoices_from) {
                    foreach ($companies as $company) {

                        //$companies_to_execlude = [
                        //    'ed1bcd52-5fe0-488c-9dd6-2436d5f93ca8',
                        //];

                        //if (in_array($company->id, $companies_to_execlude, true)) {
                        //    $this->info('Excluded company: '.$company->name_ar);
                        //    continue;
                        //}

                        // Checks
                        if ($company->trainees()->count() === 0) {
                            $this->info('No trainees. Skipping: '.$company->name_ar);
                            continue;
                        }
                        //$currentMonthReport = $company->company_attendance_reports()->whereBetween('date_to', [
                        //        $from_date,
                        //        $to_date
                        //    ])->first();
                        //if ($currentMonthReport) {
                        //    $this->info('Already created. Skipping: '.$company->name_ar);
                        //    continue;
                        //}

                        $lastReport = $company->company_attendance_reports()->orderBy('date_from', 'desc')->first();

                        if ($lastReport) {
                            $this->info('New report from last report: '.$company->name_ar.','.$company->trainees()->count());
                            $this->makeNewReportFromLastReportBasedOnInvoices($company, $lastReport, $from_date, $to_date, $select_invoices_from[0], $select_invoices_from[1]);
                        } else {
                            if (! $company->email) {
                                $this->info('No email for company. Skipping: '.$company->name_ar);
                                continue;
                            }
                            $this->info('No last report. Creating new report - '.$company->name_ar.','.$company->trainees()->count());
                            $this->makeNewReportBasedOnInvoices($company, $from_date, $to_date, $select_invoices_from[0], $select_invoices_from[1]);
                        }
                    }
                });
        //}
    }

    public function makeNewReportFromLastReportBasedOnInvoices($company, $lastReport, $dateFrom, $dateTo, $invoicesDateFrom, $invoicesDateTo)
    {
        $clone = app()->make(CompanyAttendanceReportService::class)->newReport($company->id);
        $clone->date_from = Carbon::parse($dateFrom)->setTimezone('Asia/Riyadh')->startOfDay();
        $clone->date_to = Carbon::parse($dateTo)->setTimezone('Asia/Riyadh')->endOfDay();
        $clone->save();

        $emails = [
            ['type' => 'bcc', 'email' => 'sara@hadaf-hq.com'],
            ['type' => 'bcc', 'email' => 'm_shehatah@hadaf-hq.com'],
            ['type' => 'bcc', 'email' => 'ceo@hadaf-hq.com'],
            ['type' => 'bcc', 'email' => 'mashael.a@hadaf-hq.com'],
        ];
        if ($company->salesperson_email) {
            $repEmails = explode(', ', $company->salesperson_email);
            foreach ($repEmails as $repEmail) {
                $emails[] = ['type' => 'bcc', 'email' => $repEmail];
            }
        }
        $clone->emails()->createMany($emails);

        $emails = explode(', ', $company->email);
        foreach ($emails as $email) {
            if ($clone->emails()->where('email', $email)->count()) {
                continue;
            }
            $clone->emails()->create([
                'type' => 'to',
                'email' => $email,
            ]);
        }

        $this->updateReportEmailsPerToCenter($clone);

        $trainee_ids = Invoice::where('company_id', $company->id)
            ->whereBetween('to_date', [$invoicesDateFrom, $invoicesDateTo])
            ->pluck('trainee_id');

        $clone->trainees()->sync($trainee_ids);

        // Check if a trainee in the current report has any resignations.
        foreach ($clone->trainees as $trainee) {
            if ($trainee->resignations()->whereBetween('created_at', [$clone->date_from, $clone->date_to])->count()) {
                $clone->trainees()->detach($trainee->id);
                $this->info('Trainee has resignations. Removed: '.$trainee->name. ' CompanyName: '.$company->name_ar);
            }
        }

        //app()->make(CompanyAttendanceReportService::class)->approve($clone->id);
    }

    public function makeNewReportBasedOnInvoices($company, $dateFrom, $dateTo, $invoicesDateFrom, $invoicesDateTo)
    {
        $report = app()->make(CompanyAttendanceReportService::class)->newReport($company->id);
        $report->date_from = Carbon::parse($dateFrom)->setTimezone('Asia/Riyadh')->startOfDay();
        $report->date_to = Carbon::parse($dateTo)->setTimezone('Asia/Riyadh')->endOfDay();
        $report->save();

        $emails = [
            ['type' => 'bcc', 'email' => 'sara@hadaf-hq.com'],
            ['type' => 'bcc', 'email' => 'm_shehatah@hadaf-hq.com'],
            ['type' => 'bcc', 'email' => 'ceo@hadaf-hq.com'],
            ['type' => 'bcc', 'email' => 'mashael.a@hadaf-hq.com'],
        ];

        if ($company->salesperson_email) {
            $repEmails = explode(', ', $company->salesperson_email);
            foreach ($repEmails as $repEmail) {
                $emails[] = ['type' => 'bcc', 'email' => $repEmail];
            }
        }
        $report->emails()->createMany($emails);

        $emails = explode(', ', $company->email);
        foreach ($emails as $email) {
            if ($report->emails()->where('email', $email)->count()) {
                continue;
            }
            $report->emails()->create([
                'type' => 'to',
                'email' => $email,
            ]);
        }

        $this->updateReportEmailsPerToCenter($report);

        $trainee_ids = Invoice::where('company_id', $company->id)
            ->whereBetween('to_date', [$invoicesDateFrom, $invoicesDateTo])
            ->pluck('trainee_id');

        $report->trainees()->sync($trainee_ids);

        // Check if a trainee in the current report has any resignations.
        foreach ($report->trainees as $trainee) {
            if ($trainee->resignations()->whereBetween('created_at', [$report->date_from, $report->date_to])->count()) {
                $report->trainees()->detach($trainee->id);
                $this->info('Trainee has resignations. Removed: '.$trainee->name);
            }
        }

        //app()->make(CompanyAttendanceReportService::class)->approve($report->id);
    }

    public function updateReportEmailsPerToCenter(CompanyAttendanceReport $report)
    {
        if (Str::contains($report->company->center->domain_name, 'jisr') || Str::contains($report->company->center->domain_name, 'jasarah')) {
            $bccEmails = $report->emails()->where('type', 'bcc')->where('email', 'LIKE', '%ptc-ksa%')->get();
            foreach ($bccEmails as $bccEmail) {
                $bccEmail->update(['email' => Str::before($bccEmail->email, '@') . '@hadaf-hq.com']);
            }
        }

        // To reduce confusion... all emails go to Jisr for now.
        //if (Str::contains($report->company->center->domain_name, 'jasarah')) {
        //    $bccEmails = $report->emails()->where('type', 'bcc')->where('email', 'LIKE', '%ptc-ksa%')->get();
        //    foreach ($bccEmails as $bccEmail) {
        //        $bccEmail->update(['email' => Str::before($bccEmail->email, '@') . '@jasarah-ksa.com']);
        //    }
        //}

        return $report;
    }
}
