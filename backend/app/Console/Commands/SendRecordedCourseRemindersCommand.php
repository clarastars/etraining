<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendRecordedCourseReminderJob;
use App\Models\Back\RecordedCourseEnrollment;
use Illuminate\Console\Command;

class SendRecordedCourseRemindersCommand extends Command
{
    protected $signature = 'recorded-courses:send-completion-reminders';

    protected $description = 'Email disclosure learners who have not finished check-in or the course, at most once per reminder interval.';

    public function handle(): int
    {
        $sent = 0;

        RecordedCourseEnrollment::query()
            ->dueForCompletionReminder()
            ->orderBy('id')
            ->chunkById(50, function ($enrollments) use (&$sent): void {
                foreach ($enrollments as $enrollment) {
                    SendRecordedCourseReminderJob::dispatchSync($enrollment->id);
                    $sent++;
                }
            });

        $this->info('Sent '.$sent.' disclosure completion reminder(s).');

        return self::SUCCESS;
    }
}
