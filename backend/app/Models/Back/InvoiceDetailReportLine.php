<?php

declare(strict_types=1);

namespace App\Models\Back;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InvoiceDetailReportLine extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'manual_start_date',
        'end_date',
        'full_salary',
        'full_reward',
        'full_fees',
        'full_refund',
    ];

    protected $casts = [
        'manual_start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'full_salary' => 'float',
        'full_reward' => 'float',
        'full_fees' => 'float',
        'full_refund' => 'float',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
