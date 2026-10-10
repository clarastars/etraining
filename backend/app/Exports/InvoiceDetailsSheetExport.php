<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoiceDetailsSheetExport implements FromArray, WithEvents
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private array $rows)
    {
    }

    public function array(): array
    {
        $sheet = [[
            __('words.trainee'),
            __('words.identity_number'),
            __('words.subtotal'),
            __('words.tax'),
            __('words.grand-total'),
            __('words.status'),
            __('words.masdr-start-date'),
            __('words.invoice-date'),
            __('words.manual-start-date'),
            __('words.invoice-details-end-date'),
            __('words.day-count'),
            __('words.full-salary'),
            __('words.daily-salary-cost'),
            __('words.salary-due'),
            __('words.full-reward'),
            __('words.daily-reward-cost'),
            __('words.reward-due'),
            __('words.established-fees'),
            __('words.full-fees'),
            __('words.daily-fees-cost'),
            __('words.training-fees-due'),
            __('words.full-refund'),
            __('words.daily-refund-cost'),
            __('words.refund-due'),
        ]];

        foreach ($this->rows as $row) {
            $sheet[] = [
                $row['trainee_name'],
                $row['identity_number'],
                $row['sub_total'],
                $row['tax'],
                $row['grand_total'],
                $row['status'],
                $row['masdr_start_label'],
                $row['invoice_date'],
                $row['manual_start_date'],
                $row['end_date'],
                $row['day_count'],
                $row['full_salary'],
                $row['daily_salary'],
                $row['salary_due'],
                $row['full_reward'],
                $row['daily_reward'],
                $row['reward_due'],
                $row['established_fees'],
                $row['full_fees'],
                $row['daily_fees'],
                $row['fees_due'],
                $row['full_refund'],
                $row['daily_refund'],
                $row['refund_due'],
            ];
        }

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);
                $sheet->getStyle('A1:X1')->getFont()->setBold(true);

                foreach ($this->rows as $index => $row) {
                    $line = $index + 2;
                    $sheet->setCellValueExplicit('B'.$line, (string) $row['identity_number'], DataType::TYPE_STRING);
                    $this->writeDate($sheet, 'G'.$line, $row['masdr_start_date'] ?? null, $row['masdr_start_label'] ?? null);
                    $this->writeDate($sheet, 'H'.$line, $row['invoice_date'] ?? null, $row['invoice_date'] ?? null);
                    $this->writeDate($sheet, 'I'.$line, $row['manual_start_date'] ?? null, null);
                    $this->writeDate($sheet, 'J'.$line, $row['end_date'] ?? null, null);

                    $sheet->setCellValue('K'.$line, '=IF(OR(I'.$line.'="",J'.$line.'=""),"",DAYS360(I'.$line.',J'.$line.')+1)');
                    $sheet->setCellValue('M'.$line, '=IF(L'.$line.'="","",L'.$line.'/30)');
                    $sheet->setCellValue('N'.$line, '=IF(OR(K'.$line.'="",M'.$line.'=""),"",M'.$line.'*K'.$line.')');
                    $sheet->setCellValue('P'.$line, '=IF(O'.$line.'="","",O'.$line.'/30)');
                    $sheet->setCellValue('Q'.$line, '=IF(OR(K'.$line.'="",P'.$line.'=""),"",P'.$line.'*K'.$line.')');
                    $sheet->setCellValue('T'.$line, '=IF(S'.$line.'="","",S'.$line.'/30)');
                    $sheet->setCellValue('U'.$line, '=IF(OR(K'.$line.'="",T'.$line.'=""),"",T'.$line.'*K'.$line.')');
                    $sheet->setCellValue('W'.$line, '=IF(V'.$line.'="","",V'.$line.'/30)');
                    $sheet->setCellValue('X'.$line, '=IF(OR(K'.$line.'="",W'.$line.'=""),"",W'.$line.'*K'.$line.')');
                }

                if ($this->rows === []) {
                    return;
                }

                $last = count($this->rows) + 1;
                $totalRow = $last + 1;
                $sheet->setCellValue('A'.$totalRow, __('words.total'));

                foreach (['C', 'D', 'E', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'] as $column) {
                    $sheet->setCellValue($column.$totalRow, '=SUM('.$column.'2:'.$column.$last.')');
                }

                $sheet->getStyle('A'.$totalRow.':X'.$totalRow)->getFont()->setBold(true);
            },
        ];
    }

    private function writeDate(Worksheet $sheet, string $cell, ?string $date, ?string $label): void
    {
        if ($date) {
            $sheet->setCellValue($cell, Date::PHPToExcel(new \DateTime($date)));
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('YYYY-MM-DD');

            return;
        }

        $sheet->setCellValue($cell, $label);
    }
}
