<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddElegantTemplateToTemplateTypeEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== "mysql") {
            return;
        }

        // تحديث الـ ENUM لإضافة elegant
        DB::statement("ALTER TABLE company_attendance_reports MODIFY COLUMN template_type ENUM('default', 'simple', 'modern', 'gradient', 'classic', 'royal', 'elegant') DEFAULT 'default'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== "mysql") {
            return;
        }

        // إرجاع الـ ENUM إلى الحالة السابقة
        DB::statement("ALTER TABLE company_attendance_reports MODIFY COLUMN template_type ENUM('default', 'simple', 'modern', 'gradient', 'classic', 'royal') DEFAULT 'default'");
    }
}
