<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recorded_course_enrollments', function (Blueprint $table) {
            $table->timestamp('last_reminder_sent_at')->nullable()->after('delivery_failure_reason');
            $table->string('reminder_delivery_status', 32)->nullable()->after('last_reminder_sent_at');
            $table->text('reminder_failure_reason')->nullable()->after('reminder_delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('recorded_course_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'last_reminder_sent_at',
                'reminder_delivery_status',
                'reminder_failure_reason',
            ]);
        });
    }
};
