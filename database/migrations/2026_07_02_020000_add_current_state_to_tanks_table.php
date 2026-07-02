<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->decimal('current_ppm', 8, 2)->nullable()->after('notes');
            $table->decimal('current_ph', 4, 2)->nullable()->after('current_ppm');
            $table->decimal('current_water_temperature', 4, 2)->nullable()->after('current_ph');
            $table->timestamp('last_condition_updated_at')->nullable()->after('current_water_temperature');
        });
    }

    public function down(): void
    {
        Schema::table('tanks', function (Blueprint $table) {
            $table->dropColumn([
                'current_ppm',
                'current_ph',
                'current_water_temperature',
                'last_condition_updated_at',
            ]);
        });
    }
};
