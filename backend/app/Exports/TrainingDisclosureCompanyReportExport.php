<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TrainingDisclosureCompanyReportExport implements FromArray, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int|null>>  $rows
     */
    public function __construct(
        private array $headings,
        private array $rows,
    ) {
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return list<list<string|int|null>>
     */
    public function array(): array
    {
        return $this->rows;
    }
}
