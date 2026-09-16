<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\RecordedCourseCertificateMail;
use App\Models\Back\RecordedCourseEnrollment;
use App\Services\RecordedCourseCertificateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendRecordedCourseCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $enrollmentId
    ) {
    }

    public function handle(RecordedCourseCertificateService $certificateService): void
    {
        /** @var RecordedCourseEnrollment|null $enrollment */
        $enrollment = RecordedCourseEnrollment::query()
            ->with([
                'trainee.company',
                'recordedCourse' => function ($q): void {
                    $q->withoutGlobalScopes();
                },
            ])
            ->find($this->enrollmentId);

        if ($enrollment === null || $enrollment->trainee === null || empty($enrollment->trainee->email)) {
            return;
        }

        try {
            $path = $certificateService->generateAndStore($enrollment);
            $enrollment->certificate_path = $path;
            $enrollment->save();

            $mailable = new RecordedCourseCertificateMail($enrollment->id);
            $pending = Mail::to($enrollment->trainee->email);

            $companyEmail = $enrollment->trainee->company->email ?? null;
            if (is_string($companyEmail) && trim($companyEmail) !== '') {
                $pending->cc(trim($companyEmail));
            }

            $pending->send($mailable);

            $enrollment->certificate_status = RecordedCourseEnrollment::CERTIFICATE_STATUS_SENT;
            $enrollment->certificate_sent_at = now();
            $enrollment->delivery_status = null;
            $enrollment->delivered_at = null;
            $enrollment->failed_at = null;
            $enrollment->delivery_failure_reason = null;
            $enrollment->save();
        } catch (Throwable $e) {
            Log::error('Failed to send recorded course certificate', [
                'enrollment_id' => $this->enrollmentId,
                'error' => $e->getMessage(),
            ]);

            $enrollment->certificate_status = RecordedCourseEnrollment::CERTIFICATE_STATUS_FAILED;
            $enrollment->delivery_status = 'failed';
            $enrollment->failed_at = now();
            $enrollment->delivery_failure_reason = $e->getMessage();
            $enrollment->save();

            throw $e;
        }
    }
}
