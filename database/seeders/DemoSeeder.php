<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a shared demo account for portfolio demo deployments.
     * No real user data is stored — single shared account, reset periodically.
     */
    public function run(): void
    {
        $email = (string) config('app.demo_email', 'demo@hydrofarm.id');
        $password = (string) config('app.demo_password', 'demo123456');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo Farmer',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $farm = Farm::query()->where('created_by', $user->id)->first();

        if ($farm === null) {
            $farm = Farm::factory()->create([
                'created_by' => $user->id,
            ]);
            $farm->users()->attach($user, ['role' => 'owner']);

            Tank::factory()->create([
                'farm_id' => $farm->id,
                'created_by' => $user->id,
            ]);
        }
    }
}
