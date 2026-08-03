<?php

namespace Tests\Feature\PushSubscription;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PushSubscriptionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_user_id_is_migrated_to_subscribable(): void
    {
        $user = User::factory()->create();

        // Simulasikan skema lama sebelum migrasi polymorphic
        Schema::drop('push_subscriptions');
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token')->unique();
            $table->string('platform')->default('android');
            $table->string('device_info')->nullable();
            $table->timestamps();
        });

        DB::table('push_subscriptions')->insert([
            'user_id' => $user->id,
            'fcm_token' => 'legacy-token-123',
            'platform' => 'android',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_08_03_100004_modify_push_subscriptions_table.php');
        $migration->up();

        $row = DB::table('push_subscriptions')->where('fcm_token', 'legacy-token-123')->first();

        $this->assertSame(User::class, $row->subscribable_type);
        $this->assertSame($user->id, $row->subscribable_id);
        $this->assertFalse(Schema::hasColumn('push_subscriptions', 'user_id'));
        $this->assertSame(1, DB::table('push_subscriptions')->count());
    }
}
