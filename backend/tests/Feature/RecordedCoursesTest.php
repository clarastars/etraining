<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Back\RecordedCourse;
use App\Models\User;
use App\Services\RolesService;
use Tests\TestCase;

class RecordedCoursesTest extends TestCase
{
    protected function makeAdminWithTeam(): User
    {
        $admin = User::factory()->create();
        $team = $admin->ownedTeams()->create([
            'name' => 'Test Team RC',
            'personal_team' => false,
        ]);
        app(RolesService::class)->seedRolesToTeam($team);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        return $admin->fresh();
    }

    public function test_user_without_permission_cannot_access_recorded_courses_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('back.settings.recorded-courses.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_recorded_course_without_lesson_videos(): void
    {
        $admin = $this->makeAdminWithTeam();

        $this->actingAs($admin)->post(
            route('back.settings.recorded-courses.store'),
            [
                'name_ar' => 'دورة تسجيل',
                'name_en' => 'Recorded course',
                'description' => 'Desc',
                'unlock_delay_hours' => 24,
                'allowed_weekdays' => [0, 1, 2, 3, 4],
                'lessons' => [
                    [
                        'title_ar' => 'درس 1',
                        'title_en' => 'Lesson 1',
                    ],
                ],
            ]
        )->assertRedirect(
            route(
                'back.settings.recorded-courses.edit',
                RecordedCourse::query()->where('name_en', 'Recorded course')->firstOrFail()
            )
        );

        $this->assertDatabaseHas('recorded_courses', [
            'name_en' => 'Recorded course',
            'unlock_delay_hours' => 24,
        ]);

        $this->assertDatabaseHas('recorded_course_lessons', [
            'title_ar' => 'درس 1',
            'title_en' => 'Lesson 1',
        ]);
    }
}
