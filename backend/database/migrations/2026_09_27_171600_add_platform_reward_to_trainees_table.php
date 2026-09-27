<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->double('platform_reward', 10, 2)->nullable()->after('override_training_costs');
        });
    }

    public function down(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->dropColumn('platform_reward');
        });
    }
};
