<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Back\JasarahCenterCertificate;
use App\Models\Back\JasarahCenterCertificateRow;
use App\Services\JasarahCenterCertificateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessJasarahCenterCertificateFinalizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 3600;

    protected int $certificateId;

    public function __construct(int $certificateId)
    {
        $this->certificateId = $certificateId;
    }

    public function handle(JasarahCenterCertificateService $service): void
    {
        $certificate = JasarahCenterCertificate::with('course')->find($this->certificateId);

        if (!$certificate) {
            return;
        }

        $rows = $certificate->rows()
            ->whereNotNull('trainee_id')
            ->where('status', JasarahCenterCertificateRow::STATUS_PENDING)
            ->get();

        foreach ($rows as $row) {
            try {
                $service->generateAndStorePdf($row, $certificate->course);
            } catch (\Exception $e) {
                Log::error('Failed to generate Jasarah Center certificate PDF', [
                    'row_id' => $row->id,
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage(),
                ]);

                $row->update([
                    'status' => JasarahCenterCertificateRow::STATUS_FAILED,
                    'error_message' => 'PDF generation failed: ' . $e->getMessage(),
                ]);
            }
        }

        $service->updateImportCounts($certificate);

        $hasSendableRows = $certificate->rows()
            ->whereNotNull('trainee_id')
            ->whereNotNull('pdf_path')
            ->where('status', JasarahCenterCertificateRow::STATUS_PENDING)
            ->exists();

        $certificate->update([
            'status' => $hasSendableRows
                ? JasarahCenterCertificate::STATUS_READY_TO_SEND
                : JasarahCenterCertificate::STATUS_FAILED,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessJasarahCenterCertificateFinalizeJob failed', [
            'certificate_id' => $this->certificateId,
            'error' => $exception->getMessage(),
        ]);

        JasarahCenterCertificate::where('id', $this->certificateId)->update([
            'status' => JasarahCenterCertificate::STATUS_FAILED,
            'completed_at' => now(),
        ]);
    }
}
