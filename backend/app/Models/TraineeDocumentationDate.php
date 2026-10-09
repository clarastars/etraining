<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraineeDocumentationDate extends Model
{
    protected $fillable = [
        'identity_number',
        'documented_on',
        'source_sheet',
        'synced_at',
    ];

    protected $casts = [
        'documented_on' => 'date:Y-m-d',
        'synced_at' => 'datetime',
    ];
}
