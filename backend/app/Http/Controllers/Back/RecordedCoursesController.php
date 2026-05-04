<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StoreRecordedCourseRequest;
use App\Http\Requests\Back\UpdateRecordedCourseRequest;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseLesson;
use App\Services\RecordedCourseLessonVideoChunkUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class RecordedCoursesController extends Controller
{
    /**
     * Flatten and dedupe weekday ints (0–6). Nested arrays break PHP's array_unique().
     */
    private static function normalizeAllowedWeekdays(array $weekdays): array
    {
        $flat = [];
        $walk = static function (mixed $item) use (&$flat, &$walk): void {
            if (is_array($item)) {
                foreach ($item as $sub) {
                    $walk($sub);
                }
            } elseif (is_numeric($item)) {
                $i = (int) $item;
                if ($i >= 0 && $i <= 6) {
                    $flat[] = $i;
                }
            }
        };
        foreach ($weekdays as $item) {
            $walk($item);
        }

        return array_values(array_unique($flat));
    }

    /**
     * Persist JSON text only: Illuminate\Database\Query\Builder::insert() treats the row as a
     * batch when the first column value is a PHP array, then calls ksort() on each value (strings fail).
     */
    private static function allowedWeekdaysJson(array $weekdays): string
    {
        return json_encode(self::normalizeAllowedWeekdays($weekdays), JSON_THROW_ON_ERROR);
    }

    public function index(): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $recordedCourses = RecordedCourse::query()
            ->withCount('lessons')
            ->latest()
            ->paginate(15);

        return Inertia::render('Back/Settings/RecordedCourses/Index', [
            'recordedCourses' => $recordedCourses,
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        return Inertia::render('Back/Settings/RecordedCourses/Create', [
            'defaultWeekdays' => [0, 1, 2, 3, 4, 5, 6],
        ]);
    }

    public function store(StoreRecordedCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var RecordedCourse $course */
        $course = DB::transaction(function () use ($request, $validated): RecordedCourse {
            $course = RecordedCourse::query()->create([
                'name_ar' => $validated['name_ar'],
                'name_en' => $validated['name_en'],
                'description' => $validated['description'],
                'unlock_delay_hours' => $validated['unlock_delay_hours'],
                'allowed_weekdays' => self::allowedWeekdaysJson($validated['allowed_weekdays']),
            ]);

            foreach (array_keys($validated['lessons']) as $index) {
                $course->lessons()->create([
                    'sort_order' => (int) $index,
                    'title_ar' => $request->input("lessons.{$index}.title_ar"),
                    'title_en' => $request->input("lessons.{$index}.title_en") ?? '',
                ]);
            }

            return $course;
        });

        return redirect()
            ->route('back.settings.recorded-courses.edit', $course)
            ->with('success', __('words.recorded-course-created-add-videos'));
    }

    public function edit(RecordedCourse $recordedCourse): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $recordedCourse->load([
            'lessons',
            'enrollments.trainee',
            'enrollments.lessonProgress',
        ]);

        $lessonsOrdered = $recordedCourse->lessons;

        $enrollments = $recordedCourse->enrollments->map(function ($e) use ($lessonsOrdered) {
            $byLessonId = $e->lessonProgress->keyBy('recorded_course_lesson_id');
            $lessonProgress = $lessonsOrdered->map(function (RecordedCourseLesson $lesson) use ($byLessonId) {
                $p = $byLessonId->get($lesson->id);

                return [
                    'lesson_id' => $lesson->id,
                    'title_ar' => $lesson->title_ar,
                    'title_en' => $lesson->title_en ?? '',
                    'unlocked_at' => $p?->unlocked_at?->toIso8601String(),
                    'completed_at' => $p?->completed_at?->toIso8601String(),
                ];
            })->values()->all();

            return [
                'id' => $e->id,
                'trainee_id' => $e->trainee_id,
                'trainee_name' => $e->trainee?->name,
                'enrolled_at' => $e->enrolled_at?->toIso8601String(),
                'lesson_progress' => $lessonProgress,
            ];
        });

        $lessons = $recordedCourse->lessons->map(function (RecordedCourseLesson $lesson) {
            $media = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION);

            return [
                'id' => $lesson->id,
                'title_ar' => $lesson->title_ar,
                'title_en' => $lesson->title_en ?? '',
                'has_video' => $media !== null,
                'video_file_name' => $media?->name,
            ];
        });

        return Inertia::render('Back/Settings/RecordedCourses/Edit', [
            'recordedCourse' => [
                'id' => $recordedCourse->id,
                'name_ar' => $recordedCourse->name_ar,
                'name_en' => $recordedCourse->name_en,
                'description' => $recordedCourse->description,
                'unlock_delay_hours' => $recordedCourse->unlock_delay_hours,
                'allowed_weekdays' => $recordedCourse->allowed_weekdays ?? [],
            ],
            'lessons' => $lessons,
            'enrollments' => $enrollments,
        ]);
    }

    public function update(
        UpdateRecordedCourseRequest $request,
        RecordedCourse $recordedCourse,
        RecordedCourseLessonVideoChunkUploadService $chunkUploads,
    ): RedirectResponse {
        $validated = $request->validated();

        $recordedCourse->update([
            'name_ar' => $validated['name_ar'],
            'name_en' => $validated['name_en'],
            'description' => $validated['description'],
            'unlock_delay_hours' => $validated['unlock_delay_hours'],
            'allowed_weekdays' => self::allowedWeekdaysJson($validated['allowed_weekdays']),
        ]);

        $lessonsInput = $request->input('lessons', []);
        $keepIds = collect($lessonsInput)->pluck('id')->filter()->values();

        DB::transaction(function () use ($recordedCourse, $request, $lessonsInput, $keepIds, $chunkUploads): void {
            $recordedCourse->lessons()
                ->whereNotIn('id', $keepIds->all())
                ->delete();

            foreach ($lessonsInput as $index => $lessonInput) {
                $lessonId = $lessonInput['id'] ?? null;

                if ($lessonId) {
                    /** @var RecordedCourseLesson $lesson */
                    $lesson = $recordedCourse->lessons()->where('id', $lessonId)->firstOrFail();
                    $lesson->update([
                        'sort_order' => $index,
                        'title_ar' => $request->input("lessons.{$index}.title_ar"),
                        'title_en' => $request->input("lessons.{$index}.title_en") ?? '',
                    ]);
                    if ($request->hasFile("lessons.{$index}.video")) {
                        $lesson->attachVideo($request->file("lessons.{$index}.video"));
                    } elseif (filled($request->input("lessons.{$index}.upload_token"))) {
                        try {
                            $payload = $chunkUploads->consumeReadyToken(
                                (int) $request->user()->id,
                                (string) $request->input("lessons.{$index}.upload_token")
                            );
                        } catch (InvalidArgumentException $e) {
                            throw ValidationException::withMessages([
                                "lessons.{$index}.upload_token" => [$e->getMessage()],
                            ]);
                        }
                        $lesson->attachVideoFromAssembledFile($payload['path'], $payload['original_name']);
                    }
                } else {
                    $lesson = $recordedCourse->lessons()->create([
                        'sort_order' => $index,
                        'title_ar' => $request->input("lessons.{$index}.title_ar"),
                        'title_en' => $request->input("lessons.{$index}.title_en") ?? '',
                    ]);
                    if ($request->hasFile("lessons.{$index}.video")) {
                        $lesson->attachVideo($request->file("lessons.{$index}.video"));
                    } elseif (filled($request->input("lessons.{$index}.upload_token"))) {
                        try {
                            $payload = $chunkUploads->consumeReadyToken(
                                (int) $request->user()->id,
                                (string) $request->input("lessons.{$index}.upload_token")
                            );
                        } catch (InvalidArgumentException $e) {
                            throw ValidationException::withMessages([
                                "lessons.{$index}.upload_token" => [$e->getMessage()],
                            ]);
                        }
                        $lesson->attachVideoFromAssembledFile($payload['path'], $payload['original_name']);
                    }
                }
            }
        });

        return redirect()
            ->route('back.settings.recorded-courses.edit', $recordedCourse)
            ->with('success', __('words.saved-successfully'));
    }

    public function destroy(RecordedCourse $recordedCourse): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $recordedCourse->delete();

        return redirect()->route('back.settings.recorded-courses.index');
    }
}
