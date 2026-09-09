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
            $table->string('access_token', 64)->nullable()->unique()->after('enrolled_at');
            $table->timestamp('access_link_sent_at')->nullable()->after('access_token');
            $table->timestamp('checked_in_at')->nullable()->after('access_link_sent_at');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            $table->timestamp('completed_at')->nullable()->after('checked_out_at');
            $table->string('certificate_status', 32)->default('none')->after('completed_at');
            $table->timestamp('certificate_approved_at')->nullable()->after('certificate_status');
            $table->uuid('certificate_approved_by')->nullable()->after('certificate_approved_at');
            $table->timestamp('certificate_sent_at')->nullable()->after('certificate_approved_by');
            $table->string('certificate_path')->nullable()->after('certificate_sent_at');
            $table->string('mailgun_message_id')->nullable()->after('certificate_path');
            $table->string('delivery_status', 32)->nullable()->after('mailgun_message_id');
            $table->timestamp('delivered_at')->nullable()->after('delivery_status');
            $table->timestamp('failed_at')->nullable()->after('delivered_at');
            $table->text('delivery_failure_reason')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('recorded_course_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'access_token',
                'access_link_sent_at',
                'checked_in_at',
                'checked_out_at',
                'completed_at',
                'certificate_status',
                'certificate_approved_at',
                'certificate_approved_by',
                'certificate_sent_at',
                'certificate_path',
                'mailgun_message_id',
                'delivery_status',
                'delivered_at',
                'failed_at',
                'delivery_failure_reason',
            ]);
        });
    }
};
