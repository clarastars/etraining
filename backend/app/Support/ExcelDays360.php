<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

class ExcelDays360
{
    /**
     * Excel DAYS360 using the US (NASD) method.
     */
    public static function between(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $startYear = (int) $start->year;
        $startMonth = (int) $start->month;
        $startDay = (int) $start->day;
        $endYear = (int) $end->year;
        $endMonth = (int) $end->month;
        $endDay = (int) $end->day;

        if ($startDay === 31) {
            $startDay = 30;
        } elseif ($startMonth === 2 && ($startDay === 29 || ($startDay === 28 && ! self::isLeapYear($startYear)))) {
            $startDay = 30;
        }

        if ($endDay === 31) {
            if ($startDay < 30) {
                $endDay = 1;
                if ($endMonth === 12) {
                    $endYear++;
                    $endMonth = 1;
                } else {
                    $endMonth++;
                }
            } else {
                $endDay = 30;
            }
        }

        return ($endYear - $startYear) * 360
            + ($endMonth - $startMonth) * 30
            + ($endDay - $startDay);
    }

    public static function inclusiveDays(?string $startDate, ?string $endDate): ?int
    {
        if ($startDate === null || $startDate === '' || $endDate === null || $endDate === '') {
            return null;
        }

        return self::between($startDate, $endDate) + 1;
    }

    private static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
    }
}
