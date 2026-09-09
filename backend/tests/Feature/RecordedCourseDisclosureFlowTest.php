<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendRecordedCourseAccessLinkJob;
use App\Jobs\SendRecordedCourseCertificateJob;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\RecordedCourseLesson;
use App\Models\Back\Trainee;
use App\Models\User;
use App\Services\RolesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecordedCourseDisclosureFlowTest extends TestCase
{
    private function makeAdminWithTeam(): User
    {
        $admin = User::factory()->create();
        $team = $admin->ownedTeams()->create([
            'name' => 'Test Team RC Disclosure',
            'personal_team' => false,
        ]);
        app(RolesService::class)->seedRolesToTeam($team);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        return $admin->fresh();
    }

    /**
     * @return array{0: RecordedCourse, 1: RecordedCourseLesson, 2: RecordedCourseLesson}
     */
    private function createCourseTwoLessons(User $admin): array
    {
        $response = $this->actingAs($admin)->post(
            route('back.settings.recorded-courses.store'),
            [
                'name_ar' => 'دورة إفصاح',
                'name_en' => 'Disclosure Course',
                'description' => 'D',
                'unlock_delay_hours' => 1,
                'allowed_weekdays' => [0, 1, 2, 3, 4, 5, 6],
                'lessons' => [
                    ['title_ar' => 'L1', 'title_en' => 'L1'],
                    ['title_ar' => 'L2', 'title_en' => 'L2'],
                ],
            ]
        );

        $course = RecordedCourse::query()->where('name_en', 'Disclosure Course')->first();
        $this->assertNotNull($course, 'Course was not created. Session: '.json_encode(session()->all()));
        $response->assertRedirect(route('back.settings.recorded-courses.show', $course));

        $lessons = $course->lessons()->orderBy('sort_order')->get();

        return [$course->fresh(), $lessons[0], $lessons[1]];
    }

    private function createTrainee(User $admin): Trainee
    {
        $teamId = $admin->current_team_id;
        $user = User::factory()->create(['current_team_id' => $teamId]);
        $trainee = Trainee::factory()->create([
            'team_id' => $teamId,
            'email' => $user->email,
        ]);
        $trainee->forceFill([
            'user_id' => $user->id,
            'skip_uploading_id' => true,
        ])->save();

        return $trainee->fresh();
    }

    public function test_invalid_access_token_returns_404(): void
    {
        $this->get(route('recorded-courses.public.show', ['token' => 'notarealtoken123']))
            ->assertNotFound();
    }

    public function test_public_access_requires_check_in_before_stream_and_unlock(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course, $lesson1] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        $token = $enrollment->fresh()->access_token;
        $this->assertNotEmpty($token);

        $this->get(route('recorded-courses.public.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('تسجيل الحضور');

        $this->post(route('recorded-courses.public.unlock', ['token' => $token]))
            ->assertForbidden();

        $this->get(route('recorded-courses.public.stream', ['token' => $token, 'lesson' => $lesson1->id]))
            ->assertForbidden();

        $this->post(route('recorded-courses.public.check-in', ['token' => $token]))
            ->assertRedirect(route('recorded-courses.public.show', ['token' => $token]));

        $this->assertNotNull($enrollment->fresh()->checked_in_at);

        Carbon::setTestNow(Carbon::parse('2026-05-09 10:00:00', config('app.timezone')));

        $this->post(route('recorded-courses.public.unlock', ['token' => $token]))
            ->assertRedirect(route('recorded-courses.public.show', ['token' => $token]));

        $this->assertDatabaseHas('recorded_course_lesson_progress', [
            'recorded_course_enrollment_id' => $enrollment->id,
            'recorded_course_lesson_id' => $lesson1->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_completing_all_lessons_sets_pending_approval_without_sending_mail(): void
    {
        Mail::fake();
        $admin = $this->makeAdminWithTeam();
        [$course, $lesson1, $lesson2] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'checked_in_at' => now(),
        ]);

        $token = $enrollment->fresh()->access_token;
        $now = Carbon::parse('2026-05-09 10:00:00', config('app.timezone'));
        Carbon::setTestNow($now);

        $this->post(route('recorded-courses.public.unlock', ['token' => $token]))->assertRedirect();
        $this->post(route('recorded-courses.public.complete', ['token' => $token, 'lesson' => $lesson1->id]))->assertRedirect();

        Carbon::setTestNow($now->copy()->addHours(2));
        $this->post(route('recorded-courses.public.unlock', ['token' => $token]))->assertRedirect();
        $this->post(route('recorded-courses.public.complete', ['token' => $token, 'lesson' => $lesson2->id]))->assertRedirect();

        $enrollment->refresh();
        $this->assertNotNull($enrollment->completed_at);
        $this->assertSame(
            RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL,
            $enrollment->certificate_status
        );

        Mail::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_approve_certificate_requires_permission_and_dispatches_send_job(): void
    {
        Bus::fake([SendRecordedCourseCertificateJob::class]);
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'completed_at' => now(),
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL,
        ]);

        $userWithoutPerm = User::factory()->create(['current_team_id' => $admin->current_team_id]);

        $this->actingAs($userWithoutPerm)
            ->post(route('back.settings.recorded-courses.enrollments.approve-certificate', [$course, $enrollment]))
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('back.settings.recorded-courses.enrollments.index', $course))
            ->post(route('back.settings.recorded-courses.enrollments.approve-certificate', [$course, $enrollment]))
            ->assertRedirect(route('back.settings.recorded-courses.enrollments.index', $course));

        $enrollment->refresh();
        $this->assertSame(RecordedCourseEnrollment::CERTIFICATE_STATUS_APPROVED, $enrollment->certificate_status);
        $this->assertNotNull($enrollment->certificate_approved_at);
        $this->assertSame($admin->id, $enrollment->certificate_approved_by);

        Bus::assertDispatched(SendRecordedCourseCertificateJob::class, function ($job) use ($enrollment) {
            return $job->enrollmentId === $enrollment->id;
        });
    }

    public function test_enroll_dispatches_access_link_job(): void
    {
        Bus::fake([SendRecordedCourseAccessLinkJob::class]);
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $this->actingAs($admin)
            ->post(route('back.settings.recorded-courses.enrollments.store', $course), [
                'trainee_id' => $trainee->id,
            ])
            ->assertRedirect();

        Bus::assertDispatched(SendRecordedCourseAccessLinkJob::class);
    }

    public function test_mailgun_webhook_updates_recorded_course_certificate_delivery(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_SENT,
            'certificate_sent_at' => now(),
        ]);

        $this->post(route('webhooks.mail'), [
            'event-data' => [
                'event' => 'delivered',
                'recipient' => $trainee->email,
                'user-variables' => [
                    'recorded_course_enrollment_id' => $enrollment->id,
                    'type' => 'recorded_course_certificate',
                ],
                'message' => [
                    'headers' => [
                        'message-id' => 'msg-rc-123@mailgun',
                    ],
                ],
            ],
        ])->assertOk();

        $enrollment->refresh();
        $this->assertSame('delivered', $enrollment->delivery_status);
        $this->assertNotNull($enrollment->delivered_at);
        $this->assertSame('msg-rc-123@mailgun', $enrollment->mailgun_message_id);
    }

    public function test_mailgun_webhook_ignores_cc_recipient_events(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_SENT,
        ]);

        $this->post(route('webhooks.mail'), [
            'event-data' => [
                'event' => 'delivered',
                'recipient' => 'company-cc@example.com',
                'user-variables' => [
                    'recorded_course_enrollment_id' => $enrollment->id,
                ],
                'message' => [
                    'headers' => [
                        'message-id' => 'msg-cc@mailgun',
                    ],
                ],
            ],
        ])->assertOk();

        $enrollment->refresh();
        $this->assertNull($enrollment->delivery_status);
        $this->assertNull($enrollment->delivered_at);
    }

    public function test_enrollments_page_includes_company_progress_summaries(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);

        $this->actingAs($admin);
        $company = \App\Models\Back\Company::factory()->create([
            'team_id' => $admin->current_team_id,
            'name_ar' => 'شركة الاختبار',
            'name_en' => 'Test Co',
            'shelf_number' => 'SH-TEST-1',
        ]);

        $trainee = $this->createTrainee($admin);
        $trainee->forceFill(['company_id' => $company->id])->save();

        RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'completed_at' => now(),
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($admin)
            ->get(route('back.settings.recorded-courses.enrollments.index', $course))
            ->assertOk()
            ->assertPropCount('companySummaries', 1)
            ->assertPropValue('companySummaries', function ($summaries) use ($company) {
                $this->assertSame($company->id, $summaries[0]['company_id']);
                $this->assertSame(1, $summaries[0]['enrolled']);
                $this->assertSame(1, $summaries[0]['eligible']);
            })
            ->assertPropValue('enrollments', function ($enrollments) {
                $this->assertSame('eligible', $enrollments[0]['entitlement']);
                $this->assertArrayHasKey('progress_percent', $enrollments[0]);
            });

        $response = $this->actingAs($admin)->getJson(
            route('back.settings.recorded-courses.enrollments.company-trainees', $course)
                .'?company_id='.$company->id
        );

        $response->assertOk();
        $this->assertTrue(collect($response->json('trainees'))->contains(
            fn ($t) => $t['id'] === $trainee->id && $t['already_enrolled'] === true
        ));
    }

    public function test_training_disclosure_hq_page(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'completed_at' => now(),
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($admin)
            ->get(route('back.training-disclosure.index'))
            ->assertOk()
            ->assertPropValue('stats', function ($stats) {
                $this->assertSame(1, $stats['courses']);
                $this->assertSame(1, $stats['pending_approval']);
            })
            ->assertPropCount('courses', 1)
            ->assertPropCount('pendingApprovals', 1);
    }
}
