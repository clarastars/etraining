<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\RecordedCourseAccessLinkMail;
use App\Models\Back\RecordedCourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendRecordedCourseAccessLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $enrollmentId,
        public bool $regenerateToken = false
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

        if ($enrollment === null || $enrollment->trainee === null || empty($enrollment->trainee->email)) {
            return;
        }

        if ($this->regenerateToken) {
            $enrollment->regenerateAccessToken();
        } else {
            $enrollment->ensureAccessToken();
        }

        $enrollment->load([
            'trainee',
            'recordedCourse' => function ($q): void {
                $q->withoutGlobalScopes();
            },
        ]);

        Mail::to($enrollment->trainee->email)
            ->send(new RecordedCourseAccessLinkMail($enrollment));

        $enrollment->access_link_sent_at = now();
        $enrollment->save();
    }
}
