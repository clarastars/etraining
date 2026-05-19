<?php

declare(strict_types=1);

namespace App\Models\Back;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JasarahCenterCertificateRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'jasarah_center_certificate_id',
        'trainee_id',
        'trainee_certificate_id',
        'row_key',
        'identity_number',
        'trainee_name_en',
        'pdf_path',
        'sent_at',
        'status',
        'error_message',
        'mailgun_message_id',
        'delivered_at',
        'failed_at',
        'delivery_failure_reason',
        'delivery_status',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';

    const DELIVERY_STATUS_PENDING = 'pending';
    const DELIVERY_STATUS_DELIVERED = 'delivered';
    const DELIVERY_STATUS_FAILED = 'failed';

    public function jasarahCenterCertificate()
    {
        return $this->belongsTo(JasarahCenterCertificate::class);
    }

    public function trainee()
    {
        return $this->belongsTo(Trainee::class)->withTrashed();
    }

    public function traineeCertificate()
    {
        return $this->belongsTo(TraineeCertificate::class);
    }
}
