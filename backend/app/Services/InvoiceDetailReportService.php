<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Company;
use App\Models\Back\Invoice;
use App\Models\Back\InvoiceDetailReportLine;
use App\Models\GosiEmployeeData;
use App\Models\TraineeDocumentationDate;
use App\Support\ExcelDays360;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InvoiceDetailReportService
{
    public const EDITABLE_FIELDS = [
        'manual_start_date',
        'end_date',
        'full_salary',
        'full_reward',
        'full_fees',
        'full_refund',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(string $companyId, string $dateFrom, string $dateTo): array
    {
        $company = Company::query()->with('contracts')->findOrFail($companyId);
        $suggestedSalary = $this->suggestedSalary($company);

        $invoices = Invoice::query()
            ->with('trainee')
            ->where('company_id', $companyId)
            ->whereDate('from_date', '>=', $dateFrom)
            ->whereDate('from_date', '<=', $dateTo)
            ->orderBy('from_date')
            ->orderBy('number')
            ->get();

        $lines = InvoiceDetailReportLine::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->get()
            ->keyBy('invoice_id');

        $gosiByIdentity = $this->gosiByIdentity($invoices);
        $documentationDates = $this->documentationDates($invoices);

        return $invoices->map(function (Invoice $invoice) use ($lines, $gosiByIdentity, $documentationDates, $suggestedSalary, $company) {
            $identity = $this->identityDigits(optional($invoice->trainee)->identity_number);

            return $this->present(
                $invoice,
                $lines->get($invoice->id),
                $suggestedSalary,
                $this->withDocumentationDate(
                    $this->masdrStart($company, $gosiByIdentity->get($identity)),
                    $documentationDates->get($identity)
                )
            );
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function saveCell(Invoice $invoice, string $field, $value): array
    {
        $invoice->loadMissing(['trainee', 'company.contracts']);

        $line = InvoiceDetailReportLine::query()->firstOrNew([
            'invoice_id' => $invoice->id,
        ]);

        if (! $line->exists) {
            $line->full_salary = $this->suggestedSalary($invoice->company);
            $line->full_reward = $this->suggestedReward($invoice);
        }

        $line->{$field} = $this->normalizeValue($field, $value);
        $line->save();

        return $this->present(
            $invoice,
            $line->fresh(),
            $this->suggestedSalary($invoice->company),
            $this->masdrStartForInvoice($invoice)
        );
    }

    /**
     * @param  array<string, mixed>|null  $masdr
     * @return array<string, mixed>
     */
    public function present(Invoice $invoice, ?InvoiceDetailReportLine $line, ?float $suggestedSalary, ?array $masdr): array
    {
        $saved = $line !== null;
        $fullSalary = $saved ? $line->full_salary : $suggestedSalary;
        $fullReward = $saved ? $line->full_reward : $this->suggestedReward($invoice);
        $fullFees = $saved ? $line->full_fees : null;
        $fullRefund = $saved ? $line->full_refund : null;
        $manualStart = $saved && $line->manual_start_date ? $line->manual_start_date->toDateString() : null;
        $endDate = $saved && $line->end_date ? $line->end_date->toDateString() : null;
        $dayCount = ExcelDays360::inclusiveDays($manualStart, $endDate);

        return [
            'invoice_id' => $invoice->id,
            'trainee_name' => optional($invoice->trainee)->name ?? '',
            'identity_number' => optional($invoice->trainee)->identity_number ?? '',
            'sub_total' => (float) $invoice->sub_total,
            'tax' => (float) $invoice->tax,
            'grand_total' => (float) $invoice->grand_total,
            'status' => $invoice->status_formatted,
            'masdr_start_date' => $masdr['date'] ?? null,
            'masdr_start_label' => $masdr['label'] ?? null,
            'masdr_approx_ago' => $masdr['approx_ago'] ?? null,
            'masdr_employer_name' => $masdr['employer_name'] ?? null,
            'masdr_wage' => $masdr['wage'] ?? null,
            'masdr_working_months' => $masdr['working_months'] ?? null,
            'masdr_payload' => $masdr['payload'] ?? null,
            'invoice_date' => optional($invoice->from_date)->toDateString(),
            'manual_start_date' => $manualStart,
            'end_date' => $endDate,
            'day_count' => $dayCount,
            'full_salary' => $fullSalary,
            'daily_salary' => $this->daily($fullSalary),
            'salary_due' => $this->due($fullSalary, $dayCount),
            'full_reward' => $fullReward,
            'daily_reward' => $this->daily($fullReward),
            'reward_due' => $this->due($fullReward, $dayCount),
            'established_fees' => $this->establishedFees($invoice),
            'full_fees' => $fullFees,
            'daily_fees' => $this->daily($fullFees),
            'fees_due' => $this->due($fullFees, $dayCount),
            'full_refund' => $fullRefund,
            'daily_refund' => $this->daily($fullRefund),
            'refund_due' => $this->due($fullRefund, $dayCount),
            'saved' => $saved,
        ];
    }

    public function due(?float $fullAmount, ?int $dayCount): ?float
    {
        if ($fullAmount === null || $dayCount === null) {
            return null;
        }

        return ($fullAmount / 30) * $dayCount;
    }

    private function daily(?float $fullAmount): ?float
    {
        if ($fullAmount === null) {
            return null;
        }

        return $fullAmount / 30;
    }

    private function suggestedSalary(?Company $company): ?float
    {
        if (! $company) {
            return null;
        }

        $contract = $company->contracts
            ->sortByDesc(function ($contract) {
                return optional($contract->contract_starts_at)->timestamp ?? 0;
            })
            ->first();

        if (! $contract || $contract->trainee_salary === null) {
            return null;
        }

        return (float) $contract->trainee_salary;
    }

    private function suggestedReward(Invoice $invoice): ?float
    {
        $reward = optional($invoice->trainee)->platform_reward;

        return $reward === null ? null : (float) $reward;
    }

    private function establishedFees(Invoice $invoice): ?float
    {
        $fees = optional($invoice->trainee)->override_training_costs;

        return $fees === null || $fees === '' ? null : (float) $fees;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function masdrStartForInvoice(Invoice $invoice): ?array
    {
        $invoice->loadMissing(['company', 'trainee']);
        $identity = $this->identityDigits(optional($invoice->trainee)->identity_number);
        if ($identity === '') {
            return null;
        }

        $record = GosiEmployeeData::query()
            ->whereIn('nin_or_iqama', array_filter([
                $identity,
                optional($invoice->trainee)->identity_number,
            ]))
            ->get()
            ->first(function (GosiEmployeeData $row) use ($identity) {
                return $this->identityDigits($row->nin_or_iqama) === $identity;
            });

        return $this->withDocumentationDate(
            $this->masdrStart($invoice->company, $record),
            TraineeDocumentationDate::query()->where('identity_number', $identity)->first()
        );
    }

    /**
     * @param  Collection<int, Invoice>  $invoices
     * @return Collection<string, TraineeDocumentationDate>
     */
    private function documentationDates(Collection $invoices): Collection
    {
        $identities = $invoices
            ->map(fn (Invoice $invoice) => $this->identityDigits(optional($invoice->trainee)->identity_number))
            ->filter()
            ->unique()
            ->values();

        if ($identities->isEmpty()) {
            return collect();
        }

        return TraineeDocumentationDate::query()
            ->whereIn('identity_number', $identities)
            ->get()
            ->keyBy('identity_number');
    }

    /**
     * @param  array<string, mixed>|null  $masdr
     * @return array<string, mixed>|null
     */
    private function withDocumentationDate(?array $masdr, ?TraineeDocumentationDate $record): ?array
    {
        if ($record === null || $record->documented_on === null) {
            return $masdr;
        }

        $masdr ??= [];
        $date = $record->documented_on->toDateString();
        $masdr['date'] = $date;
        $masdr['label'] = $date;

        return $masdr;
    }

    /**
     * @param  Collection<string, GosiEmployeeData>  $gosiByIdentity
     */
    private function gosiByIdentity(Collection $invoices): Collection
    {
        $needles = [];
        foreach ($invoices as $invoice) {
            $identity = (string) (optional($invoice->trainee)->identity_number ?? '');
            if ($identity === '') {
                continue;
            }
            $needles[] = $identity;
            $digits = $this->identityDigits($identity);
            if ($digits !== '') {
                $needles[] = $digits;
            }
        }

        if ($needles === []) {
            return collect();
        }

        return GosiEmployeeData::query()
            ->whereIn('nin_or_iqama', array_values(array_unique($needles)))
            ->get()
            ->keyBy(function (GosiEmployeeData $row) {
                return $this->identityDigits($row->nin_or_iqama);
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function masdrStart(?Company $company, ?GosiEmployeeData $record): ?array
    {
        if (! $company || ! $record) {
            return null;
        }

        $payload = $record->data;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($payload)) {
            $payload = [];
        }

        $employments = collect($payload['employmentStatusInfo'] ?? [])
            ->filter(fn ($employment) => is_array($employment))
            ->values();

        if ($employments->isEmpty()) {
            return null;
        }

        $companyCr = $this->identityDigits((string) ($company->cr_number ?? ''));
        if ($companyCr !== '') {
            $matches = $employments
                ->filter(function (array $employment) use ($company) {
                    return $this->crMatches((string) $company->cr_number, $employment['commercialRegistrationNumber'] ?? null);
                })
                ->values();

            if ($matches->isEmpty()) {
                return null;
            }
        } else {
            $matches = $employments
                ->filter(function (array $employment) use ($company) {
                    return $this->employerNameMatches($company->name_ar, $employment['employerName'] ?? null);
                })
                ->values();

            if ($matches->isEmpty()) {
                $matches = $employments;
            }
        }

        $employment = $matches->first(function (array $row) {
            $status = mb_strtolower((string) ($row['employmentStatus'] ?? ''));

            return str_contains($status, 'نشيط') || str_contains($status, 'active');
        }) ?? $matches->first();

        return $this->presentMasdrEmployment($employment, $payload);
    }

    /**
     * @param  array<string, mixed>  $employment
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function presentMasdrEmployment(array $employment, array $payload): array
    {
        $date = null;
        $label = null;

        $joining = $employment['dateOfJoining'] ?? null;
        if ($joining !== null && $joining !== '') {
            $date = $this->parseDate((string) $joining);
            $label = $date ?? (string) $joining;
        } else {
            $salaryStart = $employment['salaryStartingDate'] ?? null;
            if ($salaryStart !== null && $salaryStart !== '') {
                $date = $this->parseDate((string) $salaryStart);
                $label = $date ?? (string) $salaryStart;
            }
        }

        $workingMonths = null;
        if (isset($employment['workingMonths']) && $employment['workingMonths'] !== '' && is_numeric($employment['workingMonths'])) {
            $workingMonths = (int) $employment['workingMonths'];
        }

        $approxAgo = $workingMonths !== null ? $this->approxAgoFromMonths($workingMonths) : null;
        if ($label === null) {
            $label = $approxAgo;
        }

        $wage = null;
        if (isset($employment['fullWage']) && $employment['fullWage'] !== '' && is_numeric($employment['fullWage'])) {
            $wage = (float) $employment['fullWage'];
        }

        $employerName = isset($employment['employerName']) && $employment['employerName'] !== ''
            ? (string) $employment['employerName']
            : null;

        return [
            'date' => $date,
            'label' => $label,
            'approx_ago' => $approxAgo,
            'employer_name' => $employerName,
            'wage' => $wage,
            'working_months' => $workingMonths,
            'payload' => $payload,
        ];
    }

    private function approxAgoFromMonths(int $months): string
    {
        $months = max(0, $months);
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        if ($years > 0 && $remainingMonths > 0) {
            return __('words.masdr-approx-years-months-ago', [
                'years' => $years,
                'months' => $remainingMonths,
            ]);
        }

        if ($years > 0) {
            return __('words.masdr-approx-years-ago', [
                'years' => $years,
            ]);
        }

        return __('words.masdr-approx-months-ago', [
            'months' => $months,
        ]);
    }

    private function employerNameMatches(?string $companyName, $employerName): bool
    {
        $left = $this->normalizeName($companyName);
        $right = $this->normalizeName($employerName === null ? '' : (string) $employerName);

        return $left !== '' && $left === $right;
    }

    private function normalizeName(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_strtolower($value);
    }

    private function crMatches(string $companyCr, $employmentCr): bool
    {
        $left = $this->identityDigits($companyCr);
        $right = $this->identityDigits($employmentCr === null ? '' : (string) $employmentCr);

        return $left !== '' && $left === $right;
    }

    private function identityDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches) === 1) {
            return $this->calendarDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $matches) === 1) {
            return $this->calendarDate((int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        return null;
    }

    private function calendarDate(int $year, int $month, int $day): ?string
    {
        if ($year >= 1700) {
            if (! checkdate($month, $day, $year)) {
                return null;
            }

            return Carbon::create($year, $month, $day)->toDateString();
        }

        if ($year < 1300 || ! class_exists(\IntlCalendar::class)) {
            return null;
        }

        $calendar = \IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-civil');
        if (! $calendar) {
            return null;
        }

        $calendar->clear();
        $calendar->set(\IntlCalendar::FIELD_YEAR, $year);
        $calendar->set(\IntlCalendar::FIELD_MONTH, $month - 1);
        $calendar->set(\IntlCalendar::FIELD_DAY_OF_MONTH, $day);
        $timestamp = (int) floor($calendar->getTime() / 1000);

        return Carbon::createFromTimestampUTC($timestamp)->toDateString();
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function normalizeValue(string $field, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['manual_start_date', 'end_date'], true)) {
            return Carbon::parse((string) $value)->toDateString();
        }

        return round((float) $value, 2);
    }
}
