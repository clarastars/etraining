<?php
use App\Classes\GosiEmployee;
use App\Http\Controllers\Back\CompanyAliasesController;
use App\Http\Controllers\Back\CompanyResignationsController;
use App\Http\Controllers\Back\TraineesGroupsController;
use App\Http\Controllers\CompanyAllowedUsersController;
use App\Http\Controllers\Webhooks\MailController;
use App\Http\Controllers\ZoomAccountController;
use App\Models\Back\Audit;
use App\Models\Back\Invoice;
use App\Models\Back\Resignation;
use App\Models\Back\TestExportController;
use App\Models\Back\Trainee;


use App\Models\RequestCounter;
use App\Models\User;
use App\Services\GosiService;
use Illuminate\Mail\Markdown;


Route::get('/apple-touch-icon.png', fn() => response('', 204));
Route::get('/apple-touch-icon-precomposed.png', fn() => response('', 204));
Route::get('/apple-touch-icon-152x152.png', fn() => response('', 204));
Route::get('/apple-touch-icon-152x152-precomposed.png', fn() => response('', 204));

Route::get('/qr1', function() {
    return redirect('https://forms.gle/t9nhZgKqz5za9xmp9');
});

// CSRF token refresh route for handling expired sessions
Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});

// Test cookie setting
Route::get('/test-cookies', function () {
    $response = response()->json(['message' => 'Testing cookies']);
    
    // Set test cookies
    $response->headers->setCookie(
        cookie('test_cookie', 'test_value', 60, '/', 'app.jasarah-ksa.com', true, true, false, 'Lax')
    );
    
    $response->headers->setCookie(
        cookie('jasarah_session_v2', 'test_session', 60, '/', 'app.jasarah-ksa.com', true, true, false, 'Lax')
    );
    
    return $response;
});

// Test session status
Route::get('/test-session', function () {
    return response()->json([
        'session_id' => session()->getId(),
        'csrf_token' => csrf_token(),
        'session_data' => session()->all(),
        'is_secure' => request()->isSecure(),
        'session_domain' => config('session.domain'),
        'session_secure' => config('session.secure'),
        'session_driver' => config('session.driver'),
        'session_cookie' => config('session.cookie'),
        'cookies_sent' => request()->cookies->all(),
    ]);
});

// Force session start test
Route::get('/force-session', function () {
    // Force start session
    session()->start();
    
    // Set some test data
    session(['test_key' => 'test_value']);
    
    return response()->json([
        'session_id' => session()->getId(),
        'session_data' => session()->all(),
        'message' => 'Session forced to start'
    ]);
});

// Debug session configuration
Route::get('/debug-session', function () {
    $session = session();
    $config = config('session');
    
    // Force start session if not started
    if (!$session->isStarted()) {
        $session->start();
    }
    
    // Set some test data
    $session->put('debug_test', 'session_working_' . time());
    
    return response()->json([
        'session_id' => $session->getId(),
        'session_name' => $session->getName(),
        'session_data' => $session->all(),
        'session_started' => $session->isStarted(),
        'session_config' => [
            'driver' => $config['driver'],
            'cookie' => $config['cookie'],
            'domain' => $config['domain'],
            'secure' => $config['secure'],
            'http_only' => $config['http_only'],
            'same_site' => $config['same_site'],
            'lifetime' => $config['lifetime'],
        ],
        'env_vars' => [
            'SESSION_DRIVER' => env('SESSION_DRIVER'),
            'SESSION_COOKIE' => env('SESSION_COOKIE'),
            'SESSION_DOMAIN' => env('SESSION_DOMAIN'),
            'SESSION_SECURE_COOKIE' => env('SESSION_SECURE_COOKIE'),
            'SESSION_CONNECTION' => env('SESSION_CONNECTION'),
        ],
        'request_cookies' => request()->cookies->all(),
        'response_headers' => response()->headers->all(),
        'redis_connection' => app('redis')->connection('default')->ping(),
    ]);
});

// Simple session test
Route::get('/test-session-simple', function () {
    // Force session start
    session()->start();
    
    // Set test data
    session(['test_key' => 'test_value_' . time()]);
    
    // Get session ID
    $sessionId = session()->getId();
    
    return response()->json([
        'session_id' => $sessionId,
        'session_data' => session()->all(),
        'cookies_sent' => response()->headers->getCookies(),
    ]);
});

Route::get('/qr2', function() {
    return redirect('https://docs.google.com/forms/d/e/1FAIpQLSdjDCpcabswqgSLLo7gI-h0LC3pspt6CJw94vsV6sioHdwRXQ/viewform?usp=sf_link');
});

Route::post('/attendance-due-dates-report', [\App\Http\Controllers\Back\ReportsController::class, 'attendanceDueDatesReport'])->name('attendance.due.dates.report');

Route::get('reports/attendance/download/{filename}', [\App\Http\Controllers\Back\ReportsController::class, 'downloadAttendanceReport'])
    ->name('reports.attendance.download');

    Route::get('/reports/attendance-due-dates/latest', function () {
        return response()->json(
            \App\Models\AttendanceReportDueDates::where('user_id', auth()->id())->latest()->first()
        );
    })->name('reports.attendance-due-dates.latest');

    Route::get('attendance-due-dates', [\App\Http\Controllers\Back\ReportsController::class, 'attendanceDueDates'])->name('attendance-due-dates.index');






Route::get('preview', function () {
 $markdown = new Markdown(view(), config('mail.markdown'));
 return $markdown->render("emails.resignations", [
     'resignation' => Resignation::first(),
 ]);
});

Route::get('my-ip', function() {
   return request()->ip();
});
Route::get('connect-with-me', function() {
    return redirect('https://api.whatsapp.com/send?phone=966553139979');
});

Route::get('verify', function() {

    $body = '{
        "messaging_product": "whatsapp",
        "to": "966543224520",
        "type": "template",
        "template": {
                "name": "ptc_login",
            "language": {
                    "code": "ar"
            },
            "components": [{
                    "type": "body",
                 "parameters": [{
                        "type": "text",
                    "text": "873719"
                }]
            }]
        }
    }';

    $publicKey = env('WA_ACCESS_TOKEN');

    $client = new \GuzzleHttp\Client([
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $publicKey,
        ]
    ]);

    $response = $client->post('https://graph.facebook.com/v15.0/106103318984035/messages', [
        'body' => $body,
    ]);

    // show verify page
});
Route::post('login/2fa-code', [\App\Http\Controllers\VerificationsController::class, 'sendCode'])->name('login.2fa-code');
Route::get('login/verify-code', [\App\Http\Controllers\VerificationsController::class, 'show'])->name('login.verify');
Route::post('login/verify-code', [\App\Http\Controllers\VerificationsController::class, 'verifyCode'])->name('login.verify-code');


Route::get('/resignations/{id}', [CompanyResignationsController::class, 'confirmReceived'])->name('resignations.confirm-received');

Route::post('webhooks/mail', [MailController::class, 'store'])->name('webhooks.mail');
Route::post('noon', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'storeNoonReceipt'])->name('webhooks.noon');

Route::get('version', function() {
    return '4.7';
});

Route::get('job', function() {
    dispatch_sync(new \App\Jobs\CompanyTraineeLinkAuditJob());
    return 'ok';
});

Route::get('phpxx', function() {
    return phpinfo();
});

Route::get('card-report', function() {
    ini_set('memory_limit', -1);
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PaidByCardReport(), now()->format('Y-m-d-h-i').'-paid-by-card.xlsx');
});

Route::get('phone-numbers/{company_id}', function() {

    AttendanceReportRecordWarning::whereBetween('created_at', [now()->setDate(2022, 1, 3)->startOfDay(), now()->setDate(2022, 1, 9)->endOfDay()])->whereHas('trainee', function ($q) { $q->whereIn('company_id', ['a980c16d-b3c9-4064-aaf7-f0b6174197de', '07b45abe-da70-48b9-816e-b092e1868ae7', '4a69b7ae-25ba-43a4-a8b1-a2936647f026', 'b8f17f79-c0c4-401f-9924-d7afec100c44', '9891d1d3-103d-4af9-ab70-2028d438fd03', '022ab58a-8a4b-4a46-8c0b-350ac5ea17c5', 'd72becb3-6e03-4546-85fa-4dbd008fccb6', 'a0bc0fcb-20e3-45a3-a057-be5d47d26d19', 'e24ce28c-2cd7-496e-9eb5-202d75df5176', 'ac7393a9-d56d-49ae-9c43-a9031f244416', '6e61eded-aa67-419c-ada3-a6182214b361', 'b5d59d5a-b899-4919-bb74-43d0080b3800']); })->delete();
    AttendanceReportRecord::whereBetween('created_at', [now()->setDate(2022, 1, 3)->startOfDay(), now()->setDate(2022, 1, 9)->endOfDay()])->whereHas('trainee', function ($q) { $q->whereIn('company_id', ['a980c16d-b3c9-4064-aaf7-f0b6174197de', '07b45abe-da70-48b9-816e-b092e1868ae7', '4a69b7ae-25ba-43a4-a8b1-a2936647f026', 'b8f17f79-c0c4-401f-9924-d7afec100c44', '9891d1d3-103d-4af9-ab70-2028d438fd03', '022ab58a-8a4b-4a46-8c0b-350ac5ea17c5', 'd72becb3-6e03-4546-85fa-4dbd008fccb6', 'a0bc0fcb-20e3-45a3-a057-be5d47d26d19', 'e24ce28c-2cd7-496e-9eb5-202d75df5176', 'ac7393a9-d56d-49ae-9c43-a9031f244416', '6e61eded-aa67-419c-ada3-a6182214b361', 'b5d59d5a-b899-4919-bb74-43d0080b3800']); })->update(['status' => 3]);

    $t = Trainee::where('company_id')->pluck('phone');
    $numbers = [];
    foreach ($t as $phone) {
        $parsed = app()->make(Trainee::class)->cleanUpThePhoneNumber($phone);
        if ($parsed) {
            $numbers[] = app()->make(Trainee::class)->cleanUpThePhoneNumber($phone);
        } else {
            $numbers[] = $phone;
        }
    }
    return $numbers;
});
Route::get('unpaid-invoices-x', function() {
    set_time_limit(1000);
    $data = [
        'status' => 0,
        'from_date' => '2023-03-01',
    ];
    $invoices = Invoice::where($data)->get();

    $invoicesData = [];

    foreach ($invoices as $invoice) {
        $invoicesData[] = [
            'trainee' => optional($invoice->trainee)->name,
            'company' => optional($invoice->company)->name_ar,
            'number' => $invoice->number,
            'grand_total' => $invoice->grand_total,
            'from_date' => $invoice->from_date,
            'to_date' => $invoice->to_date,
            'status' => $invoice->status,
        ];
    }

    return $invoicesData;
});
Route::get('deleted-unpaid-invoices-x', function() {
    set_time_limit(1000);
    $data = [
        'from_date' => '2025-03-01',
    ];
    $invoices = Invoice::onlyTrashed()->where($data)->notPaid()->get();

    $invoicesData = [];

    foreach ($invoices as $invoice) {
        $invoicesData[] = [
            'trainee' => optional($invoice->trainee)->name,
            'company' => optional($invoice->company)->name_ar,
            'number' => $invoice->number,
            'grand_total' => $invoice->grand_total,
            'from_date' => $invoice->from_date,
            'to_date' => $invoice->to_date,
            'deleted_at' => $invoice->deleted_at,
            'link' => route('back.finance.invoices.show', $invoice->id),
        ];
    }

    return $invoicesData;
});
Route::get('last-logged-at', function() {
    set_time_limit(1000);
    $users = User::all();

    $usersData = [];

    foreach ($users as $user) {
        $usersData[] = [
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
            'last_login_at' => $user->last_login_at,
        ];
    }

    return $usersData;
});


Route::get('s1s1', function() {
    $trainees = Trainee::candidates()->where('created_at', '>', now()->setDay(1))->get();
    $traineeData = [];


    foreach ($trainees as $trainee) {
        $traineeData[] = [
            // 'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            // 'email' => $trainee->email,
            // 'phone' => $trainee->phone,
            // 'instructor' => optional($trainee->instructor)->name,
            'group' => optional($trainee->trainee_group)->name,
        ];
    }

    return $traineeData;
});



Route::get('s1s2', function() {
    $ids = [];
    $trainees = Trainee::where('status', 2)->latest()->take(1000)->get();

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'phone' => $trainee->phone,
        ];
    }

    return $traineeData;
});
Route::get('s1s3', function() {
//    $trainees = Trainee::onlyTrashed()->get();
    $trainees = Trainee::where('status', 1)->get();

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            'email' => $trainee->email,
            'phone' => $trainee->phone,
            'instructor' => optional($trainee->instructor)->name,
            'group' => optional($trainee->trainee_group)->name,
        ];
    }

    return $traineeData;
});
Route::get('s1s30', function() {
//    $trainees = Trainee::onlyTrashed()->get();
    $trainees = Trainee::where('status', 0)->get();

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            'email' => $trainee->email,
            'phone' => $trainee->phone,
            'instructor' => optional($trainee->instructor)->name,
            'group' => optional($trainee->trainee_group)->name,
        ];
    }

    return $traineeData;
});
Route::get('s1s32', function() {
//    $trainees = Trainee::onlyTrashed()->get();
    set_time_limit(1000);
    $trainees = Trainee::where('status', 2)->get();

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            'email' => $trainee->email,
            'phone' => $trainee->clean_phone,
            'instructor' => optional($trainee->instructor)->name,
            'group' => optional($trainee->trainee_group)->name,
        ];
    }

    return $traineeData;
});

Route::get('all-trainees-in-dammam', function() {

    set_time_limit(1000);
    $cities = ['fdb24cfe-2096-4d22-903f-5d0106538200',
        '7d1a159e-a9a9-4fa9-856d-f30a7a82ebad',
        '9e72fafc-966a-443a-8bec-a9fcf0df5f22',
        '8e089244-0763-47d4-9ddb-122bab61e0ee',
        '24ae4690-3c0f-4627-a0c5-240daf4a9f2a',
        ];
    $trainees = Trainee::whereIn('city_id', $cities);

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            'email' => $trainee->email,
            'phone' => $trainee->clean_phone,
            'city' => optional($trainee->city)->name_ar,
        ];
    }

    return $traineeData;
});

Route::get('all-trainees', function() {

    set_time_limit(1000);
    $trainees = Trainee::all();

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            'email' => $trainee->email,
            'phone' => $trainee->clean_phone,
            'city' => optional($trainee->city)->name_ar,
        ];
    }

    return $traineeData;
});

Route::get('instructor-company-report', function() {
    $contracts = \App\Models\Back\CompanyContract::get();
    $data = [];
    foreach ($contracts as $contract) {
        if ($contract->instructors()->count()) {
            foreach ($contract->instructors as $instructor) {
                $company = \App\Models\Back\Company::find($contract->company_id);
                if (!$company) continue;
                $data[] = [
                    'company_id' => $contract->company_id,
                    'company_name_en' => optional($contract->company)->name_en,
                    'company_name' => optional($contract->company)->name_ar,
                    'instructor_name' => optional($instructor)->name,
                    'trainees_count' => Trainee::where('company_id', $contract->company_id)->count(),
                ];
            }
        }
    }

    return response()->json($data);
});

Route::get('zzwarnings', function() {
    $trainee_ids = \App\Models\Back\AttendanceReportRecord::where('attendance_report_id', 'd66634c9-d756-49c2-9181-00371fce6c4e')
        ->pluck('trainee_id');

    foreach ($trainee_ids as $trainee) {
        $trainee_model = Trainee::find($trainee);
        if ($trainee_model) {
            $warnings = \App\Models\Back\AttendanceReportRecordWarning::where('trainee_id', $trainee_model->id)
                ->where('attendance_report_id', 'd66634c9-d756-49c2-9181-00371fce6c4e')
                ->count();

            if ($warnings > 1) {
                $warnings = \App\Models\Back\AttendanceReportRecordWarning::where('trainee_id', $trainee_model->id)
                    ->where('attendance_report_id', 'd66634c9-d756-49c2-9181-00371fce6c4e')
                    ->first()
                    ->delete();
            }
        }
    }
});

Route::get('ttreport', function() {
    ini_set('memory_limit', -1);
    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TtTraineeReport(), now()->format('Y-m-d-h-i').'-absences.xlsx');
    return $traineeData;
});

Route::get('logged-in-times-for-specific-users', function() {
    $list = ['Collection1@Ptc-ksa.net ', 'collection6@ptc-ksa.net', ' collection3+nowair@ptc-ksa.net ', 'rawabib@ptc-ksa.net', 'acc8@ptc-ksa.net', 'collection7@jisr-ksa.com', 'collection5@ptc-ksa.net', 'collection4@ptc-ksa.net', 'collection2@ptc-ksa.net', 'reem@ptc-ksa.net', 'acc5@ptc-ksa.net ', 'acc10@jisr-ksa.com', 'acc@ptc-ksa.net', 'acc10@ptc-ksa.net', 'acc-walaa@ptc-ksa.net', 'acc2@jisr-ksa.com', 'acc7@ptc-ksa.net', 'acc6@ptc-ksa.net', 'acc-samar@ptc-ksa.net', 'acc-ahmed@ptc-ksa.net', 'payments@ptc-ksa.net', 'collection7@ptc-ksa.net'];
    $user_ids = User::whereIn('email', $list)->get()->pluck('id');
    $audits = Audit::whereIn('user_id', $user_ids)->with('auditable')->where('event', 'login')->whereBetween('created_at', ['2024-06-14', '2024-06-23'])->get();
    $report = [];
    foreach ($audits as $audit) {
        $report[] = [
            'name' => $audit->auditable->name,
            'email' => $audit->auditable->email,
            'Logged in' => $audit->created_at->setTimezone('Asia/Riyadh')->format('Y-m-d H:i'),
        ];
    }
    return $report;
});

Route::get('suspended-trainees', function() {
    $trainees = Trainee::onlyTrashed()->orWhereNotNull('suspended_at')->get();

    $traineeData = [];

    foreach ($trainees as $trainee) {
        $traineeData[] = [
            'name' => $trainee->name,
            'company' => optional($trainee->company)->name_ar,
            'email' => $trainee->email,
            'phone' => $trainee->phone,
            'instructor' => optional($trainee->instructor)->name,
            'group' => optional($trainee->trainee_group)->name,
        ];
    }

    return $traineeData;
});

Route::impersonate();

Route::get('/disabled', [\App\Http\Controllers\Back\DisableWebsiteController::class, 'showDisabledPage'])->name('disabled');

Route::get('/loginas/{user_id}', [\App\Http\Controllers\DebugController::class, 'loginAsUser'])->middleware('auth');

Route::get('invite/{invite_id}', [\App\Http\Controllers\InviteController::class, 'show'])->name('invite');
Route::post('invite/{invite_id}/accept', [\App\Http\Controllers\InviteController::class, 'accept'])->name('invite.accept');

Route::get('setup-account/{user_id}', [\App\Http\Controllers\ProfileController::class, 'setupAccount'])->name('setup-account');
Route::post('setup-account', [\App\Http\Controllers\ProfileController::class, 'updateAccount'])->name('setup-account.update')->middleware('auth');

Route::get('language/{language}', [\App\Http\Controllers\LanguageController::class, 'changeLanguage'])->name('language');

Route::get('/communication-policy', [\App\Http\Controllers\ContactUsController::class, 'index'])->name('contact');
Route::get('/requirements', [\App\Http\Controllers\RequirementsController::class, 'index'])->name('requirements');
Route::get('/terms', [\App\Http\Controllers\TermsController::class, 'index'])->name('terms');

Route::post('/register/trainees', [\App\Http\Controllers\Auth\RegisterTraineeController::class, 'store'])->name('register.trainees.store');
Route::get('/register/trainees', [\App\Http\Controllers\Auth\RegisterTraineeController::class, 'show'])->name('register.trainees');

Route::post('/register/engineers', [\App\Http\Controllers\Auth\RegisterTraineeController::class, 'storeEngineers'])->name('register.engineers.store');
Route::get('/register/engineers', [\App\Http\Controllers\Auth\RegisterTraineeController::class, 'showEngineers'])->name('register.engineers');

Route::post('/register/instructors', [\App\Http\Controllers\Auth\RegisterInstructorController::class, 'store'])->name('register.instructors.store');
Route::get('/register/instructors', [\App\Http\Controllers\Auth\RegisterInstructorController::class, 'show'])->name('register.instructors');

Route::middleware(['guest'])->get('/', [\App\Http\Controllers\WelcomeController::class, 'welcome']);

Route::middleware(['auth:sanctum'])->group(function() {
    Route::get('/register/instructors/application', [\App\Http\Controllers\Auth\RegisterInstructorController::class, 'application'])->name('register.instructors.application');
    Route::get('/register/trainees/application', [\App\Http\Controllers\Auth\RegisterTraineeController::class, 'application'])->name('register.trainees.application');

    Route::get('complaints', [\App\Http\Controllers\ComplaintsController::class, 'index'])->name('complaints.index');
    Route::post('complaints', [\App\Http\Controllers\ComplaintsController::class, 'store'])->name('complaints.store');

    Route::get('suggestions', [\App\Http\Controllers\SuggestionsController::class, 'index'])->name('suggestions.index');

    Route::get('company-roles', [\App\Http\Controllers\CompanyRolesController::class, 'index'])->name('company-roles.index');

    Route::get('company-roles', [\App\Http\Controllers\CompanyRolesController::class, 'index'])->name('company-roles.index');

    Route::get('management-roles', [\App\Http\Controllers\ManagementRolesController::class, 'index'])->name('management-roles.index');

    Route::get('management-roles', [\App\Http\Controllers\ManagementRolesController::class, 'index'])->name('management-roles.index');

    Route::get('obligations', [\App\Http\Controllers\ObligationsController::class, 'index'])->name('obligations.index');

    Route::get('user-guides', [\App\Http\Controllers\UserGuidesController::class, 'index'])->name('user-guides.index');

    Route::get('training-plan', [\App\Http\Controllers\TrainingPlanController::class, 'index'])->name('training-plan.index');

    Route::get('training-schedule', [\App\Http\Controllers\TrainingScheduleController::class, 'index'])->name('training-schedule.index');

    Route::get('survey', [\App\Http\Controllers\SurveyController::class, 'index'])->name('survey.index');
});

Route::middleware(['auth:sanctum'])->get('/trainees/application', [\App\Http\Controllers\TraineesApplicationController::class, 'index']);


//ebrahim comment
Route::middleware(['auth:sanctum', 'verified', 'approved-application'])->get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

Route::middleware(['auth:sanctum', 'verified', 'approved-application'])->group(function () {
    Route::get('/recorded-courses', [\App\Http\Controllers\Trainees\RecordedCoursesController::class, 'index'])->name('recorded-courses.index');
    Route::get('/recorded-courses/enrollments/{enrollment}', [\App\Http\Controllers\Trainees\RecordedCoursesController::class, 'show'])->name('recorded-courses.enrollments.show');
    Route::post('/recorded-courses/enrollments/{enrollment}/unlock', [\App\Http\Controllers\Trainees\RecordedCoursesController::class, 'unlock'])->name('recorded-courses.enrollments.unlock');
    Route::post('/recorded-courses/enrollments/{enrollment}/lessons/{lesson}/complete', [\App\Http\Controllers\Trainees\RecordedCoursesController::class, 'complete'])->name('recorded-courses.enrollments.lessons.complete');
    Route::get('/recorded-courses/enrollments/{enrollment}/lessons/{lesson}/stream', [\App\Http\Controllers\Trainees\RecordedCoursesController::class, 'stream'])->name('recorded-courses.enrollments.lessons.stream');
});
Route::get('/agreement', [\App\Http\Controllers\Trainees\AgreementController::class, 'show'])->name('agreement.show');
Route::post('/agreement/accept',[\App\Http\Controllers\Trainees\AgreementController::class ,'accept'])->name('agreement.accept');


// Route::get('/test88',function(){
//     return view('traineeAgreement');
// });



Route::get('/back/media/{media_id}', [\App\Http\Controllers\MediaController::class, 'download'])->name('back.media.download');

Route::middleware(['auth:sanctum', 'verified'])->group(function() {

    Route::get('/lookup/masdr/request-counter', function () {
        $requestCounter = optional(RequestCounter::where('month', now()->format('Y-m')))->first();
        return response()->json(['requestCounter' => $requestCounter]);
    })->name('back.gosi.request-counter');
    Route::get('/lookup/masdr/monthly-history', [\App\Http\Controllers\Back\GosiController::class, 'getMonthlyHistory'])->name('back.gosi.monthly-history');
    Route::post('masdr', [\App\Http\Controllers\Back\GosiController::class, 'show'])->name('back.gosi.show');
    Route::get('masdr/log', [\App\Http\Controllers\Back\TraineesController::class, 'gosiLog'])->name('back.trainees.gosi.log');
    Route::get('lookup/masdr', [\App\Http\Controllers\Back\TraineesController::class, 'gosi'])->name('back.trainees.gosi');

    // For everyone
    Route::get('inbox', [\App\Http\Controllers\InboxController::class, 'index'])->name('inbox.index');
    Route::post('back/zoom/signature', [\App\Http\Controllers\ZoomController::class, 'signature'])->name('back.zoom.signature');
    Route::post('back/zoom/meetings', [\App\Http\Controllers\ZoomMeetingsController::class, 'store'])->name('back.zoom.meetings.store');
    Route::post('back/zoom/meetings/configs', [\App\Http\Controllers\ZoomMeetingsController::class, 'configs'])->name('back.zoom.meetings.configs');

    Route::get('session-completed-landing', [\App\Http\Controllers\Back\SurveyLinksController::class, 'landed'])->name('back.session-completed-landing');

    Route::get('job-trackers/{id}', [\App\Http\Controllers\JobTrackersController::class, 'show'])->name('job-trackers.show');
    Route::get('job-trackers/{id}/download', [\App\Http\Controllers\JobTrackersController::class, 'download'])->name('job-trackers.download');


    Route::delete('/back/media/{media_id}', [\App\Http\Controllers\MediaController::class, 'delete'])->name('back.media.delete');

    // Orders
    Route::get('orders', [\App\Http\Controllers\OrdersController::class, 'index'])->name('orders.index');
    Route::get('orders-list', [\App\Http\Controllers\OrdersController::class, 'orders'])->name('orders-list');
    Route::post('orders-lix1st/new-email/approved/{id}', [\App\Http\Controllers\OrdersController::class, 'approveMail'])->name('new_email.approve-mail');
    Route::post('orders-list/new-email/rejected/{id}', [\App\Http\Controllers\OrdersController::class, 'rejectMail'])->name('new_email.reject-mail');
    Route::get('orders/hr', [\App\Http\Controllers\OrdersController::class, 'HR'])->name('orders.hr');
    Route::get('orders/finance', [\App\Http\Controllers\OrdersController::class, 'finance'])->name('orders.finance');
    Route::get('orders/collection', [\App\Http\Controllers\OrdersController::class, 'collection'])->name('orders.collection');
    Route::get('orders/it', [\App\Http\Controllers\OrdersController::class, 'IT'])->name('orders.it');
    Route::get('orders/it/new-email', [\App\Http\Controllers\NewEmailController::class, 'index'])->name('new_email.index');
    Route::post('orders/it/new-email', [\App\Http\Controllers\NewEmailController::class, 'store'])->name('new_email.store');

    Route::get('orders/resignations', [\App\Http\Controllers\Back\OrdersResignationsController::class, 'index'])->name('orders.resignations.index');
    Route::get('orders/resignations/create', [\App\Http\Controllers\Back\OrdersResignationsController::class, 'create'])->name('orders.resignations.create');


    // For admins
    Route::prefix('back')->middleware('redirect-trainees-to-dashboard')->name('back.')->group(function() {
        Route::get('/settings', [\App\Http\Controllers\Back\SettingsController::class, 'index'])->name('settings');

        // settings
        Route::put('/settings/app', [\App\Http\Controllers\Back\AppSettingsController::class, 'update'])->name('settings.app.update');
        Route::get('/settings/app', [\App\Http\Controllers\Back\AppSettingsController::class, 'index'])->name('settings.app.index');

        //recruitment
        Route::get('/settings/recruitment-companies', [\App\Http\Controllers\Back\RecruitmentCompaniesController::class, 'index'])->name('settings.recruitment-companies.index');
        Route::get('/settings/recruitment-companies/create', [\App\Http\Controllers\Back\RecruitmentCompaniesController::class, 'create'])->name('settings.recruitment-companies.create');
        Route::post('/settings/recruitment-companies/store', [\App\Http\Controllers\Back\RecruitmentCompaniesController::class, 'store'])->name('settings.recruitment-companies.store');
        Route::delete('/settings/recruitment-companies/{id}', [\App\Http\Controllers\Back\RecruitmentCompaniesController::class, 'destroy'])->name('settings.recruitment-companies.destroy');

        Route::get('/settings/recorded-courses', [\App\Http\Controllers\Back\RecordedCoursesController::class, 'index'])->name('settings.recorded-courses.index');
        Route::get('/settings/recorded-courses/create', [\App\Http\Controllers\Back\RecordedCoursesController::class, 'create'])->name('settings.recorded-courses.create');
        Route::post('/settings/recorded-courses', [\App\Http\Controllers\Back\RecordedCoursesController::class, 'store'])->name('settings.recorded-courses.store');
        Route::get('/settings/recorded-courses/{recorded_course}/edit', [\App\Http\Controllers\Back\RecordedCoursesController::class, 'edit'])->name('settings.recorded-courses.edit');
        Route::put('/settings/recorded-courses/{recorded_course}', [\App\Http\Controllers\Back\RecordedCoursesController::class, 'update'])->name('settings.recorded-courses.update');
        Route::delete('/settings/recorded-courses/{recorded_course}', [\App\Http\Controllers\Back\RecordedCoursesController::class, 'destroy'])->name('settings.recorded-courses.destroy');
        Route::post('/settings/recorded-courses/{recorded_course}/enrollments', [\App\Http\Controllers\Back\RecordedCourseEnrollmentsController::class, 'store'])->name('settings.recorded-courses.enrollments.store');

        Route::get('/settings/global-messages', [\App\Http\Controllers\Back\GlobalMessagesController::class, 'index'])->name('settings.global-messages.index');
        Route::get('/settings/global-messages/create', [\App\Http\Controllers\Back\GlobalMessagesController::class, 'create'])->name('settings.global-messages.create');
        Route::post('/settings/global-messages', [\App\Http\Controllers\Back\GlobalMessagesController::class, 'store'])->name('settings.global-messages.store');
        Route::delete('/settings/global-messages/{id}', [\App\Http\Controllers\Back\GlobalMessagesController::class, 'delete'])->name('settings.global-messages.destroy');

        Route::get('/settings/complaints', [\App\Http\Controllers\Back\ComplaintsSettingsController::class, 'index'])->name('settings.complaints.index');
        Route::put('/settings/complaints/update', [\App\Http\Controllers\Back\ComplaintsSettingsController::class, 'update'])->name('settings.complaints.update');

        Route::get('/settings/resignation', [\App\Http\Controllers\Back\ResignationSettingsController::class, 'index'])->name('settings.resignation.index');
        Route::put('/settings/resignation', [\App\Http\Controllers\Back\ResignationSettingsController::class, 'update'])->name('settings.resignation.update');

        Route::put('/settings/payment/update', [\App\Http\Controllers\Back\PaymentController::class, 'update'])->name('settings.payment.update');
        Route::get('/settings/payment', [\App\Http\Controllers\Back\PaymentController::class, 'index'])->name('settings.payment.index');

        Route::get('/settings/survey-links', [\App\Http\Controllers\Back\SurveyLinksController::class, 'index'])->name('settings.survey-links.index');
        Route::post('/settings/survey-links/store', [\App\Http\Controllers\Back\SurveyLinksController::class, 'store'])->name('settings.survey-links.store');

        Route::get('/settings/disable-website', [\App\Http\Controllers\Back\DisableWebsiteController::class, 'index'])->name('settings.disable-website.index');
        Route::put('/settings/disable-website', [\App\Http\Controllers\Back\DisableWebsiteController::class, 'update'])->name('settings.disable-website.update');
        Route::delete('/settings/roles/{role_id}/users/{user_id}', [\App\Http\Controllers\Back\RolesController::class, 'deleteUser'])->name('settings.roles.users.delete');
        Route::post('/settings/roles/{id}/users/invite', [\App\Http\Controllers\Back\RolesController::class, 'sendInvite'])->name('settings.roles.users.invite.send');
        Route::get('/settings/roles/{id}/users/invite', [\App\Http\Controllers\Back\RolesController::class, 'invite'])->name('settings.roles.users.invite');
        Route::get('/settings/roles/{id}', [\App\Http\Controllers\Back\RolesController::class, 'show'])->name('settings.roles.show');
        Route::get('/settings/roles', [\App\Http\Controllers\Back\RolesController::class, 'index'])->name('settings.roles.index');
        Route::post('/settings/roles/attach-permission', [\App\Http\Controllers\Back\RolesPermissionsController::class, 'attachPermission'])->name('settings.roles.attach-permission');
        Route::post('/settings/roles/detach-permission', [\App\Http\Controllers\Back\RolesPermissionsController::class, 'detachPermission'])->name('settings.roles.detach-permission');
        Route::get('/settings/roles/{id}/permissions', [\App\Http\Controllers\Back\RolesPermissionsController::class, 'index'])->name('settings.roles.permissions.index');


        Route::get('/settings/trainees-applications', [\App\Http\Controllers\Back\SettingsTraineesApplication::class, 'index'])->name('settings.trainees-application');
        Route::get('/settings/trainees-applications/required-files', [\App\Http\Controllers\Back\SettingsTraineesApplication::class, 'requiredFiles'])->name('settings.trainees-application.required-files');
        Route::post('/settings/trainees-applications/required-files', [\App\Http\Controllers\Back\SettingsTraineesApplication::class, 'store'])->name('settings.trainees-application.required-files.store');
        Route::delete('/settings/trainees-applications/required-files/{id}', [\App\Http\Controllers\Back\SettingsTraineesApplication::class, 'delete'])->name('settings.trainees-application.required-files.delete');

        Route::get('companies/{company_id}/aliases', [CompanyAliasesController::class, 'index'])->name('companies.aliases.index');
        Route::post('companies/{company_id}/aliases', [CompanyAliasesController::class, 'store'])->name('companies.aliases.store');
        Route::delete('companies/{company_id}/aliases/{id}', [CompanyAliasesController::class, 'delete'])->name('companies.aliases.delete');

        Route::post('companies/{company_id}/resignations/{id}/approve', [CompanyResignationsController::class, 'approve'])->name('resignations.approve');
        Route::post('companies/{company_id}/resignations/{id}/upload/store', [CompanyResignationsController::class, 'uploadStore'])->name('resignations.upload.store');
        Route::get('companies/{company_id}/resignations/{id}/upload', [CompanyResignationsController::class, 'upload'])->name('resignations.upload');
        Route::resource('companies/{company_id}/resignations', CompanyResignationsController::class);
        Route::get('companies/deleted', [\App\Http\Controllers\Back\CompaniesController::class, 'deleted'])->name('companies.deleted');
        Route::get('companies/{id}/restore', [\App\Http\Controllers\Back\CompaniesController::class, 'restore'])->name('companies.restore');

        Route::get('companies/export', [\App\Http\Controllers\Back\CompaniesController::class, 'export'])->name('companies.export');

        Route::get('companies/exportArchived', [\App\Http\Controllers\Back\CompaniesController::class, 'exportArchived'])->name('companies.export-archived');

        Route::get('companies/{id}/ptcnet', [\App\Http\Controllers\Back\CompaniesController::class, 'markAsPtcNet']);



        Route::resource('companies', \App\Http\Controllers\Back\CompaniesController::class);




        Route::resource('companies.invoices', \App\Http\Controllers\Back\CompanyInvoicesController::class)->only(['create', 'store']);
        Route::get('companies/{company_id}/mails/{id}', [App\Http\Controllers\Webhooks\MailController::class,'viewCompanyMails'])->name('companies.mail');
        //Route::put('user/{id}', [\App\Http\Controllers\Back\UserCompanyController::class, 'index'])->name('user.index');
        //Route::resource('user', \App\Http\Controllers\Back\UserCompanyController::class);
        Route::prefix('companies')->name('companies.')->group(function() {
            Route::post('{company_id}/post-trainees', [\App\Http\Controllers\Back\CompaniesController::class, 'postTrainees'])->name('post-trainees');
            Route::get('{company_id}/trainees/company-trainee-link-audit', [\App\Http\Controllers\Back\CompanyTraineeLinkAuditsController::class, 'index'])->name('trainees.company-trainee-link-audit');
            Route::get('{company_id}/trainees/company-trainee-link-audit/excel', [\App\Http\Controllers\Back\CompanyTraineeLinkAuditsController::class, 'excel'])->name('trainees.company-trainee-link-audit.excel');
            Route::get('{company_id}/trainees/activity-log/excel', [\App\Http\Controllers\Back\CompanyTraineesActivityLogController::class, 'excel'])->name('trainees.activity-log.excel');
            Route::get('{company_id}/trainees/activity-log', [\App\Http\Controllers\Back\CompanyTraineesActivityLogController::class, 'index'])->name('trainees.activity-log');
            Route::get('{company_id}/contracts/{contract_id}/attachments', [\App\Http\Controllers\Back\CompaniesContractsController::class, 'attachments'])->name('contracts.attachments');
            Route::post('{company_id}/contracts/{contract}/attachments/upload', [\App\Http\Controllers\Back\CompaniesContractsController::class, 'storeAttachments'])->name('contracts.attachments.store');
            Route::delete('{company_id}/contracts/{contract}/attachments/delete', [\App\Http\Controllers\Back\CompaniesContractsController::class, 'deleteAttachments'])->name('contracts.attachments.delete');
            Route::get('{company_id}/trainees/excel', [\App\Http\Controllers\Back\CompaniesContractsController::class, 'excel'])->name('trainees.excel');
            Route::resource('{company_id}/contracts', \App\Http\Controllers\Back\CompaniesContractsController::class);
            Route::get('{company_id}/invoices/bulk-pdf', [\App\Http\Controllers\Back\CompanyInvoicesController::class, 'bulkPdf'])->name('invoices.bulk-pdf');
            Route::get('{company_id}/invoices/pdf', [\App\Http\Controllers\Back\CompanyInvoicesController::class, 'pdf'])->name('invoices.pdf');
            Route::get('{company_id}/invoices/change-date-period', [\App\Http\Controllers\Back\CompanyInvoicesController::class, 'datePeriod'])->name('invoices.date-period');
            Route::post('{company_id}/invoices/change-date-period', [\App\Http\Controllers\Back\CompanyInvoicesController::class, 'changeDatePeriod'])->name('invoices.change-date-period');
            Route::get('{company_id}/allowed-users', [CompanyAllowedUsersController::class, 'index'])->name('allowed-users.index');
            Route::get('{company_id}/allowed-users', [CompanyAllowedUsersController::class, 'index'])->name('allowed-users.index');
            Route::post('{company_id}/allowed-users', [CompanyAllowedUsersController::class, 'store'])->name('allowed-users.store');
            Route::delete('{company_id}/allowed-users/{id}', [CompanyAllowedUsersController::class, 'delete'])->name('allowed-users.delete');
        });
        Route::post('company-contracts/attach-instructor', [\App\Http\Controllers\Back\CompaniesContractsController::class, 'attachInstructor'])->name('company-contracts.attach-instructor');
        Route::post('company-contracts/detach-instructor', [\App\Http\Controllers\Back\CompaniesContractsController::class, 'detachInstructor'])->name('company-contracts.detach-instructor');

        Route::get('trainee-groups', [\App\Http\Controllers\Back\TraineesController::class, 'withGroups'])->name('trainee-groups.index');

        Route::get('finance', [\App\Http\Controllers\Back\FinanceController::class, 'index'])->name('finance');

        Route::prefix('finance')->name('finance.')->group(function() {
            Route::get('account-statements/excel', [\App\Http\Controllers\Back\AccountStatementsController::class, 'excel'])->name('account-statements.excel');
            Route::get('account-statements', [\App\Http\Controllers\Back\AccountStatementsController::class, 'index'])->name('account-statements');
            Route::resource('accounts', \App\Http\Controllers\Back\FinancialAccountsController::class);
            Route::get('invoices/short', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'ShortTable'])->name('invoices.short');
            Route::post('invoices/bulk-approve-financial-department', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'bulkApproveFinancialDepartment'])->name('invoices.bulk-approve-finance-department');
            Route::post('invoices/excel/generate', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'generateExcel'])->name('invoices.excel.generate');
            Route::get('invoices/excel', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'excel'])->name('invoices.excel');
            Route::get('invoices/{id}/upload-receipt-form', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'uploadReceiptForm'])->name('invoices.upload-receipt-form');
            Route::post('invoices/{id}/upload-receipt', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'uploadReceipt'])->name('invoices.upload-receipt');
            Route::post('invoices/{id}/mark-as-unpaid-from-chaser', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'markAsUnpaidFromChaser'])->name('invoices.mark-as-unpaid-from-chaser');
            Route::post('invoices/{id}/mark-as-paid-from-chaser', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'markAsPaidFromChaser'])->name('invoices.mark-as-paid-from-chaser');
            Route::get('invoices/{id}/payment-url', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'getPaymentUrl'])->name('invoices.payment-url');
            Route::post('invoices/{id}/approve-payment-receipt/store', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'storePaymentReceiptProof'])->name('invoices.approve-payment-receipt.store');
            Route::get('invoices/{id}/approve-payment-receipt', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'approvePaymentReceipt'])->name('invoices.approve-payment-receipt');
            Route::post('invoices/{id}/reject-payment-receipt', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'rejectPaymentReceipt'])->name('invoices.reject-payment-receipt');
            Route::get('invoices/{id}/pdf', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'pdf'])->name('invoices.pdf');
            Route::put('invoices/{id}/update', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'update'])->name('invoices.update');
            Route::get('invoices/{id}/change-date-period', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'datePeriod'])->name('invoices.date-period');
            Route::post('invoices/{id}/change-date-period', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'changeDatePeriod'])->name('invoices.change-date-period');
            Route::post('invoices/{id}/mark-under-review', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'markUnderReview'])->name('invoices.mark-under-review');
            Route::post('invoices/{id}/mark-archived', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'markArchived'])->name('invoices.mark-as-archived');
            Route::post('invoices/{id}/reset-status', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'resetStatus'])->name('invoices.reset-status');
            Route::resource('invoices', \App\Http\Controllers\Back\FinancialInvoicesController::class);
            Route::post('expected-amount-per-invoice', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'expectedAmountPerInvoice']);
            Route::put('invoices/{invoice_id}/update-amount', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'updateAmount'])->name('invoices.update-amount-direct');
            Route::put('invoices/{invoice_id}/update-company', [\App\Http\Controllers\Back\FinancialInvoicesController::class, 'updateCompany'])->name('invoices.update-company-direct');

        });

        Route::get('trainees/import', [\App\Http\Controllers\Back\TraineesImportController::class, 'index'])->name('trainees.import');
        Route::post('trainees/import', [\App\Http\Controllers\Back\TraineesImportController::class, 'store'])->name('trainees.import.store');

        Route::get('/trainees/{id}/gosi-deleted', [\App\Http\Controllers\Back\TraineesController::class, 'toggleGosiDeleted'])->name('trainees.toggle-gosi-deleted');

        Route::delete('/trainees/{id}/block-list', [\App\Http\Controllers\Back\TraineesController::class, 'deleteFromBlockList'])->name('trainees.delete-from-block-list');
        Route::get('/trainees/{id}/audit', [\App\Http\Controllers\Back\TraineesController::class, 'audit'])->name('trainees.audit');
        Route::get('/trainees/{id}/attendance-sheet', [\App\Http\Controllers\Back\TraineesController::class, 'attendanceSheetPdf'])->name('trainees.admin.attendance-sheet.pdf');
        Route::get('/trainees/{id}/send-private-notification', [\App\Http\Controllers\Back\TraineesController::class, 'sendPrivateNotificationForm'])->name('trainees.private-notifications.create');
        Route::post('/trainees/{id}/send-private-notification/send', [\App\Http\Controllers\Back\TraineesController::class, 'sendPrivateNotification'])->name('trainees.private-notifications.send');
        Route::get('trainees/send-notification', [\App\Http\Controllers\Back\TraineesController::class, 'sendNotificationForm'])->name('trainees.send-notification');
        Route::post('trainees/send-notification/send', [\App\Http\Controllers\Back\TraineesController::class, 'sendNotification'])->name('trainees.send-notification.send');

        Route::post('trainees/{trainee_id}/set-password', [\App\Http\Controllers\Back\TraineesController::class, 'setPassword'])->name('trainees.set-password');

        Route::post('trainees/{trainee_id}/re-send-invitation', [\App\Http\Controllers\Back\TraineesController::class, 'resendInvitation'])->name('trainees.re-send-invitation');
        Route::put('/trainees/{id}/update-deleted-remark', [\App\Http\Controllers\Back\TraineesController::class, 'updatedDeletedRemark'])->name('trainees.update-deleted-remark');

        Route::get('/trainees/groups', [TraineesGroupsController::class, 'index'])->name('trainees.groups.index');
        Route::get('/trainees/link-groups', [\App\Http\Controllers\Back\TraineesController::class, 'linkGroups'])->name('trainees.link-groups');
        Route::post('/trainees/link-groups', [\App\Http\Controllers\Back\TraineesController::class, 'storeLinkGroups'])->name('trainees.link-groups.store');

        // Attendance management of trainee.
        Route::get('trainees/{trainee_id}/warnings', [\App\Http\Controllers\Back\TraineesController::class, 'warnings'])->name('trainees.warnings');
        Route::delete('trainees/{trainee_id}/warnings/all', [\App\Http\Controllers\Back\TraineesController::class, 'warningDeleteAll'])->name('trainees.warnings.delete.all');
        Route::delete('trainees/{trainee_id}/warnings/{id}', [\App\Http\Controllers\Back\TraineesController::class, 'warningDelete'])->name('trainees.warnings.delete');

        // Leave management of trainee
        Route::get('trainees/{trainee_id}/leaves', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'index'])->name('trainees.leaves.index');
        Route::post('trainees/{trainee_id}/leaves', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'store'])->name('trainees.leaves.store');
        Route::get('trainees/{trainee_id}/leaves/create', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'create'])->name('trainees.leaves.create');
        Route::get('trainees/{trainee_id}/leaves/email-defaults', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'getEmailDefaults'])->name('trainees.leaves.email-defaults');
        Route::get('trainees/{trainee_id}/leaves/{id}', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'show'])->name('trainees.leaves.show');
        Route::get('trainees/{trainee_id}/leaves/{id}/edit', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'edit'])->name('trainees.leaves.edit');
        Route::get('trainees/{trainee_id}/leaves/{id}/file-url', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'getFileUrl'])->name('trainees.leaves.file-url');
        Route::put('trainees/{trainee_id}/leaves/{id}', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'update'])->name('trainees.leaves.update');
        Route::delete('trainees/{trainee_id}/leaves/{id}', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'destroy'])->name('trainees.leaves.destroy');
        Route::put('trainees/{trainee_id}/leaves/{id}/status', [\App\Http\Controllers\Back\TraineeLeavesController::class, 'updateStatus'])->name('trainees.leaves.update-status');

        // Invoice management of trainee
        Route::get('trainees/{trainee_id}/invoices', [\App\Http\Controllers\Back\TraineesController::class, 'invoices'])->name('trainees.invoices');

        // Export For Trainees.
        Route::get('trainees/excel/{id}/download', [\App\Http\Controllers\Back\TraineesController::class, 'excelJobDownload'])->name('trainees.excel.job.download');
        Route::get('trainees/excel/{id}', [\App\Http\Controllers\Back\TraineesController::class, 'excelJob'])->name('trainees.excel.job');
        Route::post('trainees/excel', [\App\Http\Controllers\Back\TraineesController::class, 'excel'])->name('trainees.excel');

        Route::post('certificates/import/upload', [\App\Http\Controllers\Back\CertificatesController::class, 'upload'])->name('certificates.import.upload');
        Route::get('certificates/import', [\App\Http\Controllers\Back\CertificatesController::class, 'import'])->name('certificates.import');
        Route::post('certificates/import/{id}/issue', [\App\Http\Controllers\Back\CertificatesController::class, 'issue'])->name('certificates.import.issue');
        Route::get('certificates/import/{id}', [\App\Http\Controllers\Back\CertificatesController::class, 'job'])->name('certificates.import.job');

        // UK Certificates
        Route::get('uk-certificates', [\App\Http\Controllers\Back\UkCertificatesController::class, 'index'])->name('uk-certificates.index');
        Route::post('uk-certificates/upload-zip', [\App\Http\Controllers\Back\UkCertificatesController::class, 'uploadZip'])->name('uk-certificates.upload-zip');
        Route::post('uk-certificates/google-drive', [\App\Http\Controllers\Back\UkCertificatesController::class, 'processGoogleDrive'])->name('uk-certificates.google-drive');
        Route::get('uk-certificates/{importId}', [\App\Http\Controllers\Back\UkCertificatesController::class, 'showProcessing'])->name('uk-certificates.processing');
        Route::get('uk-certificates/{importId}/status', [\App\Http\Controllers\Back\UkCertificatesController::class, 'getProcessingStatus'])->name('uk-certificates.status');
        Route::post('uk-certificates/finalize', [\App\Http\Controllers\Back\UkCertificatesController::class, 'finalizeImport'])->name('uk-certificates.finalize');
        Route::get('uk-certificates/{row_id}/download', [\App\Http\Controllers\Back\UkCertificatesController::class, 'downloadCertificate'])->name('uk-certificates.download');
        Route::get('uk-certificates/{importId}/delivery-report', [\App\Http\Controllers\Back\UkCertificatesController::class, 'downloadDeliveryReport'])->name('uk-certificates.delivery-report');
        Route::delete('uk-certificates/{importId}', [\App\Http\Controllers\Back\UkCertificatesController::class, 'delete'])->name('uk-certificates.delete');

        // Trainees
        Route::get('trainees/{id}/download-all-files', [\App\Http\Controllers\Back\TraineesController::class, 'downloadAllFiles'])->name('trainees.download-all-files');
        Route::get('trainees/fixed-training-costs', [\App\Http\Controllers\Back\TraineesController::class, 'indexFixedTrainingCosts'])->name('trainees.fixed-training-costs.index');
        Route::put('trainees/{trainee_id}/fixed-training-costs', [\App\Http\Controllers\Back\TraineeFixedTrainingCostsController::class, 'update'])->name('trainees.fixed-training-costs.update');
        Route::get('trainees/{trainee_id}/fixed-training-costs', [\App\Http\Controllers\Back\TraineeFixedTrainingCostsController::class, 'index'])->name('trainees.fixed-training-costs');


        // my comment
        Route::resource('trainees/{trainee_id}/files', \App\Http\Controllers\Back\TraineesFilesController::class, ['as' => 'trainees']);
        Route::resource('companies/{company_id}/files', \App\Http\Controllers\Back\CompaniesFilesController::class, ['as' => 'companies']);

        Route::get('trainees/block-list', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'index'])->name('trainees.block-list.index');
        Route::get('trainees/block-list/create', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'create'])->name('trainees.block-list.create');
        Route::post('trainees/block-list', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'store'])->name('trainees.block-list.store');
        Route::delete('trainees/block-list/{id}', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'destroy'])->name('trainees.block-list.delete');
        Route::get('trainees/block-list/import', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'import'])->name('trainees.block-list.import');
        Route::post('trainees/block-list/import', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'importCsv'])->name('trainees.block-list.import-csv');
        Route::get('trainees/block-list/download', [\App\Http\Controllers\Back\TraineesBlockListController::class, 'download'])->name('trainees.block-list.download');
        Route::get('trainees/archived/excel/{id}/download', [\App\Http\Controllers\Back\TraineesController::class, 'excelJobDownload'])->name('trainees.archived.excel.job.download');
        Route::get('trainees/archived/excel/{id}', [\App\Http\Controllers\Back\TraineesController::class, 'excelJob'])->name('trainees.archived.excel.job');
        Route::post('trainees/archived/excel', [\App\Http\Controllers\Back\TraineesController::class, 'archivedExcel'])->name('trainees.archived.excel');
        Route::post('trainees/{trainee_id}/approve-user', [\App\Http\Controllers\Back\TraineesController::class, 'approveUser'])->name('trainees.approve-user');
        Route::post('trainees/{trainee_id}/create-user', [\App\Http\Controllers\Back\TraineesController::class, 'createUser'])->name('trainees.create-user');
        Route::post('trainees/assign-instructor', [\App\Http\Controllers\Back\TraineesController::class, 'assignInstructor'])->name('trainees.assign-instructor');
        Route::post('trainees/{trainee_id}/attachments/identity', [\App\Http\Controllers\Back\TraineesController::class, 'storeIdentity'])->name('trainees.attachments.identity');
        Route::delete('trainees/{trainee_id}/attachments/identity', [\App\Http\Controllers\Back\TraineesController::class, 'deleteIdentity'])->name('trainees.attachments.identity.destroy');


        // my comment
        Route::post('trainees/{trainee_id}/attachments/qualification', [\App\Http\Controllers\Back\TraineesController::class, 'storeQualification'])->name('trainees.attachments.qualification');

        Route::delete('trainees/{trainee_id}/attachments/qualification', [\App\Http\Controllers\Back\TraineesController::class, 'deleteQualification'])->name('trainees.attachments.qualification.destroy');
        Route::post('trainees/{trainee_id}/attachments/bank-account', [\App\Http\Controllers\Back\TraineesController::class, 'storeBankAccount'])->name('trainees.attachments.bank-account');
        Route::delete('trainees/{trainee_id}/attachments/bank-account', [\App\Http\Controllers\Back\TraineesController::class, 'deleteBankAccount'])->name('trainees.attachments.bank-account.destroy');
        Route::post('trainees/{trainee_id}/attachments/national-address', [\App\Http\Controllers\Back\TraineesController::class, 'storeNationalAddress'])->name('trainees.attachments.national-address');
        Route::delete('trainees/{trainee_id}/attachments/national-address', [\App\Http\Controllers\Back\TraineesController::class, 'deleteNationalAddress'])->name('trainees.attachments.national-address.destroy');
        Route::post('trainees/{trainee_id}/attachments/cv', [\App\Http\Controllers\Back\TraineesController::class, 'storeCv'])->name('trainees.attachments.cv');
        Route::delete('trainees/{trainee_id}/attachments/cv', [\App\Http\Controllers\Back\TraineesController::class, 'deleteCv'])->name('trainees.attachments.cv.destroy');
        Route::post('trainees/{trainee_id}/attachments/gosi-certificate', [\App\Http\Controllers\Back\TraineesController::class, 'storeGosiCertificate'])->name('trainees.attachments.gosi-certificate');
        Route::delete('trainees/{trainee_id}/attachments/gosi-certificate', [\App\Http\Controllers\Back\TraineesController::class, 'deleteGosiCertificate'])->name('trainees.attachments.gosi-certificate.destroy');
        Route::post('trainees/{trainee_id}/attachments/qiwa-contract', [\App\Http\Controllers\Back\TraineesController::class, 'storeQiwaContract'])->name('trainees.attachments.qiwa-contract');
        Route::delete('trainees/{trainee_id}/attachments/qiwa-contract', [\App\Http\Controllers\Back\TraineesController::class, 'deleteQiwaContract'])->name('trainees.attachments.qiwa-contract.destroy');
        Route::get('trainees/{trainee_id}/check-optional-documents', [\App\Http\Controllers\Back\TraineesController::class, 'checkOptionalDocumentsStatus'])->name('trainees.check-optional-documents');
        
        // Custom certificates
        Route::get('trainees/{trainee_id}/custom-certificates/create', [\App\Http\Controllers\Back\TraineesController::class, 'createCustomCertificate'])->name('trainees.custom-certificates.create');
        Route::post('trainees/{trainee_id}/custom-certificates', [\App\Http\Controllers\Back\TraineesController::class, 'storeCustomCertificate'])->name('trainees.custom-certificates.store');
        Route::get('trainees/{trainee_id}/custom-certificates/{id}/file-url', [\App\Http\Controllers\Back\TraineesController::class, 'getCustomCertificateFileUrl'])->name('trainees.custom-certificates.file-url');
        Route::get('trainees/{trainee_id}/custom-certificates/{id}/file', [\App\Http\Controllers\Back\TraineesController::class, 'streamCustomCertificateFile'])->name('trainees.custom-certificates.stream-file');
        
        Route::get('trainees/{trainee_id}/block', [\App\Http\Controllers\Back\TraineesController::class, 'blockView'])->name('trainees.block');
        Route::post('trainees/{trainee_id}/block', [\App\Http\Controllers\Back\TraineesController::class, 'block'])->name('trainees.block.store');
        Route::post('trainees/{trainee_id}/suspend', [\App\Http\Controllers\Back\TraineesController::class, 'suspend'])->name('trainees.suspend.store');
        Route::get('trainees/{trainee_id}/suspend/create', [\App\Http\Controllers\Back\TraineesController::class, 'suspendCreate'])->name('trainees.suspend.create');
        Route::post('trainees/suspend', [\App\Http\Controllers\Back\TraineesController::class, 'suspendAll'])->name('trainees.suspend.all');
        Route::post('trainees/unblock', [\App\Http\Controllers\Back\TraineesController::class, 'unBlockAll'])->name('trainees.unblock.all');
        Route::get('trainees/suspend/{trainee_block_list_id}/edit', [\App\Http\Controllers\Back\TraineesController::class, 'suspendEdit'])->name('trainees.suspend.edit');
        Route::put('trainees/suspend/{trainee_block_list_id}/update', [\App\Http\Controllers\Back\TraineesController::class, 'suspendUpdate'])->name('trainees.suspend.update');
        Route::get('trainees/blocked/show/{trainee_id}', [\App\Http\Controllers\Back\TraineesController::class, 'showBlocked'])->name('trainees.show.blocked');
        Route::post('trainees/blocked/show/{trainee_id}', [\App\Http\Controllers\Back\TraineesController::class, 'unblock'])->name('trainees.unblock');
        Route::get('trainees/archived', [\App\Http\Controllers\Back\TraineesController::class, 'indexArchived'])->name('trainees.index.archived');
        Route::get('trainees/engineers', [\App\Http\Controllers\Back\TraineesController::class, 'indexEngineers'])->name('trainees.engineers.index');
        Route::resource('trainees', \App\Http\Controllers\Back\TraineesController::class);
        Route::resource('trainees.invoices', \App\Http\Controllers\Back\TraineeInvoicesController::class)->only(['create', 'store']);
        Route::post('trainees/{trainee_id}/refresh-invoices', [\App\Http\Controllers\Back\TraineesController::class, 'refreshInvoices'])->name('trainees.refresh-invoices');
        Route::get('candidates', [\App\Http\Controllers\Back\CandidatesController::class, 'index'])->name('candidates.index');
        Route::post('trainees/{trainee_id}/suspendSelectedTrainees', [\App\Http\Controllers\Back\TraineesController::class, 'suspendSelectedTrainees'])->name('trainees.suspend.selected.trainees');
        // Export trainees
        Route::get('candidates/excel/{id}/download', [\App\Http\Controllers\Back\TraineesController::class, 'excelJobDownload'])->name('candidates.excel.job.download');
        Route::get('candidates/excel/{id}', [\App\Http\Controllers\Back\TraineesController::class, 'excelJob'])->name('candidates.excel.job');
        Route::post('candidates/excel', [\App\Http\Controllers\Back\CandidatesController::class, 'excel'])->name('candidates.excel');

        Route::get('instructors/{instructor_id}/zoom-account', [ZoomAccountController::class, 'index'])->name('instructors.zoom-account.index');
        Route::put('instructors/{instructor_id}/zoom-account', [ZoomAccountController::class, 'update'])->name('instructors.zoom-account.update');

        Route::post('instructors/{instructor_id}/approve-user', [\App\Http\Controllers\Back\InstructorsController::class, 'approveUser'])->name('instructors.approve-user');
        Route::post('instructors/{instructor_id}/create-user', [\App\Http\Controllers\Back\InstructorsController::class, 'createUser'])->name('instructors.create-user');
        Route::post('instructors/{instructor_id}/attachments/cv-full', [\App\Http\Controllers\Back\InstructorsController::class, 'storeCvFull'])->name('instructors.attachments.cv-full');
        Route::delete('instructors/{instructor_id}/attachments/cv-full', [\App\Http\Controllers\Back\InstructorsController::class, 'deleteCvFull'])->name('instructors.attachments.cv-full.destroy');
        Route::post('instructors/{instructor_id}/attachments/cv-summary', [\App\Http\Controllers\Back\InstructorsController::class, 'storeCvSummary'])->name('instructors.attachments.cv-summary');
        Route::delete('instructors/{instructor_id}/attachments/cv-summary', [\App\Http\Controllers\Back\InstructorsController::class, 'deleteCvSummary'])->name('instructors.attachments.cv-summary.destroy');
        Route::delete('instructors/{instructor_id}/block', [\App\Http\Controllers\Back\InstructorsController::class, 'block'])->name('instructors.block');
        Route::get('instructors/blocked/show/{instructor_id}', [\App\Http\Controllers\Back\InstructorsController::class, 'showBlocked'])->name('instructors.show.blocked');
        Route::post('instructors/blocked/show/{instructor_id}', [\App\Http\Controllers\Back\InstructorsController::class, 'unblock'])->name('instructors.unblock');
        Route::resource('instructors', \App\Http\Controllers\Back\InstructorsController::class);

        Route::get('/courses/{course_id}/course-batches/{course_batch_id}/course-batch-sessions/{course_batch_session}/start', [\App\Http\Controllers\Back\CourseBatchSessionsController::class, 'show'])->name('course-batch-session.start');
        Route::resource('courses/{course_id}/course-batches/{course_batch_id}/course-batch-sessions', \App\Http\Controllers\Back\CourseBatchSessionsController::class);

        Route::resource('courses/{course_id}/course-batches', \App\Http\Controllers\Back\CourseBatchesController::class);

        Route::get('search', [\App\Http\Controllers\Back\SiteSearchController::class, 'search']);

        Route::post('courses/{course_id}/approve', [\App\Http\Controllers\Back\CoursesController::class, 'approve'])->name('courses.approve');
        Route::post('courses/{course_id}/training-package', [\App\Http\Controllers\Back\CoursesController::class, 'storeTrainingPackage'])->name('courses.training-package');
        Route::delete('courses/{course_id}/training-package', [\App\Http\Controllers\Back\CoursesController::class, 'deleteTrainingPackage'])->name('courses.training-package.destroy');
        Route::get('courses/today', [\App\Http\Controllers\Back\CoursesController::class, 'indexToday'])->name('courses.today');
        Route::resource('courses', \App\Http\Controllers\Back\CoursesController::class);

        Route::prefix('reports')->group(function() {

            Route::get('/', [\App\Http\Controllers\Back\ReportsController::class, 'index'])->name('reports.index');

            Route::post('course-attendances/generate', [\App\Http\Controllers\Back\ReportsController::class, 'generateCourseAttendanceReport'])->name('reports.course-attendances.generate');
            Route::get('course-attendances', [\App\Http\Controllers\Back\ReportsController::class, 'formCourseAttendanceReport'])->name('reports.course-attendances.index');

            Route::post('bulk-course-attendances/generate', [\App\Http\Controllers\Back\ReportsController::class, 'generateBulkCourseAttendanceReport'])->name('reports.bulk-course-attendances.generate');
            Route::get('bulk-course-attendances', [\App\Http\Controllers\Back\ReportsController::class, 'formBulkCourseAttendanceReport'])->name('reports.bulk-course-attendances.index');

            Route::get('company-certificates', [\App\Http\Controllers\Back\ReportsController::class, 'formCompanyCertificateseReport'])->name('reports.company-certificates.index');

            Route::get('contracts', [\App\Http\Controllers\Back\ReportsController::class, 'formContractsReport'])->name('reports.contracts.index');

            Route::get('trainees-witout-invoices', [\App\Http\Controllers\Back\ReportsController::class, 'formTraineesWithoutInvoicesReport'])->name('reports.trainees-witout-invoices.index');
            //trainees without invoices export
            Route::get('trainees-witout-invoices/export', [\App\Http\Controllers\Back\ReportsController::class, 'export'])->name('reports.trainees-witout-invoices.export');

            // Company invoices summary report routes
            Route::get('company-invoices-summary', [\App\Http\Controllers\Back\ReportsController::class, 'formCompanyInvoicesSummaryReport'])->name('reports.company-invoices-summary.index');
            Route::get('company-invoices-summary/export', [\App\Http\Controllers\Back\ReportsController::class, 'exportCompanyInvoicesSummary'])->name('reports.company-invoices-summary.export');

            // Companies paid invoices 2024 report routes
            Route::get('companies-paid-invoices-2024/export', [\App\Http\Controllers\Back\ReportsController::class, 'exportCompaniesPaidInvoices2024'])->name('reports.companies-paid-invoices-2024.export');
            Route::get('companies-paid-invoices-2024/job/{id}', [\App\Http\Controllers\Back\ReportsController::class, 'companiesPaidInvoices2024Job'])->name('reports.companies-paid-invoices-2024.job');
            Route::get('companies-paid-invoices-2024/job/{id}/download', [\App\Http\Controllers\Back\ReportsController::class, 'companiesPaidInvoices2024JobDownload'])->name('reports.companies-paid-invoices-2024.job.download');

            // Certificates issued report routes
            Route::get('certificates-issued', [\App\Http\Controllers\Back\ReportsController::class, 'formCertificatesIssuedReport'])->name('reports.certificates-issued.index');
            Route::post('certificates-issued/export', [\App\Http\Controllers\Back\ReportsController::class, 'exportCertificatesIssued'])->name('reports.certificates-issued.export');

            // Trainees report routes
            Route::get('trainees', [\App\Http\Controllers\Back\TraineesReportController::class, 'index'])->name('reports.trainees.index');
            Route::post('trainees/generate', [\App\Http\Controllers\Back\TraineesReportController::class, 'generate'])->name('reports.trainees.generate');





            Route::post('contracts/generate', [\App\Http\Controllers\Back\ReportsController::class, 'generateContractsReport'])->name('reports.contracts.generate');

            Route::post('company-attendance/{report_id}/add-trainee', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'addTrainee'])->name('reports.company-attendance.add-trainee');
            Route::delete('company-attendance/{report_id}/remove-email/{id}', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'removeEmail'])->name('reports.company-attendance.remove-email');
            Route::delete('company-attendance/{report_id}/clear-all-emails', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'clearAllEmails'])->name('reports.company-attendance.clear-all-emails');
            Route::post('company-attendance/{report_id}/add-email-in-bulk', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'addEmailInBulk'])->name('reports.company-attendance.add-email-in-bulk');
            Route::post('company-attendance/{report_id}/add-email', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'addEmail'])->name('reports.company-attendance.add-email');
            Route::get('company-attendance/{report_id}/toggle-select', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'toggleSelect'])->name('reports.company-attendance.toggle-select');
            Route::get('company-attendance/{report_id}/excel', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'excel'])->name('reports.company-attendance.excel');
            Route::post('company-attendance/{report_id}/trainees/{trainee_id}', [\App\Http\Controllers\Back\CompanyAttendanceReportTraineesController::class, 'update'])->name('reports.company-attendance.trainees.update');
            Route::get('company-attendance/{report_id}/edit', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'edit'])->name('reports.company-attendance.edit');
            Route::post('company-attendance/{id}/clone', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'clone'])->name('reports.company-attendance.clone');
            Route::post('company-attendance/{id}/approve', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'approve'])->name('reports.company-attendance.approve');

            Route::get('company-attendance/{id}/preview', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'preview'])->name('reports.company-attendance.preview');

            Route::post('company-attendance/{id}/send', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'send'])->name('reports.company-attendance.send');
            Route::post('company-attendance/send-report/download', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'sendReportDownload'])->name('reports.company-attendance.send-report.download');
            Route::get('company-attendance/send-report', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'sendReport'])->name('reports.company-attendance.send-report');
            Route::put('company-attendance/{id}', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'update'])->name('reports.company-attendance.update');
            Route::patch('company-attendance/{id}/template', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'updateTemplate'])->name('reports.company-attendance.update-template');
            Route::post('company-attendance/{id}/attach', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'attach'])->name('reports.company-attendance.attach');
            Route::post('company-attendance/{id}/detach', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'detach'])->name('reports.company-attendance.detach');
            Route::delete('company-attendance/{id}', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'destroy'])->name('reports.company-attendance.destroy');
            Route::post('company-attendance', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'store'])->name('reports.company-attendance.store');
            Route::get('company-attendance/create', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'create'])->name('reports.company-attendance.create');
            Route::get('company-attendance', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'index'])->name('reports.company-attendance.index');

            Route::get('company-attendance/{id}', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'show'])->name('reports.company-attendance.show');


            Route::get('company-attendance/{id}/trainee/{trainee_id}', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'individual'])->name('reports.company-attendance.individual');
            Route::get('company-attendance/{id}/trainee/{trainee_id}/pdf', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'individualPdf'])->name('reports.company-attendance.individual.pdf');
            Route::post('company-attendance/{id}/trainee/{trainee_id}/email', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'individualEmail'])->name('reports.company-attendance.individual.email');

        });
    });

    // For instructors
    Route::prefix('teaching')->middleware('redirect-trainees-to-dashboard')->name('teaching.')->group(function() {
        Route::get('/', [\App\Http\Controllers\Teaching\TeachingController::class, 'index'])->name('index');
        Route::resource('courses', \App\Http\Controllers\Teaching\CoursesController::class);

        // WIP
        Route::put('/course-batch-sessions/{course_batch_session_id}/attendance/trainee', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'updateTraineeAttendance'])->name('course-batch-sessions.attendance.trainee.status');

        // Preparing attendance
        Route::put('/attendance-report-record/{attendance_report_record_id}', [\App\Http\Controllers\AttendanceReportRecordsController::class, 'update'])->name('attendance-report-records.update');
        Route::get('/course-batch-sessions/{course_batch_session_id}/attendance-reports', [\App\Http\Controllers\AttendanceReportsController::class, 'show'])->name('course-batch-sessions.attendance-reports.show');
        Route::post('/attendance-reports/{attendance_report_id}/close-attendance', [\App\Http\Controllers\AttendanceReportsController::class, 'close'])->name('course-batch-sessions.attendance-reports.close-attendance');
        Route::get('/attendance-reports/{attendance_report_id}', [\App\Http\Controllers\AttendanceReportsController::class, 'showReport'])->name('course-batch-sessions.attendance-reports.show-report');
        Route::get('/attendance-reports/{attendance_report_id}/attendances', [\App\Http\Controllers\AttendanceReportsController::class, 'attendances'])->name('attendance-reports.attendances');
        Route::get('/attendance-reports/{attendance_report_id}/confirm', [\App\Http\Controllers\AttendanceReportsController::class, 'confirm'])->name('attendance-reports.confirm');
        Route::post('/attendance-reports/{attendance_report_id}/approve', [\App\Http\Controllers\AttendanceReportsController::class, 'approve'])->name('attendance-reports.approve');
        Route::get('/attendance-reports/{attendance_report_id}/excel', [\App\Http\Controllers\AttendanceReportsController::class, 'excel'])->name('attendance-reports.excel');

        Route::get('/course-batch-sessions/{course_batch_session_id}/attendance/excel', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'excel'])->name('course-batch-sessions.attendance.excel');
        Route::get('/course-batch-sessions/{course_batch_session_id}/attendance/export', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'attendingExcel'])->name('course-batch-sessions.attendance.export');
        Route::post('/course-batch-sessions/{course_batch_session_id}/confirm/approve', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'approve'])->name('course-batch-sessions.attendance.confirm.approve');
        Route::get('/course-batch-sessions/{course_batch_session_id}/confirm', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'confirm'])->name('course-batch-sessions.attendance.confirm');
        Route::get('/course-batch-sessions/{course_batch_session_id}/attendance', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'index'])->name('course-batch-sessions.attendance.index');
        Route::put('/course-batch-sessions/{course_batch_session_id}/attendance/{id}', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'update'])->name('course-batch-sessions.attendances.update');

        Route::post('/course-batch-sessions/attendance', [\App\Http\Controllers\CourseBatchSessionsAttendanceController::class, 'store'])->name('course-batch-sessions.attendance.store');

        Route::post('/trainee-groups/{trainee_group_id}/trainees/{id}/send-message', [\App\Http\Controllers\Teaching\TraineeGroupTraineesController::class, 'sendMessage'])->name('trainee-groups.trainees.send-message');

        // These two don't exist?
        //Route::get('/trainee-groups/{trainee_group_id}/trainees/{id}', [\App\Http\Controllers\Teaching\TraineeGroupDashboardController::class, 'show'])->name('trainee-groups.trainees.show');
        //Route::get('/trainee-groups/{trainee_group_id}/trainees', [\App\Http\Controllers\Teaching\TraineeGroupTraineesController::class, 'index'])->name('trainee-groups.trainees.index');

        Route::get('/trainee-groups/{trainee_group_id}/announcements/create', [\App\Http\Controllers\Teaching\TraineeGroupsController::class, 'createAnnouncement'])->name('trainee-groups.announcements.create');
        Route::post('/trainee-groups/{trainee_group_id}/announcements/send', [\App\Http\Controllers\Teaching\TraineeGroupsController::class, 'sendAnnouncement'])->name('trainee-groups.announcements.send');
        Route::get('/trainee-groups', [\App\Http\Controllers\Teaching\TraineeGroupsController::class, 'index'])->name('trainee-groups.index');
    });

    // For trainees
    Route::prefix('trainees')->name('trainees.')->group(function() {

        Route::get('attendance-sheet', [\App\Http\Controllers\Trainees\AttendanceSheetController::class, 'index'])->name('attendance-sheet.index');
        Route::get('training-packages', [\App\Http\Controllers\Trainees\TrainingPackagesController::class, 'index'])->name('training-packages.index');

        Route::get('training-plan', [\App\Http\Controllers\Trainees\TrainingPlanController::class, 'index'])->name('training-plan.index');

        Route::get('/courses/{course_id}/certificate', [\App\Http\Controllers\Trainees\CoursesController::class, 'generateCertificate'])->name('courses.generate-certificate');
        Route::get('/courses/{course_id}/training-package', [\App\Http\Controllers\Teaching\CoursesController::class, 'trainingPackage'])->name('courses.training-package');
        Route::resource('courses', \App\Http\Controllers\Trainees\CoursesController::class);

        Route::get('/courses/{course_id}/course-batches/{course_batch_id}/course-batch-sessions/{course_batch_session}', [\App\Http\Controllers\Trainees\CourseBatchSessionsController::class, 'show'])->name('course-batch-session.show');

        Route::get('payment/options', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'showOptions'])->name('payment.options');
        Route::get('payment/choose-invoice', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'chooseInvoice'])->name('payment.choose-invoice');
        Route::get('payment/objection', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'objectionOfAmount'])->name('payment.objection');
        Route::post('payment/receipt/store', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'storeReceipt'])->name('payment.upload-receipt.store');
        Route::get('payment/receipt', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'uploadReceipt'])->name('payment.upload-receipt');

        Route::get('payment/card', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'showPaymentForm'])
            ->name('payment.card');
        Route::post('override-payment/card', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'changeInvoiceAmountRedirectToPaymentGateway'])
            ->name('override-payment.card');

        Route::get('payment/card/charge-payment', [\App\Http\Controllers\Trainees\Payment\PaymentCardController::class, 'chargePayment'])
            ->name('payment.card.charge');

        // Resignation request routes
        Route::get('resignation-request', [\App\Http\Controllers\Trainees\ResignationRequestController::class, 'index'])->name('resignation-request.index');
        Route::post('resignation-request', [\App\Http\Controllers\Trainees\ResignationRequestController::class, 'store'])->name('resignation-request.store');
    });
});

Route::middleware(['auth:sanctum'])->group(function() {
    // Moved API call here because I don't have time to figure the token stuff out that work under /api/ url space.
    Route::post('/api/instructors/uploadcv', [\App\Http\Controllers\Back\InstructorsController::class, 'storeCvFromApplication'])->name('api.register.instructors.upload-cv');
    Route::post('/api/trainees/uploadcv', [\App\Http\Controllers\Back\TraineesController::class, 'storeCvFromApplication'])->name('api.register.trainees.upload-cv');
});

// Some routes for nowyer
Route::get('sm3', function() { return redirect()->to('https://linktr.ee/ptcksa'); }); // used for events, linktree account registered by billing@ptc-ksa.net


Route::get('/logo-files', [\App\Http\Controllers\LogoFilesController::class, 'index']);
//comment from shafiq

//route to export attendance sheet for specefic group
Route::get('/attendance/export-by-group/{courseBatch}', [\App\Http\Controllers\AttendanceReportsController::class, 'exportAttendanceReportByGroup'])
    ->name('attendance.export-by-group');



 Route::get('/company-attendance-reports/approve/{id}', [\App\Http\Controllers\Back\CompanyAttendanceReportController::class, 'emailApprove']);

 Route::post('company-certificates/generate', [\App\Http\Controllers\Back\ReportsController::class, 'formCompanyCertificateseGenerateReport'])
 ->name('reports.company-certificates.generate');


 Route::get('export-some-trainees',function(){
   return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ExportSomeTraineesFromGada(),'trainees.xlsx');
});

Route::get('export-candidates', function () {
    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\ExportSomeTraineesFromGada(),
        'candidates.xlsx'
    );
})->name('candidates.export.excel');

Route::get('export-trainee-audits', function(\Illuminate\Http\Request $request) {
    $userId = $request->get('user_id', '53a66c49-f6b4-4dee-952f-71f4d096a9ba');
    $field = $request->get('field');
    $weeks = $request->get('weeks', 2);
    
    $fileName = 'trainee-audits-' . now()->format('Y-m-d-H-i-s') . '.xlsx';
    
    return \Maatwebsite\Excel\Facades\Excel::download(
        new \App\Exports\Back\TraineeAuditExport($userId, $field, $weeks),
        $fileName
    );
})->name('export.trainee-audits');



Route::post('/send-contract', [\App\Http\Controllers\ZohoSignController::class, 'sendContract']);

Route::get('/test-zoho', [\App\Http\Controllers\ZohoSignController::class, 'test']);

Route::post('/send-embedded-contract', [\App\Http\Controllers\ZohoSignController::class, 'sendEmbeddedContract']);

Route::get('/zoho/view-contract', [\App\Http\Controllers\ZohoSignController::class, 'viewContract'])->name('zoho.view-contract');

Route::get('/zoho/check-contract-status', [\App\Http\Controllers\ZohoSignController::class, 'checkContractStatus'])->name('zoho.check-contract-status');

Route::get('/contract-must-sign', [\App\Http\Controllers\ZohoSignController::class, 'contractMustSign'])->name('contract-must-sign');
Route::get('/contract-cancel', [\App\Http\Controllers\ZohoSignController::class, 'cancelContract'])->name('contract-cancel');




Route::get('/send-test-email', function () {
    $data = ['message' => 'This is a test email from Mailgun.'];

    Mail::send([], [], function ($message) use ($data) {
        $message->to('ebrahim.hosny@hadaf-hq.com')
                ->subject('Test Email')
                ->setBody($data['message']);
    });

    return 'Test email sent successfully!';
});






Route::middleware(['auth:sanctum'])->group(function() {
    Route::get('/contract-guides',[\App\Http\Controllers\Back\TraineesController::class,'contractGuides'])->name('contract-guides');
    Route::post('reports/deleted-trainees/generate', [\App\Http\Controllers\Back\DeletedTraineesReportController::class, 'generate'])->name('back.reports.deleted-trainees.generate');
    Route::get('reports/deleted-trainees', [\App\Http\Controllers\Back\DeletedTraineesReportController::class, 'index'])->name('back.reports.deleted-trainees.index');
});

// Magic link login
Route::post('/login/magic-link', [\App\Http\Controllers\Auth\MagicLinkController::class, 'send'])->name('login.magic-link.send');
Route::get('/login/magic', [\App\Http\Controllers\Auth\MagicLinkController::class, 'login'])->name('login.magic-link.consume');
Route::get('/login/magic-link/sent', [\App\Http\Controllers\Auth\MagicLinkController::class, 'sent'])->name('login.magic-link.sent');
