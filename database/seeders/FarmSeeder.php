<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\Farm\Tank;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FarmSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'hasan',
        ]);

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
