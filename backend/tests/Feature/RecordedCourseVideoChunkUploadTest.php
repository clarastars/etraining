<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseLesson;
use App\Models\User;
use App\Services\RolesService;
use Tests\TestCase;

class RecordedCourseVideoChunkUploadTest extends TestCase
{
    /**
     * Minimal bytes that file(1) / finfo report as video/mp4 (ftyp isom).
     */
    private static function minimalMp4Payload(): string
    {
        return hex2bin('000000206674797069736f6d0000020069736f6d69736f32617663316d703431');
    }

    private function makeAdminWithTeam(): User
    {
        $admin = User::factory()->create();
        $team = $admin->ownedTeams()->create([
            'name' => 'Test Team Chunk',
            'personal_team' => false,
        ]);
        app(RolesService::class)->seedRolesToTeam($team);
        $admin->forceFill(['current_team_id' => $team->id])->save();

        return $admin->fresh();
    }

    public function test_chunked_upload_then_update_attaches_lesson_video(): void
    {
        $admin = $this->makeAdminWithTeam();
        $full = self::minimalMp4Payload();
        $total = strlen($full);
        $split = (int) floor($total / 2);
        $chunk0 = substr($full, 0, $split);
        $chunk1 = substr($full, $split);

        $start = $this->actingAs($admin)->postJson(
            route('back.settings.recorded-courses.lesson-videos.chunk-uploads.start'),
            [
                'file_name' => 'lesson.mp4',
                'mime_type' => 'video/mp4',
                'total_size' => $total,
            ]
        );
        $start->assertOk();
        $uploadId = $start->json('upload_id');
        $this->assertNotEmpty($uploadId);

        $chunkUrl = route('back.settings.recorded-courses.lesson-videos.chunk-uploads.chunk', $uploadId);
        $this->actingAs($admin)->call(
            'POST',
            $chunkUrl,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_CHUNK_INDEX' => '0',
            ],
            $chunk0
        )->assertOk();

        $this->actingAs($admin)->call(
            'POST',
            $chunkUrl,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_CHUNK_INDEX' => '1',
            ],
            $chunk1
        )->assertOk();

        $complete = $this->actingAs($admin)->postJson(
            route('back.settings.recorded-courses.lesson-videos.chunk-uploads.complete', $uploadId)
        );
        $complete->assertOk();
        $token = $complete->json('upload_token');
        $this->assertNotEmpty($token);

        $this->actingAs($admin)->post(
            route('back.settings.recorded-courses.store'),
            [
                'name_ar' => 'دورة',
                'name_en' => 'Chunk course',
                'description' => '',
                'unlock_delay_hours' => 24,
                'allowed_weekdays' => [0, 1, 2, 3, 4],
                'lessons' => [
                    [
                        'title_ar' => 'درس',
                        'title_en' => 'Lesson',
                    ],
                ],
            ]
        )->assertRedirect(
            route(
                'back.settings.recorded-courses.edit',
                RecordedCourse::query()->where('name_en', 'Chunk course')->firstOrFail()
            )
        );

        $course = RecordedCourse::query()->where('name_en', 'Chunk course')->firstOrFail();
        $lesson = $course->lessons()->orderBy('sort_order')->firstOrFail();

        $this->actingAs($admin)->put(
            route('back.settings.recorded-courses.update', $course->id),
            [
                'name_ar' => 'دورة',
                'name_en' => 'Chunk course',
                'description' => '',
                'unlock_delay_hours' => 24,
                'allowed_weekdays' => [0, 1, 2, 3, 4],
                'lessons' => [
                    [
                        'id' => $lesson->id,
                        'title_ar' => 'درس',
                        'title_en' => 'Lesson',
                        'upload_token' => $token,
                    ],
                ],
            ]
        )->assertRedirect(route('back.settings.recorded-courses.edit', $course));

        $this->assertTrue(
            $lesson->fresh()->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION) !== null
        );
    }
}
