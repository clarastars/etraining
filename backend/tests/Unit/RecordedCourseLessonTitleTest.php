<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RecordedCourseLessonTitle;
use PHPUnit\Framework\TestCase;

class RecordedCourseLessonTitleTest extends TestCase
{
    public function test_strips_zoom_recording_index_from_filename(): void
    {
        $this->assertSame(
            'ساعات العمل الأسبوعية',
            RecordedCourseLessonTitle::fromFileName('ساعات العمل الأسبوعية-2.mp4')
        );
        $this->assertSame(
            'ساعات العمل اليومية',
            RecordedCourseLessonTitle::clean('ساعات العمل اليومية-1')
        );
        $this->assertSame(
            'الإجازات الرسمية',
            RecordedCourseLessonTitle::clean('الإجازات الرسمية-1')
        );
    }

    public function test_uses_topic_from_long_day_session_path(): void
    {
        $this->assertSame(
            'أهمية نظام العمل السعودي',
            RecordedCourseLessonTitle::clean(
                'نظام العمل السعودي - اليوم الأول - الجلسة الأولى - أهمية نظام العمل السعودي',
                'نظام العمل السعودي'
            )
        );
    }

    public function test_does_not_strip_intentional_lesson_codes(): void
    {
        $this->assertSame('L1', RecordedCourseLessonTitle::clean('L1'));
        $this->assertSame('درس 1', RecordedCourseLessonTitle::clean('درس 1'));
    }

    public function test_keeps_two_part_titles(): void
    {
        $this->assertSame(
            'اليوم الأول - الجلسة الأولى',
            RecordedCourseLessonTitle::clean('اليوم الأول - الجلسة الأولى')
        );
    }
}
