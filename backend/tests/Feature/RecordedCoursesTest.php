<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\RolesService;
use Illuminate\Http\UploadedFile;
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

    public function test_admin_can_create_recorded_course_with_lesson_video(): void
    {
        $admin = $this->makeAdminWithTeam();

        $file = UploadedFile::fake()->create('lesson.mp4', 1024, 'video/mp4');

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
                        'video' => $file,
                    ],
                ],
            ]
        )->assertRedirect(route('back.settings.recorded-courses.index'));

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
