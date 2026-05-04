<?php

declare(strict_types=1);

namespace App\Models\Back;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RecordedCourseLesson extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const VIDEO_COLLECTION = 'video';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recorded_course_id',
        'sort_order',
        'title_ar',
        'title_en',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (RecordedCourseLesson $model): void {
            $model->{$model->getKeyName()} = (string) Str::uuid();
        });
        static::deleting(function (RecordedCourseLesson $model): void {
            $model->clearMediaCollection(self::VIDEO_COLLECTION);
        });
    }

    public function recordedCourse(): BelongsTo
    {
        return $this->belongsTo(RecordedCourse::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(RecordedCourseLessonProgress::class, 'recorded_course_lesson_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::VIDEO_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/webm', 'video/quicktime']);
    }

    public function attachVideo(UploadedFile $file): Media
    {
        $this->clearMediaCollection(self::VIDEO_COLLECTION);

        $teamId = auth()->user()->currentTeam()->first()->id;

        return $this->addMedia($file)
            ->usingFileName($file->hashName())
            ->withAttributes([
                'team_id' => $teamId,
            ])
            ->toMediaCollection(self::VIDEO_COLLECTION);
    }

    /**
     * Attach a fully assembled upload from disk (e.g. chunked upload temp file) and remove the source file.
     */
    public function attachVideoFromAssembledFile(string $absolutePath, string $originalFilename): Media
    {
        $this->clearMediaCollection(self::VIDEO_COLLECTION);

        $teamId = auth()->user()->currentTeam()->first()->id;
        $storedName = $this->storedFileNameFromOriginal($originalFilename);

        try {
            return $this->addMedia($absolutePath)
                ->usingFileName($storedName)
                ->withAttributes([
                    'team_id' => $teamId,
                ])
                ->toMediaCollection(self::VIDEO_COLLECTION);
        } finally {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function storedFileNameFromOriginal(string $originalFilename): string
    {
        $ext = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $allowedExt = ['mp4', 'webm', 'mov'];

        if ($ext !== '' && in_array($ext, $allowedExt, true)) {
            return Str::random(40).'.'.$ext;
        }

        return Str::random(40).'.mp4';
    }

    public function getVideoMediaAttribute(): ?Media
    {
        return $this->getFirstMedia(self::VIDEO_COLLECTION);
    }
}
