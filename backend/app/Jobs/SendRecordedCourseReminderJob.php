<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\RecordedCourseReminderMail;
use App\Models\Back\RecordedCourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRecordedCourseReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $enrollmentId
    ) {
    }

    public function handle(): void
    {
        /** @var RecordedCourseEnrollment|null $enrollment */
        $enrollment = RecordedCourseEnrollment::query()
            ->with(['trainee', 'recordedCourse' => function ($q): void {
                $q->withoutGlobalScopes();
            }])
            ->find($this->enrollmentId);

        if ($enrollment === null
            || $enrollment->completed_at !== null
            || $enrollment->trainee === null
            || empty($enrollment->trainee->email)
            || $enrollment->delivery_status === 'failed'
            || $enrollment->reminder_delivery_status === 'failed') {
            return;
        }

        $enrollment->ensureAccessToken();

        Mail::to($enrollment->trainee->email)
            ->send(new RecordedCourseReminderMail($enrollment));

        $enrollment->last_reminder_sent_at = now();
        $enrollment->reminder_delivery_status = 'pending';
        $enrollment->reminder_failure_reason = null;
        $enrollment->save();
    }
}
