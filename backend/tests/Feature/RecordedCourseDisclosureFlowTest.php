<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendRecordedCourseAccessLinkJob;
use App\Jobs\SendRecordedCourseCertificateJob;
use App\Mail\RecordedCourseReminderMail;
use App\Models\Back\Company;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\RecordedCourseLesson;
use App\Models\Back\RecordedCourseLessonProgress;
use App\Models\Back\Trainee;
use App\Models\Back\TrainingDisclosureRequest;
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

    public function test_public_lesson_list_strips_recording_indexes_from_titles(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course, $lesson1] = $this->createCourseTwoLessons($admin);
        $course->forceFill(['name_ar' => 'نظام العمل السعودي'])->save();
        $lesson1->update([
            'title_ar' => 'نظام العمل السعودي - اليوم الأول - الجلسة الأولى - أهمية نظام العمل السعودي',
            'title_en' => 'ساعات العمل الأسبوعية-2',
        ]);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'checked_in_at' => now(),
        ]);

        $this->get(route('recorded-courses.public.show', ['token' => $enrollment->fresh()->access_token]))
            ->assertOk()
            ->assertSee('أهمية نظام العمل السعودي')
            ->assertDontSee('ساعات العمل الأسبوعية-2', false)
            ->assertDontSee('اليوم الأول - الجلسة الأولى', false);
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

    public function test_mailgun_webhook_updates_recorded_course_access_link_delivery(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'access_link_sent_at' => now(),
            'delivery_status' => 'pending',
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE,
        ]);

        $this->post(route('webhooks.mail'), [
            'event-data' => [
                'event' => 'delivered',
                'recipient' => $trainee->email,
                'user-variables' => [
                    'recorded_course_enrollment_id' => $enrollment->id,
                    'type' => 'recorded_course_access_link',
                ],
                'message' => [
                    'headers' => [
                        'message-id' => 'msg-access-123@mailgun',
                    ],
                ],
            ],
        ])->assertOk();

        $enrollment->refresh();
        $this->assertSame('delivered', $enrollment->delivery_status);
        $this->assertNotNull($enrollment->delivered_at);
        $this->assertSame('msg-access-123@mailgun', $enrollment->mailgun_message_id);
        $this->assertSame(RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE, $enrollment->certificate_status);
    }

    public function test_mailgun_webhook_marks_access_link_delivery_failed_without_touching_certificate(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'access_link_sent_at' => now(),
            'delivery_status' => 'pending',
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE,
        ]);

        $this->post(route('webhooks.mail'), [
            'event-data' => [
                'event' => 'bounced',
                'recipient' => $trainee->email,
                'user-variables' => [
                    'recorded_course_enrollment_id' => $enrollment->id,
                    'type' => 'recorded_course_access_link',
                ],
                'delivery-status' => [
                    'message' => 'mailbox full',
                ],
                'message' => [
                    'headers' => [
                        'message-id' => 'msg-access-bounce@mailgun',
                    ],
                ],
            ],
        ])->assertOk();

        $enrollment->refresh();
        $this->assertSame('failed', $enrollment->delivery_status);
        $this->assertNotNull($enrollment->failed_at);
        $this->assertStringContainsString('mailbox full', (string) $enrollment->delivery_failure_reason);
        $this->assertSame(RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE, $enrollment->certificate_status);
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
            ->assertPropCount('pendingApprovals', 1)
            ->assertPropValue('courses', function ($courses) {
                $this->assertArrayHasKey('ready', $courses[0]);
                $this->assertArrayHasKey('lessons_count', $courses[0]);
            });
    }

    public function test_recorded_course_lesson_video_collection_uses_s3(): void
    {
        $lesson = new RecordedCourseLesson();
        $lesson->registerMediaCollections();

        $collection = $lesson->getMediaCollection(RecordedCourseLesson::VIDEO_COLLECTION);
        $this->assertNotNull($collection);
        $this->assertSame('s3', $collection->diskName);
    }

    public function test_completion_reminder_repeats_every_twelve_hours_until_the_course_is_finished(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-19 12:00:00');

        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now()->subHours(13),
            'access_link_sent_at' => now()->subHours(12),
            'delivery_status' => 'delivered',
        ]);
        $token = $enrollment->access_token;

        $this->artisan('recorded-courses:send-completion-reminders')->assertExitCode(0);

        Mail::assertSent(RecordedCourseReminderMail::class, function (RecordedCourseReminderMail $mail) use ($trainee) {
            return $mail->hasTo($trainee->email);
        });
        $enrollment->refresh();
        $this->assertNotNull($enrollment->last_reminder_sent_at);
        $this->assertSame('pending', $enrollment->reminder_delivery_status);
        $this->assertSame($token, $enrollment->access_token);

        Mail::fake();
        $this->artisan('recorded-courses:send-completion-reminders')->assertExitCode(0);
        Mail::assertNothingSent();

        Carbon::setTestNow(now()->addHours(12));
        $this->artisan('recorded-courses:send-completion-reminders')->assertExitCode(0);
        Mail::assertSent(RecordedCourseReminderMail::class);

        $enrollment->forceFill(['completed_at' => now()])->save();
        Carbon::setTestNow(now()->addHours(12));
        Mail::fake();
        $this->artisan('recorded-courses:send-completion-reminders')->assertExitCode(0);
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_completion_reminder_skips_recent_invites_and_bounced_addresses(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-10-19 12:00:00');

        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $recent = $this->createTrainee($admin);
        $bounced = $this->createTrainee($admin);

        RecordedCourseEnrollment::query()->create([
            'team_id' => $recent->team_id,
            'trainee_id' => $recent->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now()->subHour(),
            'access_link_sent_at' => now()->subHour(),
            'delivery_status' => 'delivered',
        ]);
        RecordedCourseEnrollment::query()->create([
            'team_id' => $bounced->team_id,
            'trainee_id' => $bounced->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now()->subDay(),
            'access_link_sent_at' => now()->subDay(),
            'delivery_status' => 'failed',
        ]);

        $this->artisan('recorded-courses:send-completion-reminders')->assertExitCode(0);
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    public function test_mailgun_reminder_bounce_does_not_change_invite_or_certificate_status(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course] = $this->createCourseTwoLessons($admin);
        $trainee = $this->createTrainee($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now()->subDay(),
            'access_link_sent_at' => now()->subDay(),
            'delivery_status' => 'delivered',
            'certificate_status' => RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE,
            'last_reminder_sent_at' => now(),
            'reminder_delivery_status' => 'pending',
        ]);

        $this->post(route('webhooks.mail'), [
            'event-data' => [
                'event' => 'bounced',
                'recipient' => $trainee->email,
                'user-variables' => [
                    'recorded_course_enrollment_id' => $enrollment->id,
                    'type' => 'recorded_course_reminder',
                ],
                'delivery-status' => [
                    'message' => 'No such person',
                ],
                'message' => [
                    'headers' => [
                        'message-id' => 'msg-reminder-bounce@mailgun',
                    ],
                ],
            ],
        ])->assertOk();

        $enrollment->refresh();
        $this->assertSame('failed', $enrollment->reminder_delivery_status);
        $this->assertStringContainsString('No such person', (string) $enrollment->reminder_failure_reason);
        $this->assertSame('delivered', $enrollment->delivery_status);
        $this->assertSame(RecordedCourseEnrollment::CERTIFICATE_STATUS_NONE, $enrollment->certificate_status);

        Mail::fake();
        Carbon::setTestNow(now()->addHours(13));
        $this->artisan('recorded-courses:send-completion-reminders')->assertExitCode(0);
        Mail::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_company_report_is_limited_to_disclosure_request_companies(): void
    {
        $admin = $this->makeAdminWithTeam();
        [$course, $lesson1] = $this->createCourseTwoLessons($admin);

        $company = Company::factory()->create([
            'team_id' => $admin->current_team_id,
            'name_ar' => 'شركة الإفصاح',
            'name_en' => 'Disclosure Co',
            'shelf_number' => 'SH-REPORT-1',
        ]);
        $otherCompany = Company::factory()->create([
            'team_id' => $admin->current_team_id,
            'name_ar' => 'شركة أخرى',
            'name_en' => 'Other Co',
            'shelf_number' => 'SH-REPORT-2',
        ]);

        $checkedIn = $this->createTrainee($admin);
        $checkedIn->forceFill([
            'company_id' => $company->id,
            'name' => 'Nora',
            'email' => 'nora@example.com',
        ])->save();

        $notStarted = $this->createTrainee($admin);
        $notStarted->forceFill([
            'company_id' => $company->id,
            'name' => 'Sam',
            'email' => 'sam@example.com',
        ])->save();

        $outsider = $this->createTrainee($admin);
        $outsider->forceFill([
            'company_id' => $otherCompany->id,
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
        ])->save();

        $checkedInEnrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $checkedIn->team_id,
            'trainee_id' => $checkedIn->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'checked_in_at' => now(),
        ]);
        RecordedCourseLessonProgress::query()->create([
            'recorded_course_enrollment_id' => $checkedInEnrollment->id,
            'recorded_course_lesson_id' => $lesson1->id,
            'unlocked_at' => now(),
            'completed_at' => now(),
        ]);

        RecordedCourseEnrollment::query()->create([
            'team_id' => $notStarted->team_id,
            'trainee_id' => $notStarted->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'delivery_status' => 'pending',
        ]);

        RecordedCourseEnrollment::query()->create([
            'team_id' => $outsider->team_id,
            'trainee_id' => $outsider->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
            'delivery_status' => 'delivered',
            'checked_in_at' => now(),
        ]);

        TrainingDisclosureRequest::query()->create([
            'team_id' => $admin->current_team_id,
            'number' => 'E100',
            'company_id' => $company->id,
            'company_name' => 'شركة الإفصاح',
            'trainees_count' => 2,
            'trainees_draft' => [],
        ]);
        TrainingDisclosureRequest::query()->create([
            'team_id' => $admin->current_team_id,
            'number' => 'E101',
            'company_id' => $company->id,
            'company_name' => 'شركة الإفصاح',
            'trainees_count' => 2,
            'trainees_draft' => [],
        ]);

        $userWithoutPerm = User::factory()->create(['current_team_id' => $admin->current_team_id]);
        $this->actingAs($userWithoutPerm)
            ->get(route('back.training-disclosure.report'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('back.training-disclosure.report'))
            ->assertOk()
            ->assertPropCount('companies', 1)
            ->assertPropValue('companies', function ($companies) use ($company) {
                $this->assertSame($company->id, $companies[0]['id']);
                $this->assertSame(2, $companies[0]['requests_count']);
                $this->assertTrue($companies[0]['linked']);
            })
            ->assertPropCount('rows', 0);

        $this->actingAs($admin)
            ->get(route('back.training-disclosure.report', ['company' => $otherCompany->id]))
            ->assertOk()
            ->assertPropValue('selectedCompany', function ($selected) {
                $this->assertNull($selected);
            })
            ->assertPropCount('rows', 0);

        $this->actingAs($admin)
            ->get(route('back.training-disclosure.report', ['company' => $company->id]))
            ->assertOk()
            ->assertPropValue('summary', function ($summary) {
                $this->assertSame(2, $summary['employees']);
                $this->assertSame(1, $summary['emails_delivered']);
                $this->assertSame(1, $summary['accessed']);
                $this->assertSame(0, $summary['eligible']);
            })
            ->assertPropValue('rows', function ($rows) {
                $this->assertCount(2, $rows);
                $this->assertSame('Nora', $rows[0]['name']);
                $this->assertSame('nora@example.com', $rows[0]['email']);
                $this->assertSame('delivered', $rows[0]['delivery_status']);
                $this->assertTrue($rows[0]['accessed']);
                $this->assertSame(50, $rows[0]['progress_percent']);
                $this->assertSame(1, $rows[0]['lessons_completed']);
                $this->assertSame(2, $rows[0]['lessons_total']);
                $this->assertSame('in_progress', $rows[0]['entitlement']);
                $this->assertSame('Sam', $rows[1]['name']);
                $this->assertSame('pending', $rows[1]['delivery_status']);
                $this->assertFalse($rows[1]['accessed']);
                $this->assertSame(0, $rows[1]['progress_percent']);
                $this->assertSame('not_started', $rows[1]['entitlement']);
            });

        $export = $this->actingAs($admin)
            ->get(route('back.training-disclosure.report.export', ['company' => $company->id]));

        $export->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            (string) $export->headers->get('content-type')
        );

        $this->actingAs($admin)
            ->get(route('back.training-disclosure.report.export', ['company' => $otherCompany->id]))
            ->assertNotFound();
    }
}
