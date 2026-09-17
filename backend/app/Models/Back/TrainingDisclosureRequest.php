<?php

declare(strict_types=1);

namespace App\Models\Back;

use App\Scope\TeamScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrainingDisclosureRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'team_id',
        'number',
        'company_id',
        'company_name',
        'trainees_count',
        'trainees_draft',
    ];

    protected $casts = [
        'trainees_count' => 'integer',
        'trainees_draft' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(new TeamScope());

        static::creating(function (TrainingDisclosureRequest $model): void {
            $model->{$model->getKeyName()} = (string) Str::uuid();

            if (auth()->user() && empty($model->team_id)) {
                $model->team_id = auth()->user()->current_team_id;
            }

            if (empty($model->number)) {
                $model->number = static::nextNumber();
            }
        });
    }

    public static function nextNumber(): string
    {
        return DB::transaction(function (): string {
            // startFrom=99 → first issued value is 100 → E100
            $sequence = MaxNumber::generateForPrefix('disclosure_request', 99, 3);

            return 'E'.$sequence;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
