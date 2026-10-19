<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Back\RecordedCourseEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordedCourseReminderMail extends Mailable
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

        $mail = $this
            ->subject('تذكير بإكمال الدورة التدريبية — '.$courseName)
            ->markdown('emails.recorded-course-reminder', [
                'traineeName' => $this->enrollment->trainee->name ?? '',
                'courseName' => $courseName,
                'accessUrl' => $this->enrollment->accessUrl(),
                'hasCheckedIn' => $this->enrollment->isCheckedIn(),
            ]);

        $mail->withSwiftMessage(function ($message): void {
            $message->getHeaders()
                ->addTextHeader('X-Mailgun-Variables', json_encode([
                    'recorded_course_enrollment_id' => $this->enrollment->id,
                    'type' => 'recorded_course_reminder',
                ]));
        });

        return $mail;
    }
}
