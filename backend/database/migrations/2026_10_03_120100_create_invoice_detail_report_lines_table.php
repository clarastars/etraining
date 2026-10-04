<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_detail_report_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id')->unique();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->date('manual_start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('full_salary', 12, 2)->nullable();
            $table->decimal('full_reward', 12, 2)->nullable();
            $table->decimal('full_fees', 12, 2)->nullable();
            $table->decimal('full_refund', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_detail_report_lines');
    }
};
