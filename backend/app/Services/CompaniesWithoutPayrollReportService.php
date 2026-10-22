<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Company;
use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Models\TraineeDocumentationDate;
use Illuminate\Support\Collection;

class CompaniesWithoutPayrollReportService
{
    /**
     * Companies that have trainees with a تاريخ المباشرة on or before the period end,
     * but no invoices whose from_date falls in the selected period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $dateFrom, string $dateTo, ?string $teamId = null): array
    {
        $teamId = $teamId ?? optional(auth()->user())->current_team_id;
        $startedByIdentity = $this->startedIdentities($dateTo);

        if ($startedByIdentity->isEmpty()) {
            return [];
        }

        $identities = $startedByIdentity->keys()->all();

        $trainees = Trainee::query()
            ->select(['id', 'company_id', 'name', 'identity_number'])
            ->whereNotNull('company_id')
            ->whereIn('identity_number', $identities)
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->with(['company' => function ($query) {
                $query->select(['id', 'name_ar', 'name_en', 'code', 'team_id']);
            }])
            ->get()
            ->filter(fn (Trainee $trainee) => $trainee->company !== null);

        if ($trainees->isEmpty()) {
            return [];
        }

        $companyIds = $trainees->pluck('company_id')->unique()->values();

        $companiesWithInvoices = Invoice::query()
            ->whereIn('company_id', $companyIds)
            ->whereDate('from_date', '>=', $dateFrom)
            ->whereDate('from_date', '<=', $dateTo)
            ->distinct()
            ->pluck('company_id')
            ->flip();

        $grouped = $trainees
            ->reject(fn (Trainee $trainee) => $companiesWithInvoices->has($trainee->company_id))
            ->groupBy('company_id');

        return $grouped
            ->map(function (Collection $companyTrainees) use ($startedByIdentity) {
                /** @var Trainee $first */
                $first = $companyTrainees->first();
                $company = $first->company;

                $traineeRows = $companyTrainees
                    ->map(function (Trainee $trainee) use ($startedByIdentity) {
                        $startDate = $startedByIdentity->get($trainee->identity_number);

                        return [
                            'trainee_id' => $trainee->id,
                            'trainee_name' => $trainee->name,
                            'identity_number' => $trainee->identity_number,
                            'start_date' => $startDate,
                        ];
                    })
                    ->sortBy('start_date')
                    ->values()
                    ->all();

                $startDates = collect($traineeRows)->pluck('start_date')->filter()->sort()->values();

                return [
                    'company_id' => $company->id,
                    'company_name' => $company->name_ar,
                    'company_code' => $company->code,
                    'trainees_count' => count($traineeRows),
                    'earliest_start_date' => $startDates->first(),
                    'trainees' => $traineeRows,
                ];
            })
            ->sortBy('company_name')
            ->values()
            ->all();
    }

    /**
     * @return Collection<string, string> identity_number => documented_on (Y-m-d)
     */
    private function startedIdentities(string $dateTo): Collection
    {
        return TraineeDocumentationDate::query()
            ->whereDate('documented_on', '<=', $dateTo)
            ->get(['identity_number', 'documented_on'])
            ->mapWithKeys(function (TraineeDocumentationDate $row) {
                return [
                    $row->identity_number => optional($row->documented_on)->toDateString(),
                ];
            });
    }
}
