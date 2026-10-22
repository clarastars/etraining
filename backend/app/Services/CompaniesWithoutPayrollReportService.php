<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Invoice;
use App\Models\Back\Trainee;
use App\Models\TraineeDocumentationDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CompaniesWithoutPayrollReportService
{
    /**
     * Companies that have trainees with a تاريخ المباشرة on or before the period end,
     * and are missing invoices for at least one calendar month in the selected range.
     *
     * Sorted worst → good: most missing months first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $dateFrom, string $dateTo, ?string $teamId = null): array
    {
        $teamId = $teamId ?? optional(auth()->user())->current_team_id;
        $startedByIdentity = $this->startedIdentities($dateTo);
        $months = $this->monthsInRange($dateFrom, $dateTo);

        if ($startedByIdentity->isEmpty() || $months === []) {
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

        $grouped = $trainees->groupBy('company_id');
        $companyIds = $grouped->keys();

        $invoiceMonthsByCompany = $this->invoiceMonthsByCompany($companyIds->all(), $dateFrom, $dateTo);

        $lastInvoiceAt = Invoice::query()
            ->whereIn('company_id', $companyIds)
            ->selectRaw('company_id, MAX(created_at) as last_invoice_at')
            ->groupBy('company_id')
            ->pluck('last_invoice_at', 'company_id');

        return $grouped
            ->map(function (Collection $companyTrainees) use ($startedByIdentity, $lastInvoiceAt, $months, $invoiceMonthsByCompany) {
                /** @var Trainee $first */
                $first = $companyTrainees->first();
                $company = $first->company;
                $coveredMonths = $invoiceMonthsByCompany->get($company->id, collect());

                $monthStatuses = [];
                $missingMonths = 0;
                foreach ($months as $month) {
                    $hasInvoice = $coveredMonths->has($month['key']);
                    if (! $hasInvoice) {
                        $missingMonths++;
                    }
                    $monthStatuses[] = [
                        'key' => $month['key'],
                        'label' => $month['label'],
                        'has_invoice' => $hasInvoice,
                    ];
                }

                if ($missingMonths === 0) {
                    return null;
                }

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
                $lastAt = $lastInvoiceAt->get($company->id);

                return [
                    'company_id' => $company->id,
                    'company_name' => $company->name_ar,
                    'company_code' => $company->code,
                    'trainees_count' => count($traineeRows),
                    'earliest_start_date' => $startDates->first(),
                    'last_invoice_at' => $lastAt ? Carbon::parse($lastAt)->toDateString() : null,
                    'missing_months_count' => $missingMonths,
                    'months' => $monthStatuses,
                    'trainees' => $traineeRows,
                ];
            })
            ->filter()
            ->sort(function (array $a, array $b) {
                if ($a['missing_months_count'] !== $b['missing_months_count']) {
                    return $b['missing_months_count'] <=> $a['missing_months_count'];
                }

                $aLast = $a['last_invoice_at'] ?? '';
                $bLast = $b['last_invoice_at'] ?? '';
                if ($aLast !== $bLast) {
                    if ($aLast === '') {
                        return -1;
                    }
                    if ($bLast === '') {
                        return 1;
                    }

                    return $aLast <=> $bLast;
                }

                return strcmp((string) $a['company_name'], (string) $b['company_name']);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function monthsInRange(string $dateFrom, string $dateTo): array
    {
        $cursor = Carbon::parse($dateFrom)->startOfMonth();
        $end = Carbon::parse($dateTo)->startOfMonth();
        $months = [];

        while ($cursor->lte($end)) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('m/Y'),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * @param  array<int, string>  $companyIds
     * @return Collection<string, Collection<string, int>>
     */
    private function invoiceMonthsByCompany(array $companyIds, string $dateFrom, string $dateTo): Collection
    {
        if ($companyIds === []) {
            return collect();
        }

        return Invoice::query()
            ->whereIn('company_id', $companyIds)
            ->whereDate('from_date', '>=', $dateFrom)
            ->whereDate('from_date', '<=', $dateTo)
            ->get(['company_id', 'from_date'])
            ->groupBy('company_id')
            ->map(function (Collection $invoices) {
                return $invoices
                    ->map(fn (Invoice $invoice) => Carbon::parse($invoice->from_date)->format('Y-m'))
                    ->unique()
                    ->flip();
            });
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
