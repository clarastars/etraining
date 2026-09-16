<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recorded_courses', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable()->after('allowed_weekdays');
            $table->timestamp('drive_synced_at')->nullable()->after('drive_folder_id');
            $table->unique(['team_id', 'drive_folder_id']);
        });

        Schema::table('recorded_course_lessons', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('title_en');
            $table->index(['recorded_course_id', 'drive_file_id']);
        });
    }

    public function down(): void
    {
        Schema::table('recorded_course_lessons', function (Blueprint $table) {
            $table->dropIndex(['recorded_course_id', 'drive_file_id']);
            $table->dropColumn('drive_file_id');
        });

        Schema::table('recorded_courses', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'drive_folder_id']);
            $table->dropColumn(['drive_folder_id', 'drive_synced_at']);
        });
    }
};
