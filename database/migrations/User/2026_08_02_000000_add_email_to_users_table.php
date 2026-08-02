<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
        });

        $usedEmails = [];

        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$usedEmails): void {
                foreach ($users as $user) {
                    $base = preg_replace('/[^a-z0-9]/', '', Str::lower($user->name));
                    $email = $base.'@mail.local';

                    if ($base === '' || isset($usedEmails[$email])) {
                        $email = 'user'.$user->id.'@mail.local';
                    }

                    while (isset($usedEmails[$email])) {
                        $email = 'user'.$user->id.'-'.Str::lower(Str::random(4)).'@mail.local';
                    }

                    $usedEmails[$email] = true;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['email' => $email]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->unique()->after('name')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
