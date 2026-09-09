<?php

declare(strict_types=1);

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Back\RecordedCourse;
use App\Models\Back\RecordedCourseEnrollment;
use Inertia\Inertia;
use Inertia\Response;

class TrainingDisclosureController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->can('manage-recorded-courses'), 403);

        $coursesCount = RecordedCourse::query()->count();
        $enrollmentsCount = RecordedCourseEnrollment::query()->count();
        $checkedInCount = RecordedCourseEnrollment::query()->whereNotNull('checked_in_at')->count();
        $completedCount = RecordedCourseEnrollment::query()->whereNotNull('completed_at')->count();
        $pendingApprovalCount = RecordedCourseEnrollment::query()
            ->where('certificate_status', RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL)
            ->count();
        $certificatesSentCount = RecordedCourseEnrollment::query()
            ->where('certificate_status', RecordedCourseEnrollment::CERTIFICATE_STATUS_SENT)
            ->count();

        $companiesEnrolledCount = (int) RecordedCourseEnrollment::query()
            ->join('trainees', 'trainees.id', '=', 'recorded_course_enrollments.trainee_id')
            ->whereNotNull('trainees.company_id')
            ->selectRaw('COUNT(DISTINCT trainees.company_id) as aggregate')
            ->value('aggregate');

        $courses = RecordedCourse::query()
            ->withCount([
                'enrollments',
                'enrollments as pending_approval_count' => function ($q): void {
                    $q->where('certificate_status', RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL);
                },
                'enrollments as completed_enrollments_count' => function ($q): void {
                    $q->whereNotNull('completed_at');
                },
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (RecordedCourse $course) => [
                'id' => $course->id,
                'name_ar' => $course->name_ar,
                'name_en' => $course->name_en,
                'enrollments_count' => (int) $course->enrollments_count,
                'completed_enrollments_count' => (int) $course->completed_enrollments_count,
                'pending_approval_count' => (int) $course->pending_approval_count,
            ]);

        $pendingApprovals = RecordedCourseEnrollment::query()
            ->with([
                'trainee:id,name,email,company_id',
                'trainee.company:id,name_ar,name_en',
                'recordedCourse' => function ($q): void {
                    $q->withoutGlobalScopes()->select('id', 'name_ar', 'name_en');
                },
            ])
            ->where('certificate_status', RecordedCourseEnrollment::CERTIFICATE_STATUS_PENDING_APPROVAL)
            ->latest('completed_at')
            ->limit(15)
            ->get()
            ->map(fn (RecordedCourseEnrollment $e) => [
                'id' => $e->id,
                'trainee_name' => $e->trainee?->name,
                'trainee_email' => $e->trainee?->email,
                'company_name' => $e->trainee?->company?->name_ar ?: $e->trainee?->company?->name_en,
                'course_id' => $e->recorded_course_id,
                'course_name' => $e->recordedCourse?->name_ar ?: $e->recordedCourse?->name_en,
                'completed_at' => $e->completed_at?->toIso8601String(),
            ]);

        return Inertia::render('Back/TrainingDisclosure/Index', [
            'stats' => [
                'courses' => $coursesCount,
                'enrollments' => $enrollmentsCount,
                'companies' => $companiesEnrolledCount,
                'checked_in' => $checkedInCount,
                'completed' => $completedCount,
                'pending_approval' => $pendingApprovalCount,
                'certificates_sent' => $certificatesSentCount,
            ],
            'courses' => $courses,
            'pendingApprovals' => $pendingApprovals,
            'canApproveCertificates' => auth()->user()->can('approve-recorded-course-certificates'),
        ]);
    }
}
