<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CompaniesWithoutPayrollExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'companies-without-payroll';
    }

    public function headings(): array
    {
        return [
            'الشركة',
            'رمز الشركة',
            'اخر تاريخ فاتورة',
            'الأشهر الناقصة',
            'تغطية الأشهر',
            'المتدربة',
            'رقم الهوية',
            'تاريخ المباشرة',
        ];
    }

    public function array(): array
    {
        $lines = [];

        foreach ($this->rows as $company) {
            $coverage = collect($company['months'] ?? [])
                ->map(function (array $month) {
                    $mark = match ($month['status'] ?? null) {
                        'paid' => '✓',
                        'not_owed' => '–',
                        default => ($month['has_invoice'] ?? false) ? '✓' : '✗',
                    };

                    return $month['label'].':'.$mark;
                })
                ->implode(' ');

            foreach ($company['trainees'] as $trainee) {
                $lines[] = [
                    $company['company_name'],
                    $company['company_code'],
                    $company['last_invoice_at'] ?? null,
                    $company['missing_months_count'] ?? null,
                    $coverage,
                    $trainee['trainee_name'],
                    $trainee['identity_number'],
                    $trainee['start_date'],
                ];
            }
        }

        return $lines;
    }
}
