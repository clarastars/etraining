<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Back\RecordedCourseEnrollment;
use App\Services\RecordedCourseCertificateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Queue\SerializesModels;

class RecordedCourseCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $enrollmentId
    ) {
    }

    public function build()
    {
        /** @var RecordedCourseEnrollment $enrollment */
        $enrollment = RecordedCourseEnrollment::query()
            ->with([
                'trainee',
                'recordedCourse' => function ($q): void {
                    $q->withoutGlobalScopes();
                },
            ])
            ->findOrFail($this->enrollmentId);

        $courseName = $enrollment->recordedCourse->name_ar
            ?: $enrollment->recordedCourse->name_en;
        $recipientName = $enrollment->trainee->name ?? '';
        $pdfContent = app(RecordedCourseCertificateService::class)->pdfBinary($enrollment);
        $filename = $enrollment->id.'-recorded-course-cert.pdf';

        $mail = $this
            ->subject('شهادة تدريبية — '.$courseName.' — '.$recipientName)
            ->markdown('emails.recorded-course-certificate', [
                'recipientName' => $recipientName,
                'courseName' => $courseName,
            ])
            ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);

        $mail->withSwiftMessage(function ($message) use ($enrollment) {
            $message->getHeaders()
                ->addTextHeader('X-Mailgun-Variables', json_encode([
                    'recorded_course_enrollment_id' => $enrollment->id,
                    'type' => 'recorded_course_certificate',
                ]));
        });

        return $mail;
    }

    protected function buildMarkdownView(): array
    {
        $markdown = new Markdown(app(ViewFactory::class), [
            'theme' => config('mail.markdown.theme', 'default'),
            'paths' => [
                resource_path('views/vendor/mail-ltr'),
            ],
        ]);

        $data = $this->buildViewData();

        return [
            'html' => $markdown->render($this->markdown, $data),
            'text' => $markdown->renderText($this->markdown, $data),
        ];
    }
}
