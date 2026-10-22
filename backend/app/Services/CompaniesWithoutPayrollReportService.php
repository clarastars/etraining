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
     * Companies that have trainees with a تاريخ المباشرة (documentation sheet),
     * and are missing invoices for at least one owed calendar month in the range.
     *
     * A month is owed only from the month AFTER the company's earliest trainee
     * تاريخ المباشرة (joining on the 1st still starts the following month).
     *
     * Sorted worst → good: most missing owed months first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $dateFrom, string $dateTo, ?string $teamId = null): array
    {
        $teamId = $teamId ?? optional(auth()->user())->current_team_id;
        $startedByIdentity = $this->startedIdentities();
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
                    ->filter(fn (array $row) => $row['start_date'] !== null)
                    ->sortBy('start_date')
                    ->values();

                if ($traineeRows->isEmpty()) {
                    return null;
                }

                $earliestStart = $traineeRows->pluck('start_date')->filter()->sort()->first();
                $firstOwedMonth = Carbon::parse($earliestStart)->startOfMonth()->addMonth();

                $monthStatuses = [];
                $missingMonths = 0;
                $owedMonths = 0;

                foreach ($months as $month) {
                    $monthStart = Carbon::createFromFormat('!Y-m', $month['key'])->startOfMonth();
                    $owed = $monthStart->gte($firstOwedMonth);

                    if (! $owed) {
                        $monthStatuses[] = [
                            'key' => $month['key'],
                            'label' => $month['label'],
                            'status' => 'not_owed',
                            'has_invoice' => false,
                        ];
                        continue;
                    }

                    $owedMonths++;
                    $hasInvoice = $coveredMonths->has($month['key']);
                    if (! $hasInvoice) {
                        $missingMonths++;
                    }

                    $monthStatuses[] = [
                        'key' => $month['key'],
                        'label' => $month['label'],
                        'status' => $hasInvoice ? 'paid' : 'missing',
                        'has_invoice' => $hasInvoice,
                    ];
                }

                if ($owedMonths === 0 || $missingMonths === 0) {
                    return null;
                }

                $lastAt = $lastInvoiceAt->get($company->id);

                return [
                    'company_id' => $company->id,
                    'company_name' => $company->name_ar,
                    'company_code' => $company->code,
                    'trainees_count' => $traineeRows->count(),
                    'earliest_start_date' => $earliestStart,
                    'first_owed_month' => $firstOwedMonth->format('Y-m'),
                    'last_invoice_at' => $lastAt ? Carbon::parse($lastAt)->toDateString() : null,
                    'missing_months_count' => $missingMonths,
                    'months' => $monthStatuses,
                    'trainees' => $traineeRows->all(),
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
    private function startedIdentities(): Collection
    {
        return TraineeDocumentationDate::query()
            ->whereNotNull('documented_on')
            ->get(['identity_number', 'documented_on'])
            ->mapWithKeys(function (TraineeDocumentationDate $row) {
                return [
                    $row->identity_number => optional($row->documented_on)->toDateString(),
                ];
            })
            ->filter();
    }
}
