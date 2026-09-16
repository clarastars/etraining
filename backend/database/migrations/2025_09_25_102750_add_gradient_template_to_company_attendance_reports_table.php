<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGradientTemplateToCompanyAttendanceReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('company_attendance_reports')) {
            return;
        }

        // MySQL-only ENUM alteration; SQLite stores this as a plain string.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE company_attendance_reports MODIFY COLUMN template_type ENUM('default', 'simple', 'modern', 'gradient') DEFAULT 'default'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('company_attendance_reports')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE company_attendance_reports MODIFY COLUMN template_type ENUM('default', 'simple', 'modern') DEFAULT 'default'");
    }
}
