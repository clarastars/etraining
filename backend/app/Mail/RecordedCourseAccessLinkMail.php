<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Back\RecordedCourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordedCourseAccessLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RecordedCourseEnrollment $enrollment
    ) {
    }

    public function build()
    {
        $this->enrollment->loadMissing([
            'trainee',
            'recordedCourse' => function ($q): void {
                $q->withoutGlobalScopes();
            },
        ]);

        $courseName = $this->enrollment->recordedCourse->name_ar
            ?: $this->enrollment->recordedCourse->name_en;

        return $this
            ->subject('رابط الدورة التدريبية — '.$courseName)
            ->markdown('emails.recorded-course-access-link', [
                'traineeName' => $this->enrollment->trainee->name ?? '',
                'courseName' => $courseName,
                'accessUrl' => $this->enrollment->accessUrl(),
            ]);
    }
}
