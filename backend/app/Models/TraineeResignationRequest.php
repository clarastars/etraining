<?php

namespace App\Models;

use App\Models\Back\Trainee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TraineeResignationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'trainee_id',
        'contact_phone',
        'status',
        'admin_notes',
        'processed_at'
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function trainee()
    {
        return $this->belongsTo(Trainee::class);
    }

    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'pending' => 'تم',
            'approved' => 'موافق عليه',
            'rejected' => 'مرفوض',
            default => 'غير محدد'
        };
    }
}
