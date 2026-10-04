<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ExcelDays360;
use Tests\TestCase;

class ExcelDays360Test extends TestCase
{
    public function test_sample_sheet_dates_match_excel_days360(): void
    {
        $this->assertSame(16, ExcelDays360::between('2026-05-14', '2026-05-30'));
        $this->assertSame(17, ExcelDays360::inclusiveDays('2026-05-14', '2026-05-30'));
    }

    public function test_nasd_end_of_month_rules(): void
    {
        $this->assertSame(30, ExcelDays360::between('2026-01-01', '2026-01-31'));
        $this->assertSame(30, ExcelDays360::between('2026-02-28', '2026-03-31'));
        $this->assertNull(ExcelDays360::inclusiveDays('2026-05-14', null));
    }
}
