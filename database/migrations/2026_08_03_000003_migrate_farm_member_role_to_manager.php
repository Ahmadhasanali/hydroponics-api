<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('farm_users')->where('role', 'member')->update(['role' => 'manager']);
    }

    public function down(): void
    {
        // Tidak ada rollback data — role baru sudah dipakai setelah deploy.
    }
};
