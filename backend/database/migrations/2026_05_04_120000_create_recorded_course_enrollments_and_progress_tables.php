<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorded_course_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->uuid('trainee_id');
            $table->foreign('trainee_id')->references('id')->on('trainees')->cascadeOnDelete();
            $table->uuid('recorded_course_id');
            $table->foreign('recorded_course_id')->references('id')->on('recorded_courses')->cascadeOnDelete();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();

            $table->unique(['trainee_id', 'recorded_course_id'], 'recorded_course_enrollments_trainee_course_unique');
        });

        Schema::create('recorded_course_lesson_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recorded_course_enrollment_id');
            $table->foreign('recorded_course_enrollment_id', 'rc_lesson_progress_enrollment_fk')
                ->references('id')->on('recorded_course_enrollments')->cascadeOnDelete();
            $table->uuid('recorded_course_lesson_id');
            $table->foreign('recorded_course_lesson_id', 'rc_lesson_progress_lesson_fk')
                ->references('id')->on('recorded_course_lessons')->cascadeOnDelete();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['recorded_course_enrollment_id', 'recorded_course_lesson_id'],
                'recorded_course_lesson_progress_enrollment_lesson_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorded_course_lesson_progress');
        Schema::dropIfExists('recorded_course_enrollments');
    }
};
