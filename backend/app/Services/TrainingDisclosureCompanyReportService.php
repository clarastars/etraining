<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Back\Company;
use App\Models\Back\RecordedCourseEnrollment;
use App\Models\Back\TrainingDisclosureRequest;
use Illuminate\Support\Collection;

class TrainingDisclosureCompanyReportService
{
    /**
     * @return array{
     *     companies: list<array<string, mixed>>,
     *     selected: ?array<string, mixed>,
     *     rows: list<array<string, mixed>>,
     *     summary: array{employees: int, emails_delivered: int, accessed: int, eligible: int}
     * }
     */
    public function build(?string $selectedId): array
    {
        $companies = $this->companies();
        $selected = null;

        if (is_string($selectedId) && $selectedId !== '') {
            $selected = collect($companies)->firstWhere('id', $selectedId);
        }

        $rows = [];
        if (is_array($selected) && ($selected['linked'] ?? false) && is_string($selected['company_id'] ?? null)) {
            $rows = $this->rowsForCompany($selected['company_id']);
        }

        return [
            'companies' => $companies,
            'selected' => $selected,
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<string|int|null>>
     */
    public function spreadsheetRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $course = app()->getLocale() === 'en'
                ? ($row['course_name_en'] ?: $row['course_name_ar'])
                : ($row['course_name_ar'] ?: $row['course_name_en']);

            return [
                $row['name'],
                $row['email'],
                $course,
                __('words.training-disclosure-report-delivery-'.$row['delivery_status']),
                $row['accessed'] ? __('words.yes') : __('words.no'),
                $row['lessons_completed'].'/'.$row['lessons_total'].' ('.$row['progress_percent'].'%)',
                __('words.recorded-course-entitlement-'.$row['entitlement']),
            ];
        }, $rows);
    }

    /**
     * @return list<string>
     */
    public function spreadsheetHeadings(): array
    {
        return [
            __('words.name'),
            __('words.email'),
            __('words.course'),
            __('words.training-disclosure-report-delivered-email'),
            __('words.training-disclosure-report-accessed'),
            __('words.recorded-course-progress'),
            __('words.training-disclosure-report-certificate-eligibility'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function companies(): array
    {
        $teamId = auth()->user()?->current_team_id;
        $requests = TrainingDisclosureRequest::query()
            ->with('company:id,name_ar,name_en,team_id')
            ->get();

        $companiesByName = $this->companiesByNormalizedName(is_string($teamId) ? $teamId : null);

        /** @var Collection<string, array<string, mixed>> $grouped */
        $grouped = collect();

        foreach ($requests as $request) {
            $companyId = $this->resolveCompanyId($request, $companiesByName);
            $key = $companyId ?? ('unlinked:'.substr(md5($this->normalizeName($request->company_name)), 0, 12));
            $company = $companyId ? ($request->company_id === $companyId ? $request->company : Company::query()->find($companyId)) : null;

            $existing = $grouped->get($key);
            $numbers = $existing['request_numbers'] ?? [];
            $numbers[] = $request->number;

            $grouped->put($key, [
                'id' => $key,
                'company_id' => $companyId,
                'name_ar' => $company?->name_ar ?: ($existing['name_ar'] ?? $request->company_name),
                'name_en' => $company?->name_en ?: ($existing['name_en'] ?? $request->company_name),
                'requests_count' => count($numbers),
                'request_numbers' => $numbers,
                'linked' => $companyId !== null,
            ]);
        }

        return $grouped
            ->sortBy(fn (array $company) => mb_strtolower((string) ($company['name_ar'] ?: $company['name_en'])))
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function companiesByNormalizedName(?string $teamId): array
    {
        if ($teamId === null || $teamId === '') {
            return [];
        }

        $index = [];

        Company::query()
            ->where('team_id', $teamId)
            ->get(['id', 'name_ar', 'name_en'])
            ->each(function (Company $company) use (&$index): void {
                foreach ([$company->name_ar, $company->name_en] as $name) {
                    $key = $this->normalizeName((string) $name);
                    if ($key !== '' && ! isset($index[$key])) {
                        $index[$key] = $company->id;
                    }
                }
            });

        return $index;
    }

    /**
     * @param  array<string, string>  $companiesByName
     */
    private function resolveCompanyId(TrainingDisclosureRequest $request, array $companiesByName): ?string
    {
        if (is_string($request->company_id) && $request->company_id !== '') {
            return $request->company_id;
        }

        $key = $this->normalizeName($request->company_name);

        return $companiesByName[$key] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rowsForCompany(string $companyId): array
    {
        $teamId = auth()->user()?->current_team_id;
        if (! is_string($teamId) || $teamId === '') {
            return [];
        }

        return RecordedCourseEnrollment::query()
            ->where('team_id', $teamId)
            ->whereHas('trainee', function ($query) use ($companyId): void {
                $query->where('company_id', $companyId);
            })
            ->with([
                'trainee:id,name,email,company_id',
                'lessonProgress',
                'recordedCourse' => function ($query): void {
                    $query->withoutGlobalScopes()->withCount('lessons');
                },
            ])
            ->get()
            ->sortBy(function (RecordedCourseEnrollment $enrollment): string {
                $name = mb_strtolower((string) ($enrollment->trainee?->name ?? ''));
                $course = mb_strtolower((string) ($enrollment->recordedCourse?->name_ar ?? ''));

                return $name.' '.$course;
            })
            ->map(function (RecordedCourseEnrollment $enrollment): array {
                $lessonsTotal = (int) ($enrollment->recordedCourse?->lessons_count ?? 0);
                $lessonsCompleted = $enrollment->lessonProgress
                    ->filter(fn ($progress) => $progress->completed_at !== null)
                    ->count();
                $progressPercent = $lessonsTotal > 0
                    ? (int) round(($lessonsCompleted / $lessonsTotal) * 100)
                    : 0;
                $deliveryStatus = $enrollment->delivery_status;
                if (! in_array($deliveryStatus, ['delivered', 'pending', 'failed'], true)) {
                    $deliveryStatus = 'not_sent';
                }

                return [
                    'id' => $enrollment->id,
                    'name' => $enrollment->trainee?->name,
                    'email' => $enrollment->trainee?->email,
                    'course_name_ar' => $enrollment->recordedCourse?->name_ar,
                    'course_name_en' => $enrollment->recordedCourse?->name_en,
                    'delivery_status' => $deliveryStatus,
                    'email_delivered' => $deliveryStatus === 'delivered',
                    'accessed' => $enrollment->checked_in_at !== null,
                    'checked_in_at' => $enrollment->checked_in_at?->toIso8601String(),
                    'progress_percent' => $progressPercent,
                    'lessons_completed' => $lessonsCompleted,
                    'lessons_total' => $lessonsTotal,
                    'entitlement' => $enrollment->certificateEntitlement(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{employees: int, emails_delivered: int, accessed: int, eligible: int}
     */
    private function summary(array $rows): array
    {
        $collection = collect($rows);

        return [
            'employees' => $collection->count(),
            'emails_delivered' => $collection->where('email_delivered', true)->count(),
            'accessed' => $collection->where('accessed', true)->count(),
            'eligible' => $collection->filter(function (array $row): bool {
                return in_array($row['entitlement'] ?? '', ['eligible', 'certificate_sent'], true);
            })->count(),
        ];
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_strtolower($name);
    }
}
