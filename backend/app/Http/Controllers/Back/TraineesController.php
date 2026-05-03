<?php

namespace App\Http\Controllers\Back;

use App\Actions\Fortify\CreateNewTraineeUser;
use App\Http\Controllers\Controller;
use App\Jobs\ExportArchivedTraineesToExcelJob;
use App\Jobs\ExportTraineesToExcelJob;
use App\Mail\DeletedTraineeMail;
use App\Models\AppSetting;
use App\Models\Back\AttendanceReportRecord;
use App\Models\Back\AttendanceReportRecordWarning;
use App\Models\Back\Audit;
use App\Models\Back\Company;
use App\Models\Back\ExportTraineesToExcelJobTracker;
use App\Models\Back\Instructor;
use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Models\Back\TraineeBlockList;
use App\Models\Back\TraineeCustomCertificate;
use App\Models\Back\TraineeGroup;
use App\Models\City;
use App\Models\EducationalLevel;
use App\Models\InboxMessage;
use App\Models\MaritalStatus;
use App\Models\RequestCounter;
use App\Models\User;
use App\Notifications\CustomTraineeNotification;
use App\Notifications\TraineeApplicationApprovedNotification;
use App\Notifications\TraineePrivateMessage;
use App\Services\NoonService;
use App\Notifications\TraineeRestoredNotification;
use App\Notifications\TraineeSetupAccountNotification;
use App\Notifications\TraineeWelcomeNotification;
use App\Rules\TraineeGroupLimit;
use App\Services\TraineesServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Mail;
use PDF;
use Spatie\MediaLibrary\Support\MediaStream;
use App\Http\Controllers\ZohoSignController;

class TraineesController extends Controller
{
    protected $service;

    public function __construct(TraineesServices $services)
    {
        $this->service = $services;
    }

    public function index()
    {
        // Check if the logged-in user is sara@hadaf-hq.com
        $isSaraUser = auth()->user()->email === 'sara@hadaf-hq.com';
        
        if ($isSaraUser) {
            // For sara@hadaf-hq.com, show only: name, identity_number, company name
            // Order by newest first (latest)
            return Inertia::render('Back/Trainees/Index', [
                'trainees' => Trainee::with('company')
                    ->select('id', 'name', 'identity_number', 'company_id', 'created_at')
                    ->latest()
                    ->paginate(20),
                'isSaraView' => true,
            ]);
        }
        
        // For other users, show the normal view
        return Inertia::render('Back/Trainees/Index', [
            'trainees' => Trainee::with('company')
                ->with('trainee_group')
                ->latest()
                ->paginate(20),
            'isSaraView' => false,
        ]);
    }

    /**
     * List trainees who self-registered via the public engineer registration flow.
     */
    public function indexEngineers()
    {
        $isSaraUser = auth()->user()->email === 'sara@hadaf-hq.com';

        $baseQuery = Trainee::with('company')->where('is_engineer', true);

        if ($isSaraUser) {
            return Inertia::render('Back/Trainees/EngineersIndex', [
                'trainees' => (clone $baseQuery)
                    ->select('id', 'name', 'identity_number', 'company_id', 'created_at')
                    ->latest()
                    ->paginate(20),
                'isSaraView' => true,
            ]);
        }

        return Inertia::render('Back/Trainees/EngineersIndex', [
            'trainees' => (clone $baseQuery)
                ->with('trainee_group')
                ->latest()
                ->paginate(20),
            'isSaraView' => false,
        ]);
    }

    public function indexArchived()
    {
        return Inertia::render('Back/Trainees/IndexArchived', [
            'blocked_trainees' => Trainee::with('company')
                ->with('trainee_group')
                ->where('suspended_at', null)
                ->onlyTrashed()
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create()
    {
        return Inertia::render('Back/Trainees/Create', [
            'companies' => Company::get(),
            'trainee_groups' => TraineeGroup::get(),
            'cities' => City::orderBy('name_ar')->get(),
            'marital_statuses' => MaritalStatus::orderBy('order')->get(),
            'educational_levels' => EducationalLevel::orderBy('order')->get(),
        ]);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'trainee_group_name' => 'nullable|string|max:255',
            'email' => 'string|max:255|unique:trainees,email',
            'name' => 'required|string|max:255',
            'english_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:trainees,phone',
            'phone_additional' => 'nullable|string|max:255|unique:trainees,phone_additional',
            'identity_number' => 'required|string|max:255|unique:trainees,identity_number',
            'birthday' => 'required|date',
            'educational_level_id' => 'nullable|exists:educational_levels,id',
            'city_id' => 'nullable|exists:cities,id',
            'marital_status_id' => 'nullable|exists:marital_statuses,id',
            'children_count' => 'nullable|integer|max:20',
            'bill_from_date' => ['nullable', 'date'],
            'linked_date' => ['nullable', 'date'],
            'trainee_message' => 'nullable|string|max:255',
            'job_number' => 'nullable|string|max:255',
        ]);

        $trainee = $this->service->store($request->except('_token'));

        return redirect()->route('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param $id
     *
     * @return \Inertia\Response
     */
    public function show($id)
    {
        $trainee = Trainee::withTrashed()->find($id);

        // Check if trainee exists, return 404 if not found
        if (!$trainee) {
            abort(404);
        }

        //zoho check contarct status
        $zohoController = new ZohoSignController();
        $response = $zohoController->adminCheckContractStatusForTrainee($trainee);




        $in_block_list = TraineeBlockList::where('phone', $trainee->phone)
            ->orWhere('identity_number', $trainee->identity_number)
            ->orWhere('email', $trainee->email)
            ->orWhere('name', $trainee->name)
            ->first();

        $attributes = [
            'phone' => $trainee->phone,
            'identity_number' => $trainee->identity_number,
            'email' => $trainee->email,
            'name' => $trainee->name,
        ];

        $in_block_list = TraineeBlockList::where(function ($query) use ($attributes) {
            foreach ($attributes as $column => $value) {
                $query->orWhere($column, $value);
            }
        })->first();


        Audit::create([
            'event' => 'trainees.show',
            'user_type' => User::class,
            'user_id' => auth()->user()->id,
            'auditable_id' => auth()->user()->id,
            'auditable_type' => User::class,
            'new_values' => [
              'company' => $trainee->company->name_ar ?? null,
              'trainee' => $trainee->name,
              'trainee_id' => $trainee->id,
            ],
        ]);

        // Get users with specific roles for special document access
        $allowedRoleIds = [
            '209eb897-2385-43c8-970c-279c426c9b30', // مديرون شؤون متدربات
            '7a9101c7-728f-4653-82f1-e6318359c344', // شؤون متدربات
            '05a1703e-2672-4b6d-866c-20702a00c3a5', // دور إضافي مصرح له
            '95f7a0b9-adb6-42b3-a718-811047ea088d', // دور إضافي
        ];
        
        $allowedUsers = User::whereHas('roles', function($query) use ($allowedRoleIds) {
            $query->whereIn('id', $allowedRoleIds);
        })->pluck('email')->toArray();

        // Check if user has limited view permission (identity only)
        // This user can only view identity file and basic info
        $hasLimitedView = auth()->user()->can('view-trainee-identity-only');

        if ($hasLimitedView) {
            // Limited view: only show name, identity number, and identity file
            // But we need to send a full trainee object with default values for Vue component
            $trainee = Trainee::withTrashed()
                ->select('id', 'name', 'identity_number', 'created_at', 'updated_at', 'deleted_at')
                ->findOrFail($id);

            // Get identity file URL if exists
            $identityCopyUrl = $trainee->identity_copy_url;

            // Create a minimal trainee object with required properties for Vue component
            $traineeData = [
                'id' => $trainee->id,
                'name' => $trainee->name,
                'identity_number' => $trainee->identity_number,
                'identity_copy_url' => $identityCopyUrl,
                // Add default values for required properties
                'company' => null,
                'company_id' => null,
                'educational_level' => null,
                'educational_level_id' => null,
                'city' => null,
                'city_id' => null,
                'marital_status' => null,
                'marital_status_id' => null,
                'trainee_group' => null,
                'trainee_group_id' => null,
                'user' => null,
                'invoices' => [],
                'custom_certificates' => [],
                'uk_certificates' => [],
                'general_files_count' => 0,
                'email' => null,
                'english_name' => null,
                'birthday' => null,
                'phone' => null,
                'phone_additional' => null,
                'national_address' => null,
                'children_count' => null,
                'bill_from_date' => null,
                'linked_date' => null,
                'trainee_message' => null,
                'job_number' => null,
                'dont_edit_notice' => false,
                'phone_is_owned' => null,
                'phone_ownership_status' => null,
                'whatsapp_link' => null,
                'qualification_copy_url' => null,
                'bank_account_copy_url' => null,
                'national_address_copy_url' => null,
                'cv_url' => null,
                'gosi_certificate_copy_url' => null,
                'qiwa_contract_copy_url' => null,
            ];

            return Inertia::render('Back/Trainees/Show', [
                'is_limited_view' => true,
                'trainee' => $traineeData,
                'companies' => collect([]),
                'in_block_list' => null,
                'trainee_groups' => collect([]),
                'cities' => collect([]),
                'marital_statuses' => collect([]),
                'educational_levels' => collect([]),
                'allowed_users_for_special_documents' => $allowedUsers,
            ]);
        }

        return Inertia::render('Back/Trainees/Show', [
            'companies' => Company::get(),
            'in_block_list' => $in_block_list,
            'trainee' => Trainee::withTrashed()
                ->with([
                    'company',
                    'educational_level',
                    'city',
                    'marital_status',
                    'trainee_group',
                    'user',
                ])
                ->withCount('general_files')
                ->with(['invoices' => function($q) {
                    $q->orderBy('number', 'desc');
                }])
                ->with(['custom_certificates' => function($q) {
                    $q->orderBy('created_at', 'desc')->limit(20);
                }])
                ->with(['uk_certificates' => function($q) {
                    $q->where('status', 'sent')
                      ->with(['ukCertificate.course'])
                      ->orderBy('sent_at', 'desc')
                      ->limit(5);
                }])
                ->findOrFail($id),
            'trainee_groups' => TraineeGroup::get(),
            'cities' => City::orderBy('name_ar')->get(),
            'marital_statuses' => MaritalStatus::orderBy('order')->get(),
            'educational_levels' => EducationalLevel::orderBy('order')->get(),
            'allowed_users_for_special_documents' => $allowedUsers,
        ]);
    }

    /**
     *
     * @param $id
     *
     * @return \Inertia\Response
     */
    public function gosi()
    {
        $requestCounter = optional(RequestCounter::where('month', now()->format('Y-m')))->first() ?? 0;
        $gosiMonthlyRequests = AppSetting::where('name', 'gosi_monthly_requests')->value('value');

        return Inertia::render('Back/Trainees/gosi', [
            'requestCounter' => $requestCounter,
            'gosiMonthlyRequests' => $gosiMonthlyRequests,
        ]);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return
     */
    public function storeIdentity(Request $request, $trainee_id)
    {
        $request->validate([
            'identity_card_copy' => 'required_without:file',
            'file' => 'required_without:identity_card_copy',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('identity_card_copy') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'identity');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // When the other has been filled, mark the application as pending approval from the administration.
        if ($trainee->identity_copy_url) {
            $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
            $trainee->save();
        }

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteIdentity(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('identity')->each->forceDelete();

        $trainee->status = Instructor::STATUS_PENDING_UPLOADING_FILES;
        $trainee->save();

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return
     */
    public function storeQualification(Request $request, $trainee_id)
    {

        $request->validate([
            'qualification_copy' => 'required_without:file',
            'file' => 'required_without:qualification_copy',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('qualification_copy') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'qualification');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // When the other has been filled, mark the application as pending approval from the administration.
        if ($trainee->qualification_copy_url) {
            $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
            $trainee->save();
        }

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteQualification(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('qualification')->each->forceDelete();

        $trainee->status = Instructor::STATUS_PENDING_UPLOADING_FILES;
        $trainee->save();

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return
     */
    public function storeBankAccount(Request $request, $trainee_id)
    {
        $request->validate([
            'bank_account_copy' => 'required_without:file',
            'file' => 'required_without:bank_account_copy',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('bank_account_copy') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'bank-account');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // When the other has been filled, mark the application as pending approval from the administration.
        if ($trainee->bank_account_copy_url) {
            $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
            $trainee->save();
        }

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return
     */
    public function storeNationalAddress(Request $request, $trainee_id)
    {
        $request->validate([
            'national_address_copy' => 'required_without:file',
            'file' => 'required_without:national_address_copy',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('national_address_copy') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'national-address');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // When the other has been filled, mark the application as pending approval from the administration.
        if ($trainee->national_address_copy_url) {
            $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
            $trainee->save();
        }

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return
     */
    public function storeCv(Request $request, $trainee_id)
    {
        $request->validate([
            'cv' => 'required_without:file',
            'file' => 'required_without:cv',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('cv') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'cv');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // When the other has been filled, mark the application as pending approval from the administration.
        if ($trainee->cv_url) {
            $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
            $trainee->save();
        }

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteBankAccount(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('bank-account')->each->forceDelete();

        $trainee->status = Instructor::STATUS_PENDING_UPLOADING_FILES;
        $trainee->save();

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteNationalAddress(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('national-address')->each->forceDelete();

        $trainee->status = Instructor::STATUS_PENDING_UPLOADING_FILES;
        $trainee->save();

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteCv(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('cv')->each->forceDelete();

        $trainee->status = Instructor::STATUS_PENDING_UPLOADING_FILES;
        $trainee->save();

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeGosiCertificate(Request $request, $trainee_id)
    {
        $request->validate([
            'gosi_certificate' => 'required_without:file',
            'file' => 'required_without:gosi_certificate',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('gosi_certificate') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'gosi-certificate');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // For optional documents, we don't change the status when uploaded
        // The status will be managed by the approval process

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteGosiCertificate(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('gosi-certificate')->each->forceDelete();

        // For optional documents, we don't change the status when deleted
        // The status will be managed by the approval process

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeQiwaContract(Request $request, $trainee_id)
    {
        $request->validate([
            'qiwa_contract' => 'required_without:file',
            'file' => 'required_without:qiwa_contract',
        ]);

        $trainee = Trainee::findOrFail($trainee_id);
        $file = $request->file('qiwa_contract') ?: $request->file('file');
        $uploaded_file = $trainee->uploadToFolder($file, 'qiwa-contract');

        // Refresh the trainee model to get the updated URL after upload
        $trainee->refresh();

        // For optional documents, we don't change the status when uploaded
        // The status will be managed by the approval process

        return $uploaded_file;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteQiwaContract(Request $request, $trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->getMedia('qiwa-contract')->each->forceDelete();

        // For optional documents, we don't change the status when deleted
        // The status will be managed by the approval process

        return response()->redirectToRoute('back.trainees.show', $trainee->id);
    }

    /**
     * Show the form for creating a custom certificate.
     *
     * @param                          $trainee_id
     *
     * @return \Inertia\Response
     */
    public function createCustomCertificate($trainee_id)
    {
        $trainee = Trainee::withTrashed()
            ->with(['custom_certificates' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->findOrFail($trainee_id);

        return Inertia::render('Back/Trainees/Certificates/Create', [
            'trainee' => $trainee,
        ]);
    }

    /**
     * Store a custom certificate for a trainee.
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeCustomCertificate(Request $request, $trainee_id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'issued_at' => 'required|date',
            'certificate_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $trainee = Trainee::withTrashed()->findOrFail($trainee_id);

        $certificate = TraineeCustomCertificate::create([
            'trainee_id' => $trainee->id,
            'title' => $request->title,
            'issued_at' => $request->issued_at,
        ]);

        if ($request->hasFile('certificate_file')) {
            $certificate->addMediaFromRequest('certificate_file')
                ->toMediaCollection('certificate_file');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'تم إنشاء الشهادة بنجاح',
                'redirect' => route('back.trainees.show', $trainee->id),
            ]);
        }

        return redirect()->route('back.trainees.show', $trainee->id);
    }

    /**
     * Get signed or direct URL for custom certificate file.
     */
    public function getCustomCertificateFileUrl($trainee_id, $id)
    {
        try {
            $certificate = TraineeCustomCertificate::where('trainee_id', $trainee_id)
                ->where('id', $id)
                ->firstOrFail();

            $media = $certificate->getFirstMedia('certificate_file');

            if (!$media) {
                return response()->json(['error' => 'الملف غير موجود'], 404);
            }

            if ($media->disk === 's3') {
                $signedUrl = $media->getTemporaryUrl(now()->addHour(), '', [
                    'ResponseContentDisposition' => 'inline; filename="' . $media->name . '"',
                ]);

                return response()->json([
                    'file_url' => $signedUrl,
                    'file_name' => $media->name,
                    'expires_at' => now()->addHour()->toISOString(),
                ]);
            }

            // للتخزين المحلي: إرجاع رابط يمر عبر التطبيق لتفادي 404 مع /storage/
            $streamUrl = route('back.trainees.custom-certificates.stream-file', [
                'trainee_id' => $trainee_id,
                'id' => $id,
            ]);

            return response()->json([
                'file_url' => $streamUrl,
                'file_name' => $media->name,
                'expires_at' => null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting custom certificate file URL', [
                'trainee_id' => $trainee_id,
                'certificate_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'حدث خطأ أثناء الحصول على رابط الملف'], 500);
        }
    }

    /**
     * Stream or redirect to custom certificate file (عرض الملف من خلال التطبيق).
     */
    public function streamCustomCertificateFile($trainee_id, $id)
    {
        try {
            $certificate = TraineeCustomCertificate::where('trainee_id', $trainee_id)
                ->where('id', $id)
                ->firstOrFail();

            $media = $certificate->getFirstMedia('certificate_file');

            if (!$media) {
                abort(404, 'الملف غير موجود');
            }

            if ($media->disk === 's3') {
                $signedUrl = $media->getTemporaryUrl(now()->addHour(), '', [
                    'ResponseContentDisposition' => 'inline; filename="' . $media->name . '"',
                ]);

                return redirect()->away($signedUrl);
            }

            $path = $media->getPath();
            if (!is_file($path)) {
                abort(404, 'الملف غير موجود');
            }

            return response()->file($path, [
                'Content-Disposition' => 'inline; filename="' . $media->name . '"',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'الشهادة غير موجودة');
        } catch (\Exception $e) {
            \Log::error('Error streaming custom certificate file', [
                'trainee_id' => $trainee_id,
                'certificate_id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'حدث خطأ أثناء عرض الملف');
        }
    }

    /**
     * Shows all trainees with groups.
     *
     * @returns array
     */
    public function withGroups(): array
    {
        if (filter_var(request()->load_trainees, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) {
            $groups = TraineeGroup::with([
                'trainees' => function ($q) {
                    if (request()->company_id) {
                        return $q->where('company_id', request()->company_id);
                    }
                },
            ])
                ->get()
                ->toArray();
        } else {
            $groups = TraineeGroup::get()
                ->toArray();
        }

        foreach ($groups as &$group) {
            $group['id'] .= '-group';
        }

        return $groups;
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Throwable
     */
    public function assignInstructor(Request $request)
    {
        $request->validate([
            'instructor_id' => 'required|exists:instructors,id',
            'trainees.*' => 'nullable',
        ]);

        \DB::beginTransaction();
        $trainee_ids = [];

        if (count($request->trainees)) {
            foreach ($request->trainees as $trainee_id) {
                if (Str::contains($trainee_id, '-group')) {
                    $group = TraineeGroup::findOrFail(Str::before($trainee_id, '-group'));
                    $group_ids = $group->trainees()->pluck('trainees.id');
                    foreach ($group_ids as $id) {
                        $trainee_ids[] = $id;
                    }
                } else {
                    $trainee_ids[] = $trainee_id;
                }
            }
        }

        // Get affected trainees before update for logging
        $traineesToRemove = Trainee::withoutGlobalScopes()
            ->where('instructor_id', $request->instructor_id)
            ->get(['id', 'name', 'instructor_id']);
        
        $traineesToAssign = Trainee::withoutGlobalScopes()
            ->whereIn('id', $trainee_ids)
            ->get(['id', 'name', 'instructor_id']);
        
        // Remove instructor from old trainees
        Trainee::withoutGlobalScopes()->where('instructor_id', $request->instructor_id)->update(['instructor_id' => null]);
        
        // Log removal
        foreach ($traineesToRemove as $trainee) {
            Log::info('INSTRUCTOR_ID_CHANGED: TraineesController::assignInstructor - Removing instructor', [
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'old_instructor_id' => $trainee->instructor_id,
                'new_instructor_id' => null,
                'instructor_id_being_removed' => $request->instructor_id,
                'reason' => 'إعادة تعيين مدرب - تم إزالة المدرب القديم من المتدربين قبل تعيين مدرب جديد',
                'location' => __FILE__ . ':' . __LINE__,
                'method' => 'TraineesController::assignInstructor',
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? null,
                'request_data' => [
                    'trainees' => $request->trainees,
                    'trainee_ids' => $trainee_ids,
                ],
            ]);
        }
        
        // Assign instructor to new trainees
        Trainee::withoutGlobalScopes()->whereIn('id', $trainee_ids)->update(['instructor_id' => $request->instructor_id]);
        
        // Log assignment
        foreach ($traineesToAssign as $trainee) {
            Log::info('INSTRUCTOR_ID_CHANGED: TraineesController::assignInstructor - Assigning instructor', [
                'trainee_id' => $trainee->id,
                'trainee_name' => $trainee->name,
                'old_instructor_id' => $trainee->instructor_id,
                'new_instructor_id' => $request->instructor_id,
                'reason' => 'إعادة تعيين مدرب - تم تعيين مدرب جديد للمتدرب',
                'location' => __FILE__ . ':' . __LINE__,
                'method' => 'TraineesController::assignInstructor',
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? null,
                'request_data' => [
                    'trainees' => $request->trainees,
                    'trainee_ids' => $trainee_ids,
                ],
            ]);
        }

        \DB::commit();

        return redirect()->back();
    }

    /**
     * Display the page for linking trainee groups to instructors.
     *
     * @return \Inertia\Response
     */
    public function linkGroups()
    {
        $traineeGroups = TraineeGroup::withCount('trainees')
            ->orderBy('name')
            ->get()
            ->map(function($group) {
                // الحصول على instructor_id الأكثر شيوعاً في المجموعة
                // إذا كان جميع المتدربين لديهم نفس instructor_id، فهذا هو المدرب المربوط بالشعبة
                $instructorCounts = Trainee::where('trainee_group_id', $group->id)
                    ->whereNotNull('instructor_id')
                    ->selectRaw('instructor_id, COUNT(*) as count')
                    ->groupBy('instructor_id')
                    ->orderByDesc('count')
                    ->first();

                if ($instructorCounts && isset($instructorCounts->instructor_id)) {
                    $instructor = Instructor::select(['id', 'name'])->find($instructorCounts->instructor_id);
                    $group->current_instructor_id = $instructorCounts->instructor_id;
                    $group->current_instructor_name = optional($instructor)->name;
                } else {
                    // إذا لم يكن هناك مدرب مرتبط، ابحث عن أي متدرب لديه instructor_id
                    $anyTrainee = Trainee::where('trainee_group_id', $group->id)
                        ->whereNotNull('instructor_id')
                        ->with(['instructor' => function($q) {
                            $q->select(['id', 'name']);
                        }])
                        ->first();
                    
                    $group->current_instructor_id = optional($anyTrainee)->instructor_id;
                    $group->current_instructor_name = optional(optional($anyTrainee)->instructor)->name;
                }
                
                return $group;
            });

        $instructors = Instructor::select(['id', 'name'])->orderBy('name')->get();

        return Inertia::render('Back/Trainees/LinkGroups', [
            'traineeGroups' => $traineeGroups,
            'instructors' => $instructors,
        ]);
    }

    /**
     * Store the linking of trainee groups to instructors.
     * Links all trainees in each group to the assigned instructor permanently.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeLinkGroups(Request $request)
    {
        $request->validate([
            'groups' => 'required|array',
            'groups.*.group_id' => 'required|exists:trainee_groups,id',
            'groups.*.instructor_id' => 'nullable|exists:instructors,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->groups as $groupData) {
                $groupId = $groupData['group_id'];
                $instructorId = $groupData['instructor_id'] ?? null;

                // ربط جميع متدربي هذه الشعبة بالمدرب بشكل دائم
                if ($instructorId) {
                    // Get trainees before update for logging
                    $trainees = Trainee::where('trainee_group_id', $groupId)
                        ->get(['id', 'name', 'instructor_id']);
                    
                    Trainee::where('trainee_group_id', $groupId)
                        ->update(['instructor_id' => $instructorId]);
                    
                    // Log assignment
                    foreach ($trainees as $trainee) {
                        Log::info('INSTRUCTOR_ID_CHANGED: TraineesController::storeLinkGroups - Assigning instructor to group', [
                            'trainee_id' => $trainee->id,
                            'trainee_name' => $trainee->name,
                            'old_instructor_id' => $trainee->instructor_id,
                            'new_instructor_id' => $instructorId,
                            'trainee_group_id' => $groupId,
                            'trainee_group_name' => TraineeGroup::find($groupId)->name ?? null,
                            'reason' => 'ربط مجموعة متدربين بمدرب - تم تعيين مدرب للمجموعة',
                            'location' => __FILE__ . ':' . __LINE__,
                            'method' => 'TraineesController::storeLinkGroups',
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()->name ?? null,
                            'request_data' => $groupData,
                        ]);
                    }
                } else {
                    // إذا لم يتم تحديد مدرب، إزالة الربط
                    // Get trainees before update for logging
                    $trainees = Trainee::where('trainee_group_id', $groupId)
                        ->whereNotNull('instructor_id')
                        ->get(['id', 'name', 'instructor_id']);
                    
                    Trainee::where('trainee_group_id', $groupId)
                        ->update(['instructor_id' => null]);
                    
                    // Log removal
                    foreach ($trainees as $trainee) {
                        Log::info('INSTRUCTOR_ID_CHANGED: TraineesController::storeLinkGroups - Removing instructor from group', [
                            'trainee_id' => $trainee->id,
                            'trainee_name' => $trainee->name,
                            'old_instructor_id' => $trainee->instructor_id,
                            'new_instructor_id' => null,
                            'trainee_group_id' => $groupId,
                            'trainee_group_name' => TraineeGroup::find($groupId)->name ?? null,
                            'reason' => 'ربط مجموعة متدربين بمدرب - لم يتم تحديد مدرب، تم إزالة instructor_id',
                            'location' => __FILE__ . ':' . __LINE__,
                            'method' => 'TraineesController::storeLinkGroups',
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()->name ?? null,
                            'request_data' => $groupData,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()
                ->route('back.trainees.link-groups')
                ->with('success', __('words.updated-successfully'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->route('back.trainees.link-groups')
                ->with('error', 'حدث خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }

    /**
     * Open a new account for the trainee where they can login with it.
     *
     * @param $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createUser($trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);

        $user = (new CreateNewTraineeUser())->create([
            'trainee_id' => $trainee->id,
            'name' => $trainee->name,
            'english_name' => $trainee->english_name,
            'email' => $trainee->email,
            'phone' => $trainee->phone,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        Notification::send($user, new TraineeSetupAccountNotification());

        $message = new InboxMessage();
        $message->body = 'لقد تم قبولك في منصة التدريب';
        $message->to_id = $user->id;
        $message->is_system_message = true;
        $message->save();

        return redirect()->route('back.trainees.show', $trainee->id);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function storeCvFromApplication(Request $request)
    {
        $request->validate([
            'identity_card_copy' => 'required',
            'qualification_copy' => 'required',
            'bank_account_copy' => 'required',
            'national_address_copy' => 'required',
            'cv' => 'required',
        ]);

        $this->storeIdentity($request, auth()->user()->trainee->id);
        $this->storeQualification($request, auth()->user()->trainee->id);
        $this->storeBankAccount($request, auth()->user()->trainee->id);
        $this->storeNationalAddress($request, auth()->user()->trainee->id);
        $this->storeCv($request, auth()->user()->trainee->id);

        $trainee = auth()->user()->trainee;
        $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
        $trainee->save();

        Notification::send(auth()->user(), new TraineeWelcomeNotification());

        return $trainee->media;
    }

    /**
     * Approving the instructor to use the platform and start broadcasting.
     *
     * @param $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approveUser($trainee_id)
    {
        //$this->authorize('approve-trainee-applicants');

        $trainee = Trainee::findOrFail($trainee_id);
        
        // Approve the trainee regardless of GOSI or Qiwa documents
        $trainee->status = Trainee::STATUS_APPROVED;
        $trainee->approved_by_id = auth()->user()->id;
        $trainee->approved_at = now();
        $trainee->save();

        Notification::send($trainee->user ?: $trainee, new TraineeApplicationApprovedNotification());

        Log::info('Trainee ID: ' . $trainee->id . ' has been approved by user: ' . auth()->user()->email);

        return redirect()->route('back.trainees.show', $trainee->id);
    }

    /**
     * Update trainee data.
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse|string
     * @throws \Throwable
     */
    public function update(Request $request, $trainee_id)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'trainee_group_name' => 'nullable|string|max:255',
            'email' => 'required|string|max:255|unique:trainees,email,' . $trainee_id,
            'name' => 'required|string|max:255',
            'english_name' => 'nullable|string|max:255',
            'identity_number' => 'required|string|max:255|unique:trainees,identity_number,' . $trainee_id,
            'birthday' => 'nullable|date',
            'educational_level_id' => 'nullable|exists:educational_levels,id',
            'city_id' => 'nullable|exists:cities,id',
            'marital_status_id' => 'nullable|exists:marital_statuses,id',
            'bill_from_date' => ['nullable', 'date'],
            'linked_date' => ['nullable', 'date'],
            'trainee_group_name' => ['nullable', 'string', 'max:255', new TraineeGroupLimit],
            'trainee_message' => 'nullable|string|max:255',
            'job_number' => 'nullable|string|max:255',
        ]);

        $request->validate([
            'identity_number' => ['required', 'unique:instructors'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:instructors'],
            'phone' => ['required', 'string', 'max:255', 'unique:instructors'],
        ]);

        $trainee = Trainee::findOrFail($trainee_id);

        if ($trainee->user) {
            $request->validate([
                'email' => 'required|string|max:255|unique:users,email,' . $trainee->user->id,
            ]);
        }

        DB::beginTransaction();
        $trainee->update($request->except('_token'));
        if ($user = $trainee->user) {
            $user->email = $trainee->refresh()->email;
            $user->save();
        }

        if ($request->trainee_group_name) {
            $group = TraineeGroup::firstOrCreate(['name' => $request->trainee_group_name]);
            $trainee->trainee_group_id = $group->id;
            $trainee->save();
        } else {
            $trainee->trainee_group_id = null;
            $trainee->save();
        }

        DB::commit();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return redirect()->route('back.trainees.show', $trainee_id);
    }

    public function blockView($trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        return Inertia::render('Back/Trainees/Block', [
            'trainee' => $trainee,
        ]);
    }

    public function showBlocked($id)
    {
        $trainee = Trainee::with(['educational_level', 'city', 'marital_status', 'trainee_group', 'company'])
                ->withCount('general_files')
                ->with('invoices')
                ->with(['custom_certificates' => function($q) {
                    $q->orderBy('created_at', 'desc')->limit(20);
                }])
                ->with(['uk_certificates' => function($q) {
                    $q->where('status', 'sent')
                      ->with(['ukCertificate.course'])
                      ->orderBy('sent_at', 'desc')
                      ->limit(5);
                }])
                ->withTrashed()
                ->findOrFail($id);

        Audit::create([
            'event' => 'trainees.show',
            'auditable_id' => auth()->user()->id,
            'auditable_type' => User::class,
            'new_values' => [
                'company' => $trainee->company->name_ar ?? null,
                'trainee' => $trainee->name,
                'trainee_id' => $trainee->id,
            ],
        ]);

        // Get users with specific roles for special document access
        $allowedRoleIds = [
            '209eb897-2385-43c8-970c-279c426c9b30', // مديرون شؤون متدربات
            '7a9101c7-728f-4653-82f1-e6318359c344', // شؤون متدربات
            '05a1703e-2672-4b6d-866c-20702a00c3a5', // دور إضافي مصرح له
            '95f7a0b9-adb6-42b3-a718-811047ea088d', // دور إضافي
        ];
        
        $allowedUsers = User::whereHas('roles', function($query) use ($allowedRoleIds) {
            $query->whereIn('id', $allowedRoleIds);
        })->pluck('email')->toArray();

        return Inertia::render('Back/Trainees/ShowBlocked', [
            'trainee' => $trainee,
            'trainee_groups' => TraineeGroup::get(),
            'cities' => City::orderBy('name_ar')->get(),
            'marital_statuses' => MaritalStatus::orderBy('order')->get(),
            'educational_levels' => EducationalLevel::orderBy('order')->get(),
            'allowed_users_for_special_documents' => $allowedUsers,
        ]);
    }


    public function unblock(Request $request, $trainee_id)
    {
        $trainee = Trainee::onlyTrashed()->findOrFail($trainee_id);
        $trainee->suspended_at = null;
        $trainee->deleted_by_id = null;
        $trainee->save();
        $trainee->restore();
        $blockList = TraineeBlockList::where('trainee_id', $trainee->id)->first();
        if ($blockList) {
            $blockList->delete();
        }

        optional(
            User::where('email', 'sara@ptc-ksa.net')
                ->first()
        )
            ->notify(
                new TraineeRestoredNotification(
                    $trainee->name,
                    $trainee->phone,
                    $trainee->email,
                    auth()->user(),
                    $trainee->deleted_remark
                )
            );

        return redirect()->route('back.trainees.show', $trainee_id);
    }

    public function block(Request $request, $trainee_id)
    {
        DB::beginTransaction();
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->update([
            'deleted_remark' => $request->deleted_remark,
        ]);
        $trainee->deleted_by_id = auth()->user()->id;
        $trainee->delete();
        //if ($trainee->user) {
        //    $trainee->user->delete();
        //}
        $users = User::permission('receive-notification-on-trainee-delete')->get();
        //Mail::to($users)
        //    ->queue(new DeletedTraineeMail($trainee, auth()->user()->email));

        DB::commit();
        return redirect()->route('back.trainees.index');
    }

    public function suspendCreate($trainee_id)
    {
        return Inertia::render('Back/Trainees/Suspend', [
            'trainee' => Trainee::findOrFail($trainee_id),
        ]);
    }

    public function suspendEdit($trainee_block_list_id)
    {
        return Inertia::render('Back/Trainees/BlockedList/Edit', [
            'traineeBlockList' => TraineeBlockList::with('trainee')->findOrFail($trainee_block_list_id),
        ]);
    }

    public function suspendUpdate($trainee_block_list_id, Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'reason' => 'required|string|max:255',
        ]);

        $traineeBlockList = TraineeBlockList::findOrFail($trainee_block_list_id);
        $traineeBlockList->update($request->except('_token'));
        if ($traineeBlockList->trainee) {
            $traineeBlockList->trainee->deleted_remark = $request->reason;
            $traineeBlockList->trainee->save();
        }

        return redirect()->route('back.trainees.block-list.index');
    }

    public function suspend(Request $request, $trainee_id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        $trainee = Trainee::findOrFail($trainee_id);
        $trainee->deleted_remark = $request->reason;
        $trainee->suspended_at = now()->setSecond(0);
        $trainee->deleted_by_id = auth()->user()->id;
        $trainee->save();
        $block = TraineeBlockList::create([
            'trainee_id' => $trainee->id,
            'identity_number' => $trainee->identity_number,
            'name' => $trainee->name,
            'english_name' => $trainee->english_name,
            'email' => $trainee->email,
            'phone' => $trainee->phone,
            'phone_additional' => $trainee->phone_additional,
            'reason' => $request->reason,
        ]);
        //if ($trainee->user) {
        //    $trainee->user->delete();
        //}
        $trainee->delete();
        DB::commit();
        return redirect()->route('back.trainees.block-list.index');
    }

    public function suspendSelectedTrainees($trainee_id){
        DB::beginTransaction();
//        $trainees = Trainee::where('trainee_id', request()->trainee_id)->get();
        $trainees = Trainee::findOrFail($trainee_id);
        foreach ($trainees as $trainee) {
            $trainee->deleted_remark = 'استبعاد';
            $trainee->suspended_at = now()->setSecond(0);
            $trainee->deleted_by_id = auth()->user()->id;
            $trainee->save();
            $block = TraineeBlockList::create([
                'trainee_id' => $trainee->id,
                'identity_number' => $trainee->identity_number,
                'name' => $trainee->name,
                'email' => $trainee->email,
                'phone' => $trainee->phone,
                'phone_additional' => $trainee->phone_additional,
                'reason' => 'استبعاد',
            ]);
            //if ($trainee->user) {
            //    $trainee->user->delete();
            //}
            $trainee->delete();
        }
            DB::commit();
            return redirect()->route('back.trainees.block-list.index');
        }

    public function excel(Request $request)
    {
        $request->validate([
            'trainee_status_id' => 'nullable|numeric',
        ]);

        $validate = in_array((int)$request->trainee_status_id, [
            Trainee::STATUS_PENDING_APPROVAL,
            Trainee::STATUS_APPROVED,
            Trainee::STATUS_PENDING_UPLOADING_FILES,
            null,
        ], true);

        throw_if(!$validate, 'Unknown trainee status');

        $excelJob = new ExportTraineesToExcelJobTracker();
        $excelJob->trainee_status_id = $request->trainee_status_id;
        $excelJob->queued_at = now();
        $excelJob->user_id = auth()->user()->id;
        $excelJob->team_id = auth()->user()->current_team_id;
        $excelJob->save();

        Audit::create([
            'event' => 'companies.export.excel',
            'auditable_id' => auth()->user()->id,
            'auditable_type' => User::class,
            'new_values' => [],
        ]);

        dispatch(new ExportTraineesToExcelJob($excelJob));

        return $excelJob;
    }

    /**
     *
     * @param $id
     *
     * @return mixed
     */
    public function excelJob($id)
    {
        return ExportTraineesToExcelJobTracker::find($id);
    }

    public function excelJobDownload($id)
    {
        $tracker = ExportTraineesToExcelJobTracker::find($id);
        $file = $tracker->getFirstMedia('excel');
        if ($file->disk === 's3') {
            return redirect()->to(
                $file->getTemporaryUrl(now()->addMinutes(5), '', [
                    //'ResponseContentType' => 'application/octet-stream',
                ])
            );
        } else {
            return response()->download($file->getPath());
        }
    }

    public function archivedExcel()
    {
        $excelJob = new ExportTraineesToExcelJobTracker();
        $excelJob->queued_at = now();
        $excelJob->user_id = auth()->user()->id;
        $excelJob->team_id = auth()->user()->team_id;
        $excelJob->save();

        dispatch(new ExportArchivedTraineesToExcelJob($excelJob));

        return $excelJob;
    }


    public function resendInvitation($trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);

        if (!$trainee->user) {
            throw new \RuntimeException('There is no user created for this trainee to send an invite to.');
        }

        Notification::send($trainee->user, new TraineeSetupAccountNotification());

        return redirect()->route('back.trainees.show', $trainee_id);
    }

    /**
     * Assign a password for the trainee's account.
     *
     * @param                          $trainee_id
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function setPassword($trainee_id, Request $request)
    {
        $request->validate([
            'new_trainee_password' => 'required|string|max:56',
        ]);
        \DB::beginTransaction();

        $trainee = Trainee::withTrashed()->findOrFail($trainee_id);

        $user = $trainee->user;
        $user->password = Hash::make($request->new_trainee_password);
        $user->save();
        \DB::Commit();

        if($trainee->trashed()){
            return redirect()->route('back.trainees.show.blocked', $trainee_id);
        }else{
            return redirect()->route('back.trainees.show', $trainee_id);
        }

    }

    public function sendNotificationForm()
    {
        $this->authorize('send-messages-to-groups-of-trainees');
        return Inertia::render('Back/Trainees/SendNotificationForm');
    }

    public function sendNotification(Request $request)
    {
        $this->authorize('send-messages-to-groups-of-trainees');

        $request->validate([
            'registered_today_only' => 'nullable|boolean',
            'to_trainees_status' => 'required',
            'email_title' => 'nullable|string|max:255',
            'email_body' => 'nullable|string|max:500',
            'sms_body' => 'nullable|string|max:500',
        ]);

        if ($request->to_trainee_status === 'linked_to_company_and_contract') {
            $trainees = Trainee::where('company_id', '!=', null)
                ->where('trainee_group_id', '!=', null)
                ->where('instructor_id', '!=', null);
        } elseif ($request->to_trainee_status === 'not_linked_to_a_company') {
            $trainees = Trainee::where('company_id', null);
        } elseif ($request->to_trainee_status === 'all_trainees') {
            $trainees = Trainee::query();
        } else {
            $trainees = Trainee::where('status', $request->to_trainees_status);
        }

        if ($request->registered_today_only) {
            $trainees->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()]);
        }

        $trainees = $trainees->get();

        Notification::send(
            $trainees,
            new CustomTraineeNotification($request->email_title, $request->email_body, $request->sms_body)
        );

        return redirect()->route('back.trainees.index');
    }

    public function sendPrivateNotificationForm($id)
    {
        return Inertia::render('Back/Trainees/PrivateNotifications/Create', [
            'trainee' => Trainee::findOrFail($id),
        ]);
    }

    /**
     *
     * @param \Illuminate\Http\Request $request
     * @param                          $id
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendPrivateNotification(Request $request, $id)
    {
        $request->validate([
            'email_title' => 'required|string|max:500',
            'email_body' => 'required|string|max:500',
            'sms_body' => 'nullable|string|max:500',
        ]);
        $trainee = Trainee::findOrFail($id);
        $trainee->notify(new TraineePrivateMessage($request->email_title, $request->email_body, $request->sms_body));
        Log::info([
            'Sending message to: ' . $trainee->email,
            'Body: ' . $request->email_body,
        ]);
        return redirect()->route('back.trainees.show', $trainee->id);
    }

    public function warnings($trainee_id)
    {
        return AttendanceReportRecordWarning::where('trainee_id', $trainee_id)
            ->with([
                'attendance_report_record' => function ($q) {
                    $q->with([
                        'course_batch_session' => function ($q) {
                            $q->with([
                                'course' => function ($q) {
                                    $q->with('instructor');
                                },
                            ]);
                        },
                    ]);
                },
            ])
            ->get();
    }

    /**
     * Deletes a warning.
     *
     * @param $trainee_id
     * @param $id
     *
     * @return mixed
     */
    public function warningDelete($trainee_id, $id)
    {
        $warning = AttendanceReportRecordWarning::findOrFail($id);
        $warning->attendance_report_record->status = AttendanceReportRecord::STATUS_ABSENT_WITH_EXCUSE;
        $warning->attendance_report_record->save();
        $warning->delete();
        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Delete all warnings under trainee.
     *
     * @param $trainee_id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Throwable
     */
    public function warningDeleteAll($trainee_id)
    {
        DB::beginTransaction();
        $warnings = AttendanceReportRecordWarning::where('trainee_id', $trainee_id)->get();
        foreach ($warnings as $warning) {
            $warning->attendance_report_record->status = AttendanceReportRecord::STATUS_ABSENT_WITH_EXCUSE;
            $warning->attendance_report_record->save();
        }
        AttendanceReportRecordWarning::where('trainee_id', $trainee_id)->delete();
        DB::commit();

        return redirect()->route('back.trainees.show', $trainee_id);
    }

    public function updatedDeletedRemark($trainee_id, Request $request)
    {
        $request->validate([
            'deleted_remark' => 'nullable|string|max:255',
        ]);

        $t = Trainee::withTrashed()->find($trainee_id);
        $t->deleted_remark = $request->deleted_remark;
        $t->save();

        return $t;
    }

    public function attendanceSheetPdf($id)
    {
        $trainee = Trainee::withTrashed()->findOrFail($id);

        $records = AttendanceReportRecord::where('trainee_id', $id)
                ->orderBy('session_starts_at');

        if ($trainee->company_id === 'ca9709ac-6e7a-4de6-9d38-2e8b901a6f2a') {
            $records = $records->where('session_starts_at', '>=', now()->subMonths(2)->firstOfMonth());
        }

        $pdf = PDF::loadView('pdf.trainees.attendance-sheet', [
            'trainee' => $trainee,
            'records' => $records
                ->with([
                    'course_batch_session' => function ($q) {
                        $q->with('course');
                    },
                ])
                ->get(),
        ]);

        // Debug: Log the wkhtmltopdf binary path being used
        \Log::info('WKHTMLTOPDF Binary Path Debug (Trainees)', [
            'config_value' => config('snappy.pdf.binary'),
            'env_value' => env('WKHTML_PDF_BINARY'),
            'default_path' => base_path('vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64'),
        ]);

        return $pdf->inline();
    }

    public function invoices($trainee_id)
    {
        return Invoice::where('trainee_id', $trainee_id)
            ->get();
    }

    public function audit($trainee_id)
    {
        $audits = Audit::where('auditable_id', $trainee_id)
            ->withoutGlobalScopes()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($audits);
    }

    public function indexFixedTrainingCosts()
    {
        return Inertia::render('Back/Trainees/IndexFixedTrainingCosts', [
            'trainees' => Trainee::with('company')
                ->with('trainee_group')
                ->where('override_training_costs', '!=', null)
                ->latest()
                ->paginate(40),
        ]);
    }

    public function deleteFromBlockList($id)
    {
        $trainee = Trainee::find($id);
        
        // Check if trainee exists, return 404 if not found
        if (!$trainee) {
            abort(404);
        }
        
        TraineeBlockList::where('phone', $trainee->phone)
            ->orWhere('identity_number', $trainee->identity_number)
            ->orWhere('email', $trainee->email)
            ->orWhere('name', $trainee->name)
            ->first()
            ->delete();

        return redirect()->route('back.trainees.show', $trainee->id);
    }

    public function suspendAll(Request $request){

        DB::beginTransaction();
        if($request->deleted_remark){
            $reason=$request->deleted_remark;
        }else{
            $reason='استبعاد من الشركة';
        }

        $trainees = Trainee::findOrFail($request->data);
            foreach ($trainees as $trainee) {
                DB::beginTransaction();
                $trainee->deleted_remark = $reason;
                $trainee->suspended_at = now()->setSecond(0);
                $trainee->deleted_by_id = auth()->user()->id;
                $trainee->save();
                $block = TraineeBlockList::create([
                    'trainee_id' => $trainee->id,
                    'identity_number' => $trainee->identity_number,
                    'name' => $trainee->name,
                    'email' => $trainee->email,
                    'phone' => $trainee->phone,
                    'phone_additional' => $trainee->phone_additional,
                    'reason' => $reason,
                ]);
                $trainee->delete();
                DB::commit();
                }

        DB::commit();


        if (Str::contains(redirect()->back()->getTargetUrl(), 'companies')) {
            return redirect()->back();
        }

        return redirect()->route('back.trainees.block-list.index');


    }

    public function unBlockAll(Request $request){

        DB::beginTransaction();
        $trainees = Trainee::onlyTrashed()->findOrFail($request->data);
        foreach ($trainees as $trainee) {
            $trainee->suspended_at = null;
            $trainee->deleted_by_id = null;
            $trainee->save();
            $trainee->restore();
            $blockList = TraineeBlockList::where('trainee_id', $trainee->id)->first();
            if ($blockList) {
                $blockList->delete();
            }
        }
        DB::commit();

        if (Str::contains(redirect()->back()->getTargetUrl(), 'companies')) {
            return redirect()->back();
        }

        return redirect()->route('back.trainees.block-list.index');

    }

    public function traineeMessage()
    {

    }

    public function downloadAllFiles($id)
    {
        $trainee = Trainee::withTrashed()->find($id);
        
        // Check if trainee exists, return 404 if not found
        if (!$trainee) {
            abort(404);
        }
        
        $downloads = $trainee->getMedia('identity')
            ->merge($trainee->getMedia('qualification'))
            ->merge($trainee->getMedia('bank-account'))
            ->merge($trainee->getMedia('national-address'))
            ->merge($trainee->getMedia('cv'))
            ->merge($trainee->getMedia('gosi-certificate'))
            ->merge($trainee->getMedia('qiwa-contract'))
            ->merge($trainee->getMedia('general_files'));

        return MediaStream::create(str_slug($trainee->name) . '-files.zip')
            ->addMedia($downloads);

    }


    public function getTraineeContract()
{
    $trainee = auth()->user();
    return response()->json([
        'zoho_contract_id' => $trainee->zoho_contract_id
    ]);
}
    public function contractGuides()
    {

        return Inertia::render('Contract/ContractGuides');
    }

    function toggleGosiDeleted($trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        if ($trainee->gosi_deleted_at) {
            $trainee->gosi_deleted_at = null;
        } else {
            $trainee->gosi_deleted_at = now();
        }
        $trainee->save();
        return redirect()->back();
    }

    public function gosiLog()
    {
        $logs = \App\Models\GosiEmployeeData::orderBy('updated_at', 'desc')
            ->paginate(5);

        $startOfWeek = now()->startOfMonth();
        $endOfWeek = now()->endOfMonth();

        $weeklyReasonStats = \App\Models\GosiEmployeeData::whereBetween('updated_at', [$startOfWeek, $endOfWeek])->get()->reduce(function ($carry, $item) {
            $carry['مكتب التوظيف'] += $item->reason_employment_office ? 1 : 0;
            $carry['التحصيل'] += $item->reason_collection ? 1 : 0;
            $carry['شؤون المتدربات'] += $item->reason_trainee_affairs ? 1 : 0;
            $carry['المبيعات'] += $item->reason_sales ? 1 : 0;
            $carry['أخرى'] += $item->reason_other ? 1 : 0;
            return $carry;
        }, [
            'مكتب التوظيف' => 0,
            'التحصيل' => 0,
            'شؤون المتدربات' => 0,
            'المبيعات' => 0,
            'أخرى' => 0,
        ]);

        return Inertia::render('Back/Trainees/GosiLog/Index', [
            'logs' => $logs,
            'weekly_reason_stats' => $weeklyReasonStats,
        ]);
    }

    /**
     * Check if trainee has missing optional documents and set status accordingly
     *
     * @param Trainee $trainee
     * @return bool
     */
    private function checkOptionalDocumentsAndSetStatus($trainee)
    {
        $missingOptionalDocuments = [];
        
        if (!$trainee->gosi_certificate_copy_url) {
            $missingOptionalDocuments[] = 'GOSI Certificate';
        }
        if (!$trainee->qiwa_contract_copy_url) {
            $missingOptionalDocuments[] = 'Qiwa Contract';
        }
        
        // If optional documents are missing, set status to pending approval
        if (!empty($missingOptionalDocuments)) {
            $trainee->status = Trainee::STATUS_PENDING_APPROVAL;
            $trainee->save();
            
            Log::info('Trainee ID: ' . $trainee->id . ' status set to pending approval due to missing optional documents: ' . implode(', ', $missingOptionalDocuments));
            
            return false; // Documents are missing
        }
        
        return true; // All optional documents are present
    }

    /**
     * Public method to check and update trainee status based on optional documents
     *
     * @param $trainee_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkOptionalDocumentsStatus($trainee_id)
    {
        $trainee = Trainee::findOrFail($trainee_id);
        $hasAllDocuments = $this->checkOptionalDocumentsAndSetStatus($trainee);
        
        if ($hasAllDocuments) {
            return redirect()->route('back.trainees.show', $trainee->id)
                ->with('success', 'All optional documents are present. Trainee can be approved.');
        } else {
            return redirect()->route('back.trainees.show', $trainee->id)
                ->with('error', 'Trainee status set to pending approval due to missing optional documents.');
        }
    }

    /**
     * Refresh invoices from Noon payment gateway for a specific trainee
     *
     * @param string $trainee_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function refreshInvoices($trainee_id)
    {
        \Log::info("Starting refresh invoices for trainee: {$trainee_id}");
        
        $trainee = Trainee::findOrFail($trainee_id);
        
        // Get all invoices for this trainee that have payment_reference_id (Noon orders)
        $invoices = $trainee->invoices()
            ->whereNotNull('payment_reference_id')
            ->where('payment_method', Invoice::PAYMENT_METHOD_CREDIT_CARD)
            ->get();

        \Log::info("Found {$invoices->count()} invoices to check");

        $updatedCount = 0;
        $errors = [];

        foreach ($invoices as $invoice) {
            try {
                // Get order details from Noon
                $noonService = app(NoonService::class);
                $order = $noonService->getOrder($invoice->payment_reference_id, 5676); // Try Jasarah first
                
                if (is_null($order) || $order->resultCode === 5021 || $order->resultCode === 19089 || $order->resultCode === 19001) {
                    $order = $noonService->getOrder($invoice->payment_reference_id, 0); // Try Jisr
                }

                if ($order && $noonService->isPaymentSuccess($order)) {
                    // Update invoice with latest payment details
                    $invoice->update([
                        'payment_detail_method' => $order->result->paymentDetails->mode ?? $invoice->payment_detail_method,
                        'payment_detail_brand' => $order->result->paymentDetails->brand ?? $invoice->payment_detail_brand,
                        'status' => Invoice::STATUS_PAID,
                        'paid_at' => $invoice->paid_at ?? now(),
                    ]);
                    $updatedCount++;
                } elseif ($order) {
                    // Order exists but payment might have failed
                    $invoice->update([
                        'status' => Invoice::STATUS_UNPAID,
                        'paid_at' => null,
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = "Error updating invoice {$invoice->number_formatted}: " . $e->getMessage();
                \Log::error("Error refreshing invoice {$invoice->id} from Noon", [
                    'invoice_id' => $invoice->id,
                    'payment_reference_id' => $invoice->payment_reference_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        \Log::info("Refresh completed. Updated: {$updatedCount}, Errors: " . count($errors));

        if ($updatedCount > 0) {
            return response()->json([
                'success' => true,
                'message' => "تم تحديث {$updatedCount} فاتورة بنجاح من نون",
                'updated_count' => $updatedCount
            ]);
        } elseif (count($errors) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث بعض الفواتير: ' . implode(', ', $errors),
                'errors' => $errors
            ], 400);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'لا توجد فواتير تحتاج تحديث من نون',
                'updated_count' => 0
            ]);
        }
    }
}
