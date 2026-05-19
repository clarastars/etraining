<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jasarah_center_certificate_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jasarah_center_certificate_id');
            $table->char('trainee_id', 36)->nullable();
            $table->unsignedBigInteger('trainee_certificate_id')->nullable();
            $table->string('row_key');
            $table->string('identity_number');
            $table->string('trainee_name_en');
            $table->string('pdf_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->string('mailgun_message_id')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('delivery_failure_reason')->nullable();
            $table->string('delivery_status')->default('pending');
            $table->timestamps();

            $table->foreign('jasarah_center_certificate_id', 'jcc_rows_certificate_fk')
                ->references('id')->on('jasarah_center_certificates')->onDelete('cascade');
            $table->foreign('trainee_id')->references('id')->on('trainees')->onDelete('set null');
            $table->foreign('trainee_certificate_id')->references('id')->on('trainee_certificates')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jasarah_center_certificate_rows');
    }
};
