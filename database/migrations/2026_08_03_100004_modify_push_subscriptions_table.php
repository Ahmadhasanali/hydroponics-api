<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('push_subscriptions', 'user_id')) {
            return;
        }

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->nullableMorphs('subscribable');
        });

        DB::table('push_subscriptions')
            ->whereNotNull('user_id')
            ->update([
                'subscribable_type' => User::class,
                'subscribable_id' => DB::raw('user_id'),
            ]);

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('push_subscriptions')
            ->where('subscribable_type', User::class)
            ->update(['user_id' => DB::raw('subscribable_id')]);

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropMorphs('subscribable');
        });
    }
};
