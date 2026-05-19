<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class JasarahCenterCertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $pdfContent;
    protected $filename;
    protected $trainee;
    protected $courseName;
    protected $rowId;

    public function __construct($pdfContent, $filename, $trainee, $courseName, $rowId = null)
    {
        $this->pdfContent = $pdfContent;
        $this->filename = $filename;
        $this->trainee = $trainee;
        $this->courseName = $courseName;
        $this->rowId = $rowId;
    }

    public function build()
    {
        $mail = $this->from('certificates@mg.noreplycenter.com')
            ->subject('Notice of Attendance')
            ->markdown('emails.jasarah-center-certificate', [
                'trainee' => $this->trainee,
                'courseName' => $this->courseName,
            ]);

        if ($this->rowId) {
            $mail->withSwiftMessage(function ($message) {
                $message->getHeaders()
                    ->addTextHeader('X-Mailgun-Variables', json_encode([
                        'jasarah_center_certificate_row_id' => $this->rowId,
                        'type' => 'jasarah_center_certificate',
                    ]));
            });
        }

        if ($this->pdfContent && $this->filename) {
            $mail->attachData($this->pdfContent, $this->filename, ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
