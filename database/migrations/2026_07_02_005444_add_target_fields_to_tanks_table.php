<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->decimal('target_ppm_min', 10, 2)->default(800)->after('capacity_liter');
            $table->decimal('target_ppm_max', 10, 2)->default(1200)->after('target_ppm_min');
            $table->decimal('target_ph_min', 4, 2)->default(5.50)->after('target_ppm_max');
            $table->decimal('target_ph_max', 4, 2)->default(6.50)->after('target_ph_min');
        });
    }

    public function down(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->dropColumn(['target_ppm_min', 'target_ppm_max', 'target_ph_min', 'target_ph_max']);
        });
    }
};
