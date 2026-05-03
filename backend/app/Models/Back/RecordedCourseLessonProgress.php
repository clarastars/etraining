<?php

declare(strict_types=1);

namespace App\Models\Back;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RecordedCourseLessonProgress extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'recorded_course_lesson_progress';

    protected $fillable = [
        'recorded_course_enrollment_id',
        'recorded_course_lesson_id',
        'unlocked_at',
        'completed_at',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (RecordedCourseLessonProgress $model): void {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(RecordedCourseEnrollment::class, 'recorded_course_enrollment_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(RecordedCourseLesson::class, 'recorded_course_lesson_id');
    }
}
