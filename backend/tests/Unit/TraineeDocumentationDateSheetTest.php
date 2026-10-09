<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TraineeDocumentationDateSheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TraineeDocumentationDateSheetTest extends TestCase
{
    public function test_it_keeps_the_latest_documentation_date_for_each_identity(): void
    {
        $book = new Spreadsheet();
        $current = $book->getActiveSheet();
        $current->setTitle('2026');
        $current->fromArray([
            [null, 'الاسم ', 'الهوية', 'تاريخ التوثيق '],
            [null, 'اسيا', '1104839079', '15/1/2026'],
            [null, 'منار', 1079494041, 46023],
        ]);

        $older = $book->createSheet();
        $older->setTitle('2025');
        $older->fromArray([
            ['اسم المتدربة', 'الهوية', 'تاريخ التوثيق '],
            ['اسيا', '1104839079', '12/2/2025'],
        ]);

        $contractYear = $book->createSheet();
        $contractYear->setTitle('2024');
        $contractYear->fromArray([
            ['الهوية', 'تاريخ االعقد'],
            ['1080488271', '3/1/2024'],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'docs').'.xlsx';
        (new Xlsx($book))->save($path);

        try {
            $rows = (new TraineeDocumentationDateSheet())->rows($path);
        } finally {
            unlink($path);
        }

        $this->assertSame('2026-01-15', $rows['1104839079']['documented_on']);
        $this->assertSame('2026', $rows['1104839079']['source_sheet']);
        $this->assertArrayHasKey('1079494041', $rows);
        $this->assertNotSame('', $rows['1079494041']['documented_on']);
        $this->assertSame('2024-01-03', $rows['1080488271']['documented_on']);
        $this->assertSame('2024', $rows['1080488271']['source_sheet']);
    }
}
