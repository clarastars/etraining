<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TraineeDocumentationDateSheet
{
    /**
     * @return array<string, array{identity_number: string, documented_on: string, source_sheet: string}>
     */
    public function rows(string $path): array
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $book = $reader->load($path);
        $dates = [];

        foreach ($book->getWorksheetIterator() as $sheet) {
            $this->readSheet($sheet, $dates);
        }

        return $dates;
    }

    /**
     * @param  array<string, array{identity_number: string, documented_on: string, source_sheet: string}>  $dates
     */
    private function readSheet(Worksheet $sheet, array &$dates): void
    {
        $highestRow = (int) $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        if ($highestRow < 2) {
            return;
        }

        $header = $sheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0];
        $identityColumn = null;
        $dateColumn = null;

        foreach ($header as $index => $name) {
            $normalized = $this->normalizeHeader($name);
            if ($normalized === 'الهوية') {
                $identityColumn = (int) $index + 1;
            }
            if (in_array($normalized, ['تاريخالتوثيق', 'تاريخاالعقد', 'تاريخالعقد'], true)) {
                $dateColumn = (int) $index + 1;
            }
        }

        if ($identityColumn === null || $dateColumn === null) {
            return;
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $identity = $this->identity($sheet->getCellByColumnAndRow($identityColumn, $row)->getValue());
            $documentedOn = $this->date($sheet->getCellByColumnAndRow($dateColumn, $row));

            if ($identity === null || $documentedOn === null) {
                continue;
            }

            $existing = $dates[$identity]['documented_on'] ?? null;
            if ($existing !== null && $existing >= $documentedOn) {
                continue;
            }

            $dates[$identity] = [
                'identity_number' => $identity,
                'documented_on' => $documentedOn,
                'source_sheet' => $sheet->getTitle(),
            ];
        }
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', '', $value) ?? '';

        return $value;
    }

    private function identity(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $digits = sprintf('%.0f', $value);
        } else {
            $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        }

        return strlen($digits) === 10 ? $digits : null;
    }

    private function date(Cell $cell): ?string
    {
        try {
            return $this->acceptedDate($this->parseCellDate($cell));
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function parseCellDate(Cell $cell): ?string
    {
        $value = $cell->getValue();

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value))->toDateString();
        }

        if (is_numeric($value) && (ExcelDate::isDateTime($cell) || ((float) $value > 20000 && (float) $value < 80000))) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $text = trim(str_replace('\\', '/', (string) $value));
        if ($text === '' || preg_match('/\d/', $text) !== 1) {
            return null;
        }

        foreach (['j/n/Y', 'd/m/Y', 'j-n-Y', 'd-m-Y', 'Y-m-d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $text);
            } catch (\Throwable $exception) {
                continue;
            }

            if ($parsed instanceof Carbon) {
                return $parsed->toDateString();
            }
        }

        return null;
    }

    private function acceptedDate(?string $date): ?string
    {
        if ($date === null || preg_match('/^(\d{4})-/', $date, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= 1990 && $year <= 2100 ? $date : null;
    }
}
