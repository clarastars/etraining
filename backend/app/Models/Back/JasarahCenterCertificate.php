<?php

declare(strict_types=1);

namespace App\Models\Back;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JasarahCenterCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'status',
        'total_rows',
        'matched_count',
        'unmatched_count',
        'sent_count',
        'failed_count',
        'csv_path',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_READY_TO_SEND = 'ready_to_send';
    const STATUS_SENDING = 'sending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function rows()
    {
        return $this->hasMany(JasarahCenterCertificateRow::class);
    }

    public function matchedRows()
    {
        return $this->hasMany(JasarahCenterCertificateRow::class)->whereNotNull('trainee_id');
    }

    public function unmatchedRows()
    {
        return $this->hasMany(JasarahCenterCertificateRow::class)
            ->whereNull('trainee_id')
            ->where('status', '!=', JasarahCenterCertificateRow::STATUS_FAILED);
    }

    public function failedRows()
    {
        return $this->hasMany(JasarahCenterCertificateRow::class)
            ->where('status', JasarahCenterCertificateRow::STATUS_FAILED);
    }

    public function checkAndUpdateCompletionStatus(): bool
    {
        $totalRows = $this->rows()->count();
        $sentRows = $this->rows()->where('status', JasarahCenterCertificateRow::STATUS_SENT)->count();
        $failedRows = $this->rows()->where('status', JasarahCenterCertificateRow::STATUS_FAILED)->count();

        if ($sentRows + $failedRows >= $totalRows) {
            $this->update([
                'status' => $failedRows > 0 ? self::STATUS_FAILED : self::STATUS_SENT,
                'completed_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($certificate) {
            foreach ($certificate->rows as $row) {
                if ($row->pdf_path) {
                    \Storage::disk('s3')->delete($row->pdf_path);
                }
            }

            if ($certificate->csv_path) {
                \Storage::disk('s3')->delete($certificate->csv_path);
            }

            \Storage::disk('s3')->deleteDirectory('jasarah-center-certificates/' . $certificate->id);
        });
    }
}
