<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\RecordedCourseLesson;
use App\Models\Back\Trainee;
use App\Models\Role;
use App\Models\User;
use App\Services\RolesService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RecordedCourseLearnerFlowTest extends TestCase
{
    private function makeAdminWithTeam(): User
    {
        $admin = User::factory()->create();
        $team = $admin->ownedTeams()->create([
            'name' => 'Test Team RC Learner',
            'personal_team' => false,
        ]);
        app(RolesService::class)->seedRolesToTeam($team);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        return $admin->fresh();
    }

    /**
     * @return array{0: User, 1: RecordedCourse, 2: RecordedCourseLesson, 3: RecordedCourseLesson}
     */
    private function createCourseTwoLessonsSaturdayOnly(User $admin): array
    {
        $f1 = UploadedFile::fake()->create('l1.mp4', 200, 'video/mp4');
        $f2 = UploadedFile::fake()->create('l2.mp4', 200, 'video/mp4');

        $this->actingAs($admin)->post(
            route('back.settings.recorded-courses.store'),
            [
                'name_ar' => 'دورة',
                'name_en' => 'Course',
                'description' => 'D',
                'unlock_delay_hours' => 1,
                'allowed_weekdays' => [6],
                'lessons' => [
                    ['title_ar' => 'L1', 'title_en' => 'L1', 'video' => $f1],
                    ['title_ar' => 'L2', 'title_en' => 'L2', 'video' => $f2],
                ],
            ]
        )->assertRedirect(route('back.settings.recorded-courses.index'));

        $course = RecordedCourse::query()->where('name_en', 'Course')->firstOrFail();
        $lessons = $course->lessons()->orderBy('sort_order')->get();

        return [$admin, $course, $lessons[0], $lessons[1]];
    }

    /**
     * @return array{0: User, 1: Trainee}
     */
    private function createTraineeForTeam(User $admin): array
    {
        $teamId = $admin->current_team_id;
        $this->assertNotNull($teamId);
        $user = User::factory()->create(['current_team_id' => $teamId]);
        $trainee = Trainee::factory()->create([
            'team_id' => $teamId,
            'email' => $user->email,
        ]);
        $trainee->forceFill([
            'user_id' => $user->id,
            'skip_uploading_id' => true,
        ])->save();

        $role = Role::findByName($teamId.'_trainees', 'web');
        $user->assignRole($role);

        return [$user, $trainee->fresh()];
    }

    public function test_unlock_fails_on_disallowed_weekday(): void
    {
        $admin = $this->makeAdminWithTeam();
        [, $course] = $this->createCourseTwoLessonsSaturdayOnly($admin);
        [$traineeUser, $trainee] = $this->createTraineeForTeam($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00', config('app.timezone'))); // Monday

        $this->actingAs($traineeUser)
            ->from(route('recorded-courses.enrollments.show', $enrollment->id))
            ->post(route('recorded-courses.enrollments.unlock', $enrollment->id))
            ->assertSessionHasErrors('unlock');

        Carbon::setTestNow();
    }

    public function test_unlock_succeeds_on_allowed_weekday_and_stream_blocked_without_unlock(): void
    {
        $admin = $this->makeAdminWithTeam();
        [, $course, $lesson1] = $this->createCourseTwoLessonsSaturdayOnly($admin);
        [$traineeUser, $trainee] = $this->createTraineeForTeam($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($traineeUser)
            ->get(route('recorded-courses.enrollments.lessons.stream', [$enrollment->id, $lesson1->id]))
            ->assertForbidden();

        Carbon::setTestNow(Carbon::parse('2026-05-09 10:00:00', config('app.timezone'))); // Saturday

        $this->actingAs($traineeUser)
            ->post(route('recorded-courses.enrollments.unlock', $enrollment->id))
            ->assertRedirect(route('recorded-courses.enrollments.show', $enrollment->id));

        $this->assertDatabaseHas('recorded_course_lesson_progress', [
            'recorded_course_enrollment_id' => $enrollment->id,
            'recorded_course_lesson_id' => $lesson1->id,
        ]);

        $this->actingAs($traineeUser)
            ->get(route('recorded-courses.enrollments.lessons.stream', [$enrollment->id, $lesson1->id]))
            ->assertStatus(200);

        Carbon::setTestNow();
    }

    public function test_second_lesson_unlock_requires_completion_and_delay(): void
    {
        $admin = $this->makeAdminWithTeam();
        [, $course, $lesson1, $lesson2] = $this->createCourseTwoLessonsSaturdayOnly($admin);
        [$traineeUser, $trainee] = $this->createTraineeForTeam($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        $sat = Carbon::parse('2026-05-09 12:00:00', config('app.timezone'));
        Carbon::setTestNow($sat);

        $this->actingAs($traineeUser)
            ->post(route('recorded-courses.enrollments.unlock', $enrollment->id))
            ->assertRedirect(route('recorded-courses.enrollments.show', $enrollment->id));

        Carbon::setTestNow($sat->copy()->addHour());
        $this->actingAs($traineeUser)
            ->from(route('recorded-courses.enrollments.show', $enrollment->id))
            ->post(route('recorded-courses.enrollments.unlock', $enrollment->id))
            ->assertSessionHasErrors('unlock');

        $this->actingAs($traineeUser)
            ->post(route('recorded-courses.enrollments.lessons.complete', [$enrollment->id, $lesson1->id]))
            ->assertRedirect(route('recorded-courses.enrollments.show', $enrollment->id));

        Carbon::setTestNow($sat->copy()->addMinutes(30));
        $this->actingAs($traineeUser)
            ->from(route('recorded-courses.enrollments.show', $enrollment->id))
            ->post(route('recorded-courses.enrollments.unlock', $enrollment->id))
            ->assertSessionHasErrors('unlock');

        Carbon::setTestNow($sat->copy()->addHours(2));
        $this->actingAs($traineeUser)
            ->post(route('recorded-courses.enrollments.unlock', $enrollment->id))
            ->assertRedirect(route('recorded-courses.enrollments.show', $enrollment->id));

        $this->assertDatabaseHas('recorded_course_lesson_progress', [
            'recorded_course_enrollment_id' => $enrollment->id,
            'recorded_course_lesson_id' => $lesson2->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_catch_up_same_first_lesson_after_skipping_allowed_day(): void
    {
        $admin = $this->makeAdminWithTeam();
        [, $course, $lesson1] = $this->createCourseTwoLessonsSaturdayOnly($admin);
        [$traineeUser, $trainee] = $this->createTraineeForTeam($admin);

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
            'enrolled_at' => now(),
        ]);

        $sat1 = Carbon::parse('2026-05-09 10:00:00', config('app.timezone'));
        Carbon::setTestNow($sat1);
        $this->actingAs($traineeUser)->post(route('recorded-courses.enrollments.unlock', $enrollment->id));

        $sat2 = Carbon::parse('2026-05-16 10:00:00', config('app.timezone'));
        Carbon::setTestNow($sat2);

        $this->actingAs($traineeUser)
            ->post(route('recorded-courses.enrollments.lessons.complete', [$enrollment->id, $lesson1->id]));

        Carbon::setTestNow($sat2->copy()->addHours(2));
        $this->actingAs($traineeUser)->post(route('recorded-courses.enrollments.unlock', $enrollment->id));

        $this->assertDatabaseHas('recorded_course_lesson_progress', [
            'recorded_course_lesson_id' => $course->lessons()->orderBy('sort_order')->skip(1)->first()->id,
            'recorded_course_enrollment_id' => $enrollment->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_can_enroll_trainee_via_settings(): void
    {
        $admin = $this->makeAdminWithTeam();
        [, $course] = $this->createCourseTwoLessonsSaturdayOnly($admin);
        [, $trainee] = $this->createTraineeForTeam($admin);

        $this->actingAs($admin)
            ->post(route('back.settings.recorded-courses.enrollments.store', $course->id), [
                'trainee_id' => $trainee->id,
            ])
            ->assertRedirect(route('back.settings.recorded-courses.edit', $course->id));

        $this->assertDatabaseHas('recorded_course_enrollments', [
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $course->id,
        ]);
    }
}
