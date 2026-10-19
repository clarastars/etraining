<?php

namespace App\Models\Back;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Builder;

class CompanyAttendanceReport extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    const STATUS_REVIEW = 1;
    const STATUS_APPROVED = 2;

    protected $fillable = [
        'company_id',
        'date_from',
        'date_to',
        'status',
        'to_emails',
        'cc_emails',
        'with_attendance_times',
        'with_logo',
        'template_type',
    ];

    protected $casts = [
        'date_from' => 'date:d-m-Y',
        'date_to' => 'date:d-m-Y',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'period',
        'updated_at_human',
        'approved_at_human',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->status = self::STATUS_REVIEW;
            $model->number = 'ATR-'.MaxNumber::generateForPrefix('ATR');
            if (auth()->user()) {
                $model->created_by_id = auth()->user()->id;
            }
        });

        if (Str::contains(optional(auth()->user())->email, 'ptc-ksa.com') && auth()->user()->email != 'sara@ptc-ksa.com' && auth()->user()->email != 'mashal.a+1@ptc-ksa.com') {
            static::addGlobalScope('RiyadhBankAccounts', function (Builder $builder) {
                $builder->whereHas('company', function ($query) {
                    $query->whereNull('is_ptc_net');
                })->orWhereDoesntHave('company');
            });
        }

        if (Str::contains(optional(auth()->user())->email, 'ptc-ksa.net')) {
            static::addGlobalScope('RiyadhBankAccounts', function (Builder $builder) {
                $builder->whereHas('company', function ($query) {
                    $query->whereNotNull('is_ptc_net');
                });
            });
        }
    }

    public function trainees()
    {
        return $this
            ->belongsToMany(Trainee::class,'company_attendance_reports_trainees')
            ->withTrashed()
            ->withPivot('active', 'status', 'comment', 'start_date', 'end_date');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function company_attendance_reports_trainee()
    {
        return $this->hasMany(CompanyAttendanceReportsTrainee::class);
    }

    public function approved_by()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getPeriodAttribute()
    {
        return $this->date_from->format('d-m-Y') .' - '.$this->date_to->format('d-m-Y');
    }

    /**
     * Keep a trainee's work window inside this report.
     * A resignation after date_to must not extend the counted days past the period.
     *
     * @param  mixed  $start
     * @param  mixed  $end
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public function boundTraineeDates($start, $end): array
    {
        $periodStart = \Carbon\Carbon::parse($this->date_from)->copy()->startOfDay();
        $periodEnd = \Carbon\Carbon::parse($this->date_to)->copy()->endOfDay();

        $start = \Carbon\Carbon::parse($start)->copy();
        $end = \Carbon\Carbon::parse($end)->copy();

        if ($start->lt($periodStart)) {
            $start = $periodStart->copy();
        }

        if ($end->gt($periodEnd)) {
            $end = $periodEnd->copy();
        }

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [$start, $end];
    }

    public function getUpdatedAtHumanAttribute()
    {
        return $this->updated_at->setTimezone('Asia/Riyadh')->format('d-m-Y h:ia');
    }

    public function getApprovedAtHumanAttribute()
    {
        return optional(optional($this->approved_at)->setTimezone('Asia/Riyadh'))->format('d-m-Y h:ia');
    }

    public function activeTraineesCount()
    {
        return CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $this->id)
                ->where('active', true)
                ->count();
    }

    public function TraineesReport()
    {
        return CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $this->id)
            ->where('active', true)
            ->get();
    }

    public function getActiveTrainees()
    {
        return CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $this->id)
                ->where('active', true)
                ->get();
    }

    public function getAllTraineesWithResignations()
    {
        // Get ALL trainees from the report (both active and inactive) with their status
        $allReportTrainees = CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $this->id)
            ->with(['trainee' => function($q) {
                $q->withTrashed()->with('attendanceReportRecords');
            }])->get();
        
        
        // Filter only ACTIVE trainees
        $activeTrainees = $allReportTrainees->where('active', true);
        
        // Filter out trainees who have leave during the report period
        $activeTrainees = $activeTrainees->filter(function($record) {
            $hasLeave = \App\Models\Back\TraineeLeave::where('trainee_id', $record->trainee_id)
                ->where(function($query) {
                    // Check if leave overlaps with report period
                    $query->where(function($q) {
                        // Leave starts before or during report period and ends during or after report period
                        $q->where('from_date', '<=', $this->date_to)
                          ->where('to_date', '>=', $this->date_from);
                    });
                })
                ->whereIn('status', ['pending', 'approved']) // Only consider pending or approved leaves
                ->exists();
            
            return !$hasLeave; // Return true if trainee doesn't have leave (should be included)
        });
        
        $allTrainees = collect($activeTrainees);
        
        // Create a map of existing trainees with their active status for quick lookup
        $existingTraineesMap = $allReportTrainees->pluck('active', 'trainee_id')->toArray();
        
        // 2. Get trainees with resignations AND deleted (soft deleted) - ONLY if they are ACTIVE in the report
        $resignationTrainees = collect(); // Start with empty collection
        
        // Only add resignation trainees if they are explicitly ACTIVE in the report
        $activeResignationIds = $allReportTrainees->where('active', true)->pluck('trainee_id')->toArray();
        
        if (!empty($activeResignationIds)) {
            $resignationTrainees = $this->company->resignations()
                ->whereIn('status', ['new', 'sent']) // Include both new and sent resignations
                ->where('resignation_date', '>=', $this->date_from) // Resignation date should be within or after report period
                ->with(['trainees' => function($q) use ($activeResignationIds) {
                    $q->onlyTrashed()->whereIn('trainees.id', $activeResignationIds); // ONLY deleted trainees that are ACTIVE in report
                }])
                ->get()
                ->flatMap(function($resignation) {
                    return $resignation->trainees
                        ->map(function($trainee) use ($resignation) {
                            // Check if trainee has leave during report period
                            $hasLeave = \App\Models\Back\TraineeLeave::where('trainee_id', $trainee->id)
                                ->where(function($query) {
                                    $query->where(function($q) {
                                        $q->where('from_date', '<=', $this->date_to)
                                          ->where('to_date', '>=', $this->date_from);
                                    });
                                })
                                ->whereIn('status', ['pending', 'approved'])
                                ->exists();
                            
                            // Skip if trainee has leave
                            if ($hasLeave) {
                                return null;
                            }
                            
                            // Get the actual record from database to preserve all properties
                            $actualRecord = CompanyAttendanceReportsTrainee::where('company_attendance_report_id', $this->id)
                                ->where('trainee_id', $trainee->id)
                                ->first();
                            
                            if ($actualRecord) {
                                // Load the trainee relationship with trashed and attendance records
                                $actualRecord->load(['trainee' => function($q) {
                                    $q->withTrashed()->with('attendanceReportRecords');
                                }]);
                                $actualRecord->is_resignation = true;
                                $actualRecord->resignation_date = $resignation->resignation_date;
                                // Keep original start_date and end_date from database if they exist
                                if (!$actualRecord->start_date) {
                                    $actualRecord->start_date = \Carbon\Carbon::parse($this->date_from);
                                }
                                if (!$actualRecord->end_date) {
                                    $actualRecord->end_date = $this->boundTraineeDates(
                                        $this->date_from,
                                        \Carbon\Carbon::parse($resignation->resignation_date)->endOfDay()
                                    )[1];
                                }
                                return $actualRecord;
                            } else {
                                // Fallback: Create a mock CompanyAttendanceReportsTrainee object for display
                                $mockAttendance = new \stdClass();
                                $mockAttendance->trainee = $trainee->load('attendanceReportRecords');
                                $mockAttendance->is_resignation = true;
                                $mockAttendance->resignation_date = $resignation->resignation_date;
                                $mockAttendance->active = true; // Set as active for display purposes
                                $mockAttendance->status = null; // Default status
                                $mockAttendance->comment = null; // Default comment
                                $mockAttendance->start_date = \Carbon\Carbon::parse($this->date_from); // Start from report start date
                                $mockAttendance->end_date = $this->boundTraineeDates(
                                    $this->date_from,
                                    \Carbon\Carbon::parse($resignation->resignation_date)->endOfDay()
                                )[1];
                                return $mockAttendance;
                            }
                        })
                        ->filter(); // Remove null values (trainees with leave)
                });
        }
        
        $allTrainees = $allTrainees->merge($resignationTrainees);
        
        // Remove duplicates based on trainee ID
        $uniqueTrainees = $allTrainees->unique(function($item) {
            return $item->trainee->id;
        });

        return $uniqueTrainees->map(function ($item) {
            if (empty($item->start_date) || empty($item->end_date)) {
                return $item;
            }

            [$start, $end] = $this->boundTraineeDates($item->start_date, $item->end_date);
            $item->start_date = $start;
            $item->end_date = $end;

            if ($item instanceof Model) {
                $item->syncOriginal();
            }

            return $item;
        });
    }

    public function getFallsUnderPtcNetAttribute()
    {
        return $this->company->is_ptc_net;
    }

    public function created_by()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function emails()
    {
        return $this->hasMany(CompanyAttendanceReportsEmail::class);
    }

    public function emails_to()
    {
        return $this->emails()->where('type', 'to');
    }

    public function emails_cc()
    {
        return $this->emails()->where('type', 'cc');
    }

    public function emails_bcc()
    {
        return $this->emails()->where('type', 'bcc');
    }
}
