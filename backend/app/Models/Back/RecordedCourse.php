<?php

declare(strict_types=1);

namespace App\Models\Back;

use App\Scope\TeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RecordedCourse extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name_ar',
        'name_en',
        'description',
        'unlock_delay_hours',
        'allowed_weekdays',
    ];

    protected $casts = [
        'unlock_delay_hours' => 'integer',
    ];

    /**
     * Always persist JSON text: Laravel 8's Query\\Builder::insert() treats the row as a
     * "batch" when reset($attributes) is an array, then calls ksort() on each value — if
     * allowed_weekdays were still a PHP array it breaks with "ksort(): Argument #1 must be array, string given".
     */
    public function getAllowedWeekdaysAttribute(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setAllowedWeekdaysAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $this->attributes['allowed_weekdays'] = json_encode(array_values($value));

            return;
        }

        if (is_string($value)) {
            $this->attributes['allowed_weekdays'] = $value;

            return;
        }

        $this->attributes['allowed_weekdays'] = '[]';
    }

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new TeamScope());
        static::creating(function (RecordedCourse $model): void {
            $model->{$model->getKeyName()} = (string) Str::uuid();
            if (auth()->user()) {
                $model->team_id = auth()->user()->currentTeam()->first()->id;
            }
        });
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(RecordedCourseLesson::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(RecordedCourseEnrollment::class, 'recorded_course_id');
    }
}
