<?php

namespace App\Models\Back;

use App\Events\TraineeAttachedToCompany;
use App\Jobs\VerifyPhoneOwnershipJob;
use App\Models\Back\TraineeAgreement;
use App\Models\City;
use App\Models\EducationalLevel;
use App\Models\MaritalStatus;
use App\Models\SearchableLabels;
use App\Models\Team;
use App\Models\User;
use App\Services\TraineeCompanyMovementService;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JamesMills\LaravelTimezone\Facades\Timezone;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Trainee extends Model implements HasMedia, SearchableLabels, Auditable
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;
    use InteractsWithMedia;
    use Notifiable;
    use \OwenIt\Auditing\Auditable;

    const SEARCHABLE_FIELDS = ['id', 'identity_number', 'phone', 'name', 'email'];

    const STATUS_PENDING_UPLOADING_FILES = 0;
    const STATUS_PENDING_APPROVAL = 1;
    const STATUS_APPROVED = 2;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'instructor_id',
        'company_id',
        'email',
        'name',
        'english_name',
        'identity_number',
        'birthday',
        'educational_level_id',
        'city_id',
        'marital_status_id',
        'children_count',
        'phone',
        'phone_additional',
        'national_address',
        'deleted_remark',
        'trainee_group_id',
        'national_address',
        'bill_from_date',
        'linked_date',
        'trainee_message',
        'job_number',
        'zoho_contract_id',
        'zoho_contract_status',
        'zoho_sign_date',
        'team_id',
        'is_engineer',

    ];

    protected $dates = [
        'bill_from_date',
        'linked_date',
    ];

    protected $casts = [
        'deleted_at' => 'datetime:Y-m-d',
        'ignore_attendance' => 'boolean',
        'dont_edit_notice' => 'boolean',
        'is_engineer' => 'boolean',
    ];

    protected $appends = [
        'trainee_group_object',
        'identity_copy_url',
        'qualification_copy_url',
        'bank_account_copy_url',
        'national_address_copy_url',
        'cv_url',
        'gosi_certificate_copy_url',
        'qiwa_contract_copy_url',
        'name_selectable',
        'show_url',
        'created_at_date',
        'is_pending_uploading_files',
        'is_pending_approval',
        'is_approved',
        'resource_label',
        'resource_type',
        'deleted_at_timezone',
        'created_at_timezone',
        'clean_phone',
        'company_name',
        'bill_from_date_formatted',
        'linked_date_formatted',
        'has_outstanding_amount',
        'clean_identity_number',
        'whatsapp_link',
        'clean_phone_additional',
        'phone_ownership_status',
    ];

    protected static function boot(): void
    {
        parent::boot();
        // static::addGlobalScope(new TeamScope());

        static::creating(function ($model) {
            $model->{$model->getKeyName()} = (string) Str::uuid();
            if (auth()->user()) {
                $model->team_id = auth()->user()->currentTeam()->first()->id;
            }
        });

        static::created(function ($model) {
            if (app()->isProduction() && config('yakeen.enabled', false)) {
                VerifyPhoneOwnershipJob::dispatchSync($model->id);
            }
        });

        static::updating(function ($model) {

            if ($model->isDirty('company_id')) {
                TraineeAttachedToCompany::dispatch($model->id, $model->company_id);
                app()->make(TraineeCompanyMovementService::class)
                    ->recordMovement($model->id, $model->company_id, $model->getOriginal('company_id'));
            }

            //$isFinanceUser = (Str::contains('finance', optional(optional(auth()->user())->roles()->first())->name) || Str::contains('chasers', optional(optional(auth()->user())->roles()->first())->name));
            //if ($companyChanged && $model->company_id && !$isFinanceUser) {
            //    $model->notify(new AssignedToCompanyTraineeNotification());
            //}
        });

        static::updated(function ($model) {
            if ($model->isDirty('phone') && config('yakeen.enabled', false)) {
                VerifyPhoneOwnershipJob::dispatch($model->id);
            }
            
            // Log any change to instructor_id (catches changes from foreign key constraints, direct updates, etc.)
            if ($model->isDirty('instructor_id')) {
                $oldInstructorId = $model->getOriginal('instructor_id');
                $newInstructorId = $model->instructor_id;
                
                // Get stack trace to identify the caller
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
                $caller = null;
                foreach ($backtrace as $trace) {
                    if (isset($trace['file']) && 
                        !str_contains($trace['file'], 'Trainee.php') && 
                        !str_contains($trace['file'], 'vendor')) {
                        $caller = [
                            'file' => $trace['file'] ?? null,
                            'line' => $trace['line'] ?? null,
                            'function' => $trace['function'] ?? null,
                            'class' => $trace['class'] ?? null,
                        ];
                        break;
                    }
                }
                
                Log::info('INSTRUCTOR_ID_CHANGED: Trainee Model Observer - Generic instructor_id change detected', [
                    'trainee_id' => $model->id,
                    'trainee_name' => $model->name,
                    'old_instructor_id' => $oldInstructorId,
                    'new_instructor_id' => $newInstructorId,
                    'reason' => 'تغيير instructor_id تم اكتشافه من Trainee Model Observer - قد يكون من foreign key constraint أو update مباشر',
                    'location' => __FILE__ . ':' . __LINE__,
                    'method' => 'Trainee::boot() - updated event',
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name ?? null,
                    'caller' => $caller,
                    'changed_attributes' => $model->getChanges(),
                    'note' => 'هذا الـ log يلتقط أي تغيير في instructor_id من أي مصدر. تحقق من الـ logs الأخرى لمعرفة السبب المحدد.',
                ]);
            }
        });

        static::restoring(function($model) {
            $model->posted_at = null;
        });
    }

    /**
     * Get the user's preferred locale.
     *
     * @return string
     */
    public function preferredLocale()
    {
        return optional($this->user)->locale;
    }

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function educational_level()
    {
        return $this->belongsTo(EducationalLevel::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function marital_status()
    {
        return $this->belongsTo(MaritalStatus::class);
    }

    public function trainee_group()
    {
        return $this->belongsTo(TraineeGroup::class);
    }

    public function attendanceReportRecords()
    {
        return $this->hasMany(AttendanceReportRecord::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deleted_by()
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function trainee_certificate_imports()
    {
        return $this->hasMany(CertificatesImport::class);
    }

    public function trainee_certificates()
    {
        return $this->hasMany(TraineeCertificate::class);
    }

    public function custom_certificates()
    {
        return $this->hasMany(TraineeCustomCertificate::class);
    }

    public function uk_certificates()
    {
        return $this->hasMany(UkCertificateRow::class);
    }

    public function jasarah_center_certificate_rows()
    {
        return $this->hasMany(JasarahCenterCertificateRow::class);
    }

    public function scopeResponsibleToTeach($q)
    {
        return $q->where('instructor_id', auth()->user()->instructor->id);
    }

    /**
     * Ones who have completed their application.
     *
     * @param $q
     * @return mixed
     */
    public function scopeCandidates($q)
    {
        return $q->where('status', Trainee::STATUS_PENDING_APPROVAL);
    }

    /**
     * Completed their application + approved by center.
     *
     * @param $q
     * @return mixed
     */
    public function scopeApproved($q)
    {
        return $q->where('status', Trainee::STATUS_APPROVED);
    }

    /**
     * Haven't completed their application.
     *
     * @param $q
     * @return mixed
     */
    public function scopeIncomplete($q)
    {
        return $q->where('status', Trainee::STATUS_PENDING_UPLOADING_FILES)
            ->orWhere('status', null);
    }

    /**
     * Upload scan(s) of the documents.
     *
     * @param $file
     * @param $folder
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    public function uploadToFolder($file, $folder)
    {
        return $this->addMedia($file)
            ->usingFileName($file->hashName())
            ->withAttributes([
                'team_id' => $this->team_id,
            ])
            ->toMediaCollection($folder);
    }

    public function attachments($folder)
    {
        return $this->media()->where('collection_name', $folder);
    }

    public function general_files()
    {
        return $this->media()->where('collection_name', 'general_files');
    }

    public function attendances()
    {
        return $this->hasMany(CourseBatchSessionAttendance::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getCompanyNameAttribute()
    {
        return optional($this->company)->name_ar;
    }

    public function getIdentityCopyUrlAttribute()
    {
        return $this->getCopyUrl('identity');
    }

    public function getQualificationCopyUrlAttribute()
    {
        return $this->getCopyUrl('qualification');
    }

    public function getBankAccountCopyUrlAttribute()
    {
        return $this->getCopyUrl('bank-account');
    }

    public function getNationalAddressCopyUrlAttribute()
    {
        return $this->getCopyUrl('national-address');
    }

    public function getCvUrlAttribute()
    {
        return $this->getCopyUrl('cv');
    }

    public function getGosiCertificateCopyUrlAttribute()
    {
        return $this->getCopyUrl('gosi-certificate');
    }

    public function getQiwaContractCopyUrlAttribute()
    {
        return $this->getCopyUrl('qiwa-contract');
    }

    public function getCopyUrl($collection_name)
    {
        $media_id = optional($this->media()->where('collection_name', $collection_name)->first())->id;

        if ($media_id) {
            return route('back.media.download', ['media_id' => $media_id]);
        }
    }

    public function getShowUrlAttribute(): string
    {
        if ($this->trashed()) {
            return route('back.trainees.show.blocked', $this->id);
        } else {
            return route('back.trainees.show', $this->id);
        }
    }

    public function getNameSelectableAttribute()
    {
        return $this->name.' ('.$this->identity_number.')';
    }

    /**
     *
     * @return string
     */
    public function getResourceLabelAttribute(): string
    {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function getResourceTypeAttribute(): string
    {
        return trans('words.trainee');
    }

    /**
     *
     * @return string|void
     */
    public function getDeletedAtTimezoneAttribute()
    {
        if ($this->deleted_at) {
            return Timezone::convertToLocal($this->deleted_at, 'Y-m-d h:i A');
        }
    }

    /**
     *
     * @return string|void
     */
    public function getCreatedAtTimezoneAttribute()
    {
        if ($this->created_at) {
            return Timezone::convertToLocal($this->created_at, 'Y-m-d h:i A');
        }
    }

    /**
     *
     * @return string
     */
    public function getCreatedAtDateAttribute(): string
    {
        return (date('Y-m-d', strtotime($this->created_at)));
    }

    public function toSearchableArray()
    {
        return $this->only(self::SEARCHABLE_FIELDS);
    }

    /**
     *
     * @return bool
     */
    public function isPendingApproval()
    {
        return (int) $this->status === Trainee::STATUS_PENDING_APPROVAL;
    }

    public function getIsPendingApprovalAttribute()
    {
        return $this->isPendingApproval();
    }

    /**
     * The user didn't upload their CV or required files yet.
     *
     * @return bool
     */
    public function getIsPendingUploadingFilesAttribute()
    {
        return (int) $this->status === Instructor::STATUS_PENDING_UPLOADING_FILES;
    }

    public function getIsApprovedAttribute()
    {
        return (int) $this->status === Instructor::STATUS_APPROVED;
    }

    public function getTraineeGroupObjectAttribute()
    {
        if( isset($this->trainee_group[0])) {
            return $this->trainee_group[0];
        }
    }

    public function routeNotificationForClickSend()
    {
        return $this->cleanUpThePhoneNumber($this->phone);
    }

    public function cleanUpThePhoneNumber($phone)
    {
        $convertPhone = $this->arabicE2w($phone);

        if (! Str::startsWith($convertPhone, '966')) {
            $convertPhone = Str::replaceFirst('05', '9665', $convertPhone);
        }

        if (Str::startsWith($convertPhone, '5')) {
            $convertPhone = Str::replaceFirst('5', '9665', $convertPhone);
        }

        if (Str::length($convertPhone) != 12) { // KSA number.
            return null;
        }

        return $convertPhone;
    }

    public function getCleanPhoneAttribute()
    {
        return $this->cleanUpThePhoneNumber($this->phone);
    }

    public function getCleanPhoneAdditionalAttribute()
    {
        return $this->cleanUpThePhoneNumber($this->phone_additional);
    }

    public function getBillFromDateFormattedAttribute()
    {
        return optional($this->bill_from_date)->toDateString();
    }

    public function getLinkedDateFormattedAttribute()
    {
        return optional($this->linked_date)->toDateString();
    }

    public function getTotalAmountOwedAttribute(): float
    {
        return round($this->invoices()->notPaid()->sum('grand_total'), 2);
    }

    public function arabicE2w($str)
    {
        $arabic_eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $arabic_western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($arabic_eastern, $arabic_western, $str);
    }

    public function getCleanIdentityNumberAttribute()
    {
        return $this->arabicE2w($this->identity_number);
    }

    public function absences_custom()
    {
        return $this->hasMany(AttendanceReportRecord::class)
            ->where('status', 0)
            ->whereBetween('session_starts_at', [
                Carbon::parse(request()->from_date)->startOfDay(),
                Carbon::parse(request()->to_date)->endOfDay(),
            ]);

        //return $this->hasMany(AttendanceReportRecordWarning::class)
        //    ->whereBetween('created_at', [
        //        now()->setDate(2022, 6, 12)->startOfDay(),
        //        now()->setDate(2022, 6, 18)->endOfDay(),
        //    ]);
    }

    public function attendances_7to11()
    {
        // App\Models\Back\AttendanceReportRecord::whereIn('status', [1,2,3])->where('trainee_id', $t->id)->whereIn('status', [1,2,3])->whereBetween('session_starts_at', [now()->setDate(2021, 11, 7)->startOfDay(), now()->setDate(2021, 11, 11)->endOfDay()]);
        return $this->hasMany(AttendanceReportRecord::class)
            ->whereIn('status', [AttendanceReportRecord::STATUS_PRESENT, AttendanceReportRecord::STATUS_LATE_TO_CLASS, AttendanceReportRecord::STATUS_ABSENT_WITH_EXCUSE])
            ->whereBetween('session_starts_at', [
                now()->setDate(2021, 11, 7)->startOfDay(),
                now()->setDate(2021, 11, 11)->endOfDay(),
            ]);
    }

    public function getHasOutstandingAmountAttribute()
    {
        return $this->invoices()->notPaid()->count();
    }

    public function getWhatsappLinkAttribute()
    {
        return 'https://api.whatsapp.com/send?phone='.$this->routeNotificationForClickSend();
    }

    public function warnings()
    {
        return $this->hasMany(AttendanceReportRecordWarning::class);
    }

    public function getPhoneOwnershipStatusAttribute()
    {
        if (!$this->phone_ownership_verified_at) {
            return __('words.ownership-is-pending');
        }

        if ($this->phone_ownership_verified_at) {
            if ($this->phone_is_owned) {
                return __('words.ownership-is-verified');
            } else {
                return __('words.not-owned');
            }
        }

        return __('words.ownership-is-pending');
    }

    public function resignations()
    {
        return $this->hasMany(ResignationTrainee::class);
    }

    public function leaves()
    {
        return $this->hasMany(TraineeLeave::class);
    }

    public function traineeAgreement()
    {
        return $this->belongsTo(TraineeAgreement::class, 'trainee_agreement_id');
    }

}
