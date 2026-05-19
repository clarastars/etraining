<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\JasarahCenterCertificateMail;
use App\Models\Back\JasarahCenterCertificateRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendIndividualJasarahCenterCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $maxExceptions = 1;

    protected int $rowId;

    public function __construct(int $rowId)
    {
        $this->rowId = $rowId;
    }

    public function handle(): void
    {
        $row = JasarahCenterCertificateRow::with(['trainee', 'jasarahCenterCertificate.course'])->find($this->rowId);

        if (!$row || $row->status !== JasarahCenterCertificateRow::STATUS_PENDING || !$row->pdf_path) {
            return;
        }

        try {
            $pdfContent = Storage::disk('s3')->get($row->pdf_path);

            if (!$pdfContent) {
                throw new \Exception('PDF content is empty or could not be retrieved from S3');
            }

            $courseName = $row->jasarahCenterCertificate->course->name_en
                ?? $row->jasarahCenterCertificate->course->name_ar
                ?? '';

            Mail::to($row->trainee->email)
                ->bcc(['shafiqal-shaar@adv-line.com', 'mashael.a@hadaf-hq.com'])
                ->send(new JasarahCenterCertificateMail(
                    $pdfContent,
                    basename($row->pdf_path),
                    $row->trainee,
                    $courseName,
                    $this->rowId
                ));

            $row->update([
                'sent_at' => now(),
                'status' => JasarahCenterCertificateRow::STATUS_SENT,
                'delivery_status' => JasarahCenterCertificateRow::DELIVERY_STATUS_PENDING,
            ]);

            $row->jasarahCenterCertificate?->checkAndUpdateCompletionStatus();
        } catch (\Exception $e) {
            $row->update([
                'status' => JasarahCenterCertificateRow::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'delivery_status' => JasarahCenterCertificateRow::DELIVERY_STATUS_FAILED,
                'failed_at' => now(),
                'delivery_failure_reason' => $e->getMessage(),
            ]);

            Log::error('Failed to send Jasarah Center certificate for row ' . $this->rowId . ': ' . $e->getMessage());
        }
    }
}
