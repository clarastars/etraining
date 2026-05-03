<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorded_courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->unsignedInteger('unlock_delay_hours')->default(24);
            $table->json('allowed_weekdays');
            $table->timestamps();
        });

        Schema::create('recorded_course_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recorded_course_id');
            $table->foreign('recorded_course_id')->references('id')->on('recorded_courses')->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorded_course_lessons');
        Schema::dropIfExists('recorded_courses');
    }
};
