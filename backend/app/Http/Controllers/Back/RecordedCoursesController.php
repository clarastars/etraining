<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\StoreRecordedCourseRequest;
use App\Http\Requests\Back\UpdateRecordedCourseDetailsRequest;
use App\Http\Requests\Back\UpdateRecordedCourseScheduleRequest;
use App\Http\Requests\Back\UpdateRecordedCourseRequest;
use App\Models\Back\Company;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
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
            ->route('back.settings.recorded-courses.show', $course)
            ->with('success', __('words.recorded-course-created-start-setup'));
    }

    public function show(RecordedCourse $recordedCourse): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $recordedCourse->load(['lessons', 'enrollments']);

        return Inertia::render('Back/Settings/RecordedCourses/Show', [
            'recordedCourse' => $this->coursePayload($recordedCourse),
            'readiness' => $this->readiness($recordedCourse),
            'lessons' => $recordedCourse->lessons->map(fn (RecordedCourseLesson $lesson) => $this->lessonSummary($recordedCourse, $lesson)),
        ]);
    }

    public function edit(RecordedCourse $recordedCourse): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        return redirect()->route('back.settings.recorded-courses.show', $recordedCourse);
    }

    public function editDetails(RecordedCourse $recordedCourse): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $recordedCourse->load('lessons');

        return Inertia::render('Back/Settings/RecordedCourses/Details', [
            'recordedCourse' => $this->coursePayload($recordedCourse),
            'readiness' => $this->readiness($recordedCourse),
        ]);
    }

    public function updateDetails(UpdateRecordedCourseDetailsRequest $request, RecordedCourse $recordedCourse): RedirectResponse
    {
        $validated = $request->validated();
        $recordedCourse->update($validated);

        return redirect()
            ->route('back.settings.recorded-courses.details.edit', $recordedCourse)
            ->with('success', __('words.saved-successfully'));
    }

    public function editSchedule(RecordedCourse $recordedCourse): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $recordedCourse->load('lessons');

        return Inertia::render('Back/Settings/RecordedCourses/Schedule', [
            'recordedCourse' => $this->coursePayload($recordedCourse),
            'readiness' => $this->readiness($recordedCourse),
        ]);
    }

    public function updateSchedule(UpdateRecordedCourseScheduleRequest $request, RecordedCourse $recordedCourse): RedirectResponse
    {
        $validated = $request->validated();
        $recordedCourse->update([
            'unlock_delay_hours' => $validated['unlock_delay_hours'],
            'allowed_weekdays' => self::allowedWeekdaysJson($validated['allowed_weekdays']),
        ]);

        return redirect()
            ->route('back.settings.recorded-courses.schedule.edit', $recordedCourse)
            ->with('success', __('words.saved-successfully'));
    }

    public function enrollments(RecordedCourse $recordedCourse): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $recordedCourse->load([
            'lessons',
            'enrollments.trainee.company',
            'enrollments.lessonProgress',
        ]);

        $lessonsOrdered = $recordedCourse->lessons;
        $lessonsTotal = $lessonsOrdered->count();

        $enrollments = $recordedCourse->enrollments->map(function ($e) use ($lessonsOrdered, $lessonsTotal) {
            $byLessonId = $e->lessonProgress->keyBy('recorded_course_lesson_id');
            $lessonsCompleted = 0;
            $lessonProgress = $lessonsOrdered->map(function (RecordedCourseLesson $lesson) use ($byLessonId, &$lessonsCompleted) {
                $p = $byLessonId->get($lesson->id);
                if ($p?->completed_at !== null) {
                    $lessonsCompleted++;
                }

                return [
                    'lesson_id' => $lesson->id,
                    'title_ar' => $lesson->title_ar,
                    'title_en' => $lesson->title_en ?? '',
                    'unlocked_at' => $p?->unlocked_at?->toIso8601String(),
                    'completed_at' => $p?->completed_at?->toIso8601String(),
                ];
            })->values()->all();

            $progressPercent = $lessonsTotal > 0
                ? (int) round(($lessonsCompleted / $lessonsTotal) * 100)
                : 0;

            $entitlement = 'in_progress';
            if ($e->certificate_status === RecordedCourseEnrollment::CERTIFICATE_STATUS_SENT) {
                $entitlement = 'certificate_sent';
            } elseif ($e->certificate_status === RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL
                || $e->certificate_status === RecordedCourseEnrollment::CERTIFICATE_STATUS_APPROVED
                || $e->certificate_status === RecordedCourseEnrollment::CERTIFICATE_STATUS_FAILED
                || $e->completed_at !== null) {
                $entitlement = 'eligible';
            } elseif ($e->checked_in_at === null) {
                $entitlement = 'not_started';
            }

            return [
                'id' => $e->id,
                'trainee_id' => $e->trainee_id,
                'trainee_name' => $e->trainee?->name,
                'trainee_email' => $e->trainee?->email,
                'company_id' => $e->trainee?->company_id,
                'company_name' => $e->trainee?->company?->name_ar ?: $e->trainee?->company?->name_en,
                'enrolled_at' => $e->enrolled_at?->toIso8601String(),
                'access_link_sent_at' => $e->access_link_sent_at?->toIso8601String(),
                'access_url' => $e->access_token ? $e->accessUrl() : null,
                'checked_in_at' => $e->checked_in_at?->toIso8601String(),
                'checked_out_at' => $e->checked_out_at?->toIso8601String(),
                'completed_at' => $e->completed_at?->toIso8601String(),
                'certificate_status' => $e->certificate_status,
                'delivery_status' => $e->delivery_status,
                'lessons_completed' => $lessonsCompleted,
                'lessons_total' => $lessonsTotal,
                'progress_percent' => $progressPercent,
                'entitlement' => $entitlement,
                'lesson_progress' => $lessonProgress,
            ];
        })->values();

        $companySummaries = $enrollments
            ->filter(fn (array $row) => ! empty($row['company_id']))
            ->groupBy('company_id')
            ->map(function ($rows, $companyId) {
                $first = $rows->first();

                return [
                    'company_id' => $companyId,
                    'company_name' => $first['company_name'] ?? $companyId,
                    'enrolled' => $rows->count(),
                    'checked_in' => $rows->filter(fn (array $r) => ! empty($r['checked_in_at']))->count(),
                    'completed' => $rows->filter(fn (array $r) => ! empty($r['completed_at']))->count(),
                    'eligible' => $rows->filter(fn (array $r) => ($r['entitlement'] ?? '') === 'eligible')->count(),
                    'certificate_sent' => $rows->filter(fn (array $r) => ($r['entitlement'] ?? '') === 'certificate_sent')->count(),
                ];
            })
            ->sortBy('company_name')
            ->values();

        $companyOptions = Company::query()
            ->where('team_id', $recordedCourse->team_id)
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name_ar ?: $c->name_en,
            ])
            ->values();

        return Inertia::render('Back/Settings/RecordedCourses/Enrollments', [
            'recordedCourse' => $this->courseSummary($recordedCourse),
            'readiness' => $this->readiness($recordedCourse),
            'lessons' => $lessonsOrdered->map(fn (RecordedCourseLesson $lesson) => [
                'id' => $lesson->id,
                'title_ar' => $lesson->title_ar,
                'title_en' => $lesson->title_en ?? '',
            ]),
            'enrollments' => $enrollments,
            'companySummaries' => $companySummaries,
            'companies' => $companyOptions,
            'canApproveCertificates' => auth()->user()->can('approve-recorded-course-certificates'),
            'initialFilterCompanyId' => request()->query('company_id'),
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
            ->route('back.settings.recorded-courses.show', $recordedCourse)
            ->with('success', __('words.saved-successfully'));
    }

    public function destroy(RecordedCourse $recordedCourse): RedirectResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $recordedCourse->delete();

        return redirect()->route('back.settings.recorded-courses.index');
    }

    private function courseSummary(RecordedCourse $course): array
    {
        return [
            'id' => $course->id,
            'name_ar' => $course->name_ar,
            'name_en' => $course->name_en,
        ];
    }

    private function coursePayload(RecordedCourse $course): array
    {
        return [
            'id' => $course->id,
            'name_ar' => $course->name_ar,
            'name_en' => $course->name_en,
            'description' => $course->description,
            'unlock_delay_hours' => $course->unlock_delay_hours,
            'allowed_weekdays' => $course->allowed_weekdays ?? [],
        ];
    }

    private function lessonSummary(RecordedCourse $course, RecordedCourseLesson $lesson): array
    {
        $media = $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION);

        return [
            'id' => $lesson->id,
            'sort_order' => $lesson->sort_order,
            'title_ar' => $lesson->title_ar,
            'title_en' => $lesson->title_en ?? '',
            'has_video' => $media !== null,
            'video_file_name' => $media?->name,
            'video_stream_url' => $media
                ? route('back.settings.recorded-courses.lessons.stream', [$course, $lesson])
                : null,
        ];
    }

    private function readiness(RecordedCourse $course): array
    {
        $lessons = $course->lessons;
        $withVideo = $lessons->filter(
            fn (RecordedCourseLesson $lesson) => $lesson->getFirstMedia(RecordedCourseLesson::VIDEO_COLLECTION) !== null
        )->count();

        return [
            'details_complete' => filled($course->name_ar) && filled($course->name_en),
            'schedule_complete' => $course->unlock_delay_hours > 0 && count($course->allowed_weekdays) > 0,
            'lessons_count' => $lessons->count(),
            'lessons_with_video_count' => $withVideo,
            'all_lessons_have_video' => $lessons->count() > 0 && $withVideo === $lessons->count(),
            'ready_for_engineers' => filled($course->name_ar)
                && filled($course->name_en)
                && $course->unlock_delay_hours > 0
                && count($course->allowed_weekdays) > 0
                && $lessons->count() > 0
                && $withVideo === $lessons->count(),
            'enrollments_count' => $course->enrollments->count(),
        ];
    }
}
