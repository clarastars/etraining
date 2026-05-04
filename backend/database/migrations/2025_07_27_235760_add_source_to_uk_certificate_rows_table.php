<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uk_certificate_rows', function (Blueprint $table) {
            // $table->string('source')->nullable()->after('pdf_path');
            // $table->string('source_ref')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('uk_certificate_rows', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_ref']);
        });
    }
};
