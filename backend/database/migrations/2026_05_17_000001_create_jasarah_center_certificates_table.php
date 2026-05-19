<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jasarah_center_certificates', function (Blueprint $table) {
            $table->id();
            $table->char('course_id', 36);
            $table->string('status')->default('processing');
            $table->integer('total_rows')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('unmatched_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->string('csv_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasarah_center_certificates');
    }
};
