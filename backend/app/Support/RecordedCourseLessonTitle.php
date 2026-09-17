<?php

declare(strict_types=1);

namespace App\Support;

class RecordedCourseLessonTitle
{
    /**
     * Build a learner-facing title from a Drive/Zoom filename.
     */
    public static function fromFileName(string $fileName, ?string $courseName = null): string
    {
        $base = pathinfo($fileName, PATHINFO_FILENAME);
        if (! is_string($base) || $base === '') {
            $base = $fileName;
        }

        return self::clean($base, $courseName);
    }

    /**
     * Strip Zoom/Drive recording indexes and redundant course/day/session prefixes.
     *
     * "ساعات العمل الأسبوعية-2" → "ساعات العمل الأسبوعية"
     * "نظام العمل السعودي - اليوم الأول - الجلسة الأولى - أهمية نظام العمل السعودي"
     *   → "أهمية نظام العمل السعودي"
     */
    public static function clean(string $title, ?string $courseName = null): string
    {
        $original = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        if ($original === '') {
            return '';
        }

        $cleaned = preg_replace('/\s*-\s*\d{1,2}$/u', '', $original) ?? $original;
        $cleaned = trim($cleaned);

        $courseName = trim((string) $courseName);
        if ($courseName !== '') {
            $quoted = preg_quote($courseName, '/');
            $cleaned = preg_replace('/^'.$quoted.'\s*[-–—:]\s*/u', '', $cleaned) ?? $cleaned;
            $cleaned = trim($cleaned);
        }

        $parts = preg_split('/\s+[-–—]\s+/u', $cleaned) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
        if (count($parts) >= 3) {
            $cleaned = (string) end($parts);
        }

        return $cleaned !== '' ? $cleaned : $original;
    }
}
