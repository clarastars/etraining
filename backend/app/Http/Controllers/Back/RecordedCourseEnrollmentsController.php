<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\Back\BulkStoreRecordedCourseEnrollmentRequest;
use App\Http\Requests\Back\StoreRecordedCourseEnrollmentRequest;
use App\Jobs\SendRecordedCourseAccessLinkJob;
use App\Jobs\SendRecordedCourseCertificateJob;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\Trainee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordedCourseEnrollmentsController extends Controller
{
    public function companyTrainees(Request $request, RecordedCourse $recordedCourse): JsonResponse
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $companyId = $request->query('company_id');
        if (! is_string($companyId) || $companyId === '') {
            return response()->json(['trainees' => []]);
        }

        $enrolledIds = RecordedCourseEnrollment::query()
            ->where('recorded_course_id', $recordedCourse->id)
            ->whereIn('trainee_id', function ($q) use ($companyId, $recordedCourse): void {
                $q->select('id')
                    ->from('trainees')
                    ->where('company_id', $companyId)
                    ->where('team_id', $recordedCourse->team_id);
            })
            ->pluck('trainee_id')
            ->all();

        $enrolledLookup = array_flip($enrolledIds);

        $trainees = Trainee::query()
            ->where('team_id', $recordedCourse->team_id)
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (Trainee $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'already_enrolled' => isset($enrolledLookup[$t->id]),
            ])
            ->values();

        return response()->json(['trainees' => $trainees]);
    }

    public function store(StoreRecordedCourseEnrollmentRequest $request, RecordedCourse $recordedCourse): RedirectResponse
    {
        $validated = $request->validated();
        $trainee = Trainee::query()->findOrFail($validated['trainee_id']);

        if ($trainee->team_id !== $recordedCourse->team_id) {
            throw ValidationException::withMessages([
                'trainee_id' => [__('words.recorded-course-enrollment-team-mismatch')],
            ]);
        }

        $existing = RecordedCourseEnrollment::query()
            ->where('trainee_id', $trainee->id)
            ->where('recorded_course_id', $recordedCourse->id)
            ->first();

        if ($existing !== null) {
            return redirect()
                ->route('back.settings.recorded-courses.enrollments.index', $recordedCourse)
                ->with('warning', __('words.recorded-course-enrollment-already-exists'));
        }

        $enrollment = RecordedCourseEnrollment::query()->create([
            'team_id' => $trainee->team_id,
            'trainee_id' => $trainee->id,
            'recorded_course_id' => $recordedCourse->id,
            'enrolled_at' => now(),
        ]);

        SendRecordedCourseAccessLinkJob::dispatch($enrollment->id);

        return redirect()
            ->route('back.settings.recorded-courses.enrollments.index', $recordedCourse)
            ->with('success', __('words.recorded-course-enrollment-created'));
    }

    public function bulkStore(
        BulkStoreRecordedCourseEnrollmentRequest $request,
        RecordedCourse $recordedCourse
    ): RedirectResponse {
        $validated = $request->validated();
        $traineeIds = array_values(array_unique($validated['trainee_ids']));

        $trainees = Trainee::query()
            ->whereIn('id', $traineeIds)
            ->where('team_id', $recordedCourse->team_id)
            ->get();

        if ($trainees->count() !== count($traineeIds)) {
            throw ValidationException::withMessages([
                'trainee_ids' => [__('words.recorded-course-enrollment-team-mismatch')],
            ]);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($trainees, $recordedCourse, &$created, &$skipped): void {
            foreach ($trainees as $trainee) {
                $exists = RecordedCourseEnrollment::query()
                    ->where('trainee_id', $trainee->id)
                    ->where('recorded_course_id', $recordedCourse->id)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $enrollment = RecordedCourseEnrollment::query()->create([
                    'team_id' => $trainee->team_id,
                    'trainee_id' => $trainee->id,
                    'recorded_course_id' => $recordedCourse->id,
                    'enrolled_at' => now(),
                ]);

                SendRecordedCourseAccessLinkJob::dispatch($enrollment->id);
                $created++;
            }
        });

        $message = __('words.recorded-course-bulk-enrolled', [
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return redirect()
            ->route('back.settings.recorded-courses.enrollments.index', $recordedCourse)
            ->with('success', $message);
    }

    public function resendAccessLink(
        RecordedCourse $recordedCourse,
        RecordedCourseEnrollment $enrollment
    ): RedirectResponse {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);
        $this->assertEnrollmentBelongsToCourse($recordedCourse, $enrollment);

        SendRecordedCourseAccessLinkJob::dispatch($enrollment->id, true);

        return redirect()
            ->route('back.settings.recorded-courses.enrollments.index', $recordedCourse)
            ->with('success', __('words.recorded-course-access-link-resent'));
    }

    public function approveCertificate(
        RecordedCourse $recordedCourse,
        RecordedCourseEnrollment $enrollment
    ): RedirectResponse {
        abort_unless(auth()->user()->can('approve-recorded-course-certificates'), 403);
        $this->assertEnrollmentBelongsToCourse($recordedCourse, $enrollment);

        if ($enrollment->certificate_status !== RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL
            && $enrollment->certificate_status !== RecordedCourseEnrollment::CERTIFICATE_STATUS_FAILED) {
            return redirect()
                ->back()
                ->with('warning', __('words.recorded-course-certificate-not-pending'));
        }

        $enrollment->certificate_status = RecordedCourseEnrollment::CERTIFICATE_STATUS_APPROVED;
        $enrollment->certificate_approved_at = now();
        $enrollment->certificate_approved_by = auth()->id();
        $enrollment->save();

        SendRecordedCourseCertificateJob::dispatch($enrollment->id);

        return redirect()
            ->back()
            ->with('success', __('words.recorded-course-certificate-approved-queued'));
    }

    private function assertEnrollmentBelongsToCourse(
        RecordedCourse $recordedCourse,
        RecordedCourseEnrollment $enrollment
    ): void {
        if ($enrollment->recorded_course_id !== $recordedCourse->id) {
            abort(404);
        }
    }
}
