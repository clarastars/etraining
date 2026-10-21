<?php

declare(strict_types=1);

namespace App\Models\Back;

use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RecordedCourseEnrollment extends Model
{
    public const CERTIFICATE_STATUS_NONE = 'none';

    public const CERTIFICATE_STATUS_PENDING_APPROVAL = 'pending_approval';

    public const CERTIFICATE_STATUS_APPROVED = 'approved';

    public const CERTIFICATE_STATUS_SENT = 'sent';

    public const CERTIFICATE_STATUS_FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'recorded_course_enrollments';

    protected $fillable = [
        'team_id',
        'trainee_id',
        'recorded_course_id',
        'enrolled_at',
        'access_token',
        'access_link_sent_at',
        'checked_in_at',
        'checked_out_at',
        'completed_at',
        'certificate_status',
        'certificate_approved_at',
        'certificate_approved_by',
        'certificate_sent_at',
        'certificate_path',
        'mailgun_message_id',
        'delivery_status',
        'delivered_at',
        'failed_at',
        'delivery_failure_reason',
        'last_reminder_sent_at',
        'reminder_delivery_status',
        'reminder_failure_reason',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'access_link_sent_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'completed_at' => 'datetime',
        'certificate_approved_at' => 'datetime',
        'certificate_sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (RecordedCourseEnrollment $model): void {
            $model->{$model->getKeyName()} = (string) Str::uuid();
            if (empty($model->access_token)) {
                $model->access_token = self::generateAccessToken();
            }
            if (empty($model->certificate_status)) {
                $model->certificate_status = self::CERTIFICATE_STATUS_NONE;
            }
        });
    }

    public static function generateAccessToken(): string
    {
        return Str::random(48);
    }

    public function ensureAccessToken(): string
    {
        if ($this->access_token === null || $this->access_token === '') {
            $this->access_token = self::generateAccessToken();
            $this->save();
        }

        return $this->access_token;
    }

    public function regenerateAccessToken(): string
    {
        $this->access_token = self::generateAccessToken();
        $this->save();

        return $this->access_token;
    }

    public function accessUrl(): string
    {
        return url('/ar/recorded-courses/access/'.$this->ensureAccessToken());
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function certificateEntitlement(): string
    {
        if ($this->certificate_status === self::CERTIFICATE_STATUS_SENT) {
            return 'certificate_sent';
        }

        if ($this->certificate_status === self::CERTIFICATE_STATUS_PENDING_APPROVAL
            || $this->certificate_status === self::CERTIFICATE_STATUS_APPROVED
            || $this->certificate_status === self::CERTIFICATE_STATUS_FAILED
            || $this->completed_at !== null) {
            return 'eligible';
        }

        if ($this->checked_in_at === null) {
            return 'not_started';
        }

        return 'in_progress';
    }

    /**
     * Invited learners who have not finished the course, and whose last invite or reminder is older than the interval.
     *
     * @param  Builder<RecordedCourseEnrollment>  $query
     * @return Builder<RecordedCourseEnrollment>
     */
    public function scopeDueForCompletionReminder(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now = $now ?? now();
        $cutoff = $now->copy()->subHours((int) config('training-disclosure.reminder_interval_hours', 12));

        return $query
            ->whereNull('completed_at')
            ->whereNotNull('access_link_sent_at')
            ->where('access_link_sent_at', '<=', $cutoff)
            ->where(function (Builder $q) use ($cutoff): void {
                $q->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<=', $cutoff);
            })
            ->where(function (Builder $q): void {
                $q->whereNull('delivery_status')
                    ->orWhere('delivery_status', '!=', 'failed');
            })
            ->where(function (Builder $q): void {
                $q->whereNull('reminder_delivery_status')
                    ->orWhere('reminder_delivery_status', '!=', 'failed');
            })
            ->whereHas('trainee', function (Builder $q): void {
                $q->whereNotNull('email')->where('email', '!=', '');
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certificate_approved_by');
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(RecordedCourseLessonProgress::class, 'recorded_course_enrollment_id');
    }
};
