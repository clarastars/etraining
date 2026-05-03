<?php

declare(strict_types=1);

namespace App\Models\Back;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RecordedCourseEnrollment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'recorded_course_enrollments';

    protected $fillable = [
        'team_id',
        'trainee_id',
        'recorded_course_id',
        'enrolled_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (RecordedCourseEnrollment $model): void {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Trainee::class);
    }

    public function recordedCourse(): BelongsTo
    {
        return $this->belongsTo(RecordedCourse::class, 'recorded_course_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(RecordedCourseLessonProgress::class, 'recorded_course_enrollment_id');
    }
}
