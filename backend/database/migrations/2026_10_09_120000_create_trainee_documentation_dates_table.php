<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainee_documentation_dates', function (Blueprint $table) {
            $table->id();
            $table->string('identity_number', 20)->unique();
            $table->date('documented_on');
            $table->string('source_sheet', 50)->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_documentation_dates');
    }
};
