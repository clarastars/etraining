<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Back\JasarahCenterCertificate;
use App\Models\Back\JasarahCenterCertificateRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendJasarahCenterCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $maxExceptions = 1;

    protected JasarahCenterCertificate $certificate;

    public function __construct(JasarahCenterCertificate $certificate)
    {
        $this->certificate = $certificate;
    }

    public function handle(): void
    {
        $start = now();

        foreach ($this->certificate->rows()
            ->whereNotNull('trainee_id')
            ->whereNotNull('pdf_path')
            ->where('status', JasarahCenterCertificateRow::STATUS_PENDING)
            ->get() as $row) {
            dispatch(new SendIndividualJasarahCenterCertificateJob($row->id));
        }

        Mail::raw(
            "The Jasarah Center certificate process has been queued\ncourse: {$this->certificate->course_id}\nstarted_at: {$start}\nqueued_at: " . now(),
            function ($message) {
                $message->to(['shafiqalshaar@adv-line.com', 'mashael.a@hadaf-hq.com'])
                    ->subject('Jasarah Center Certificate Process Queued');
            }
        );
    }
}
