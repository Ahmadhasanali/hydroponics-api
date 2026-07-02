<?php

namespace Database\Seeders\User;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminCount = User::query()->where('name', 'superadmin')->count();

        if ($adminCount > 0) {
            User::query()->where('is_admin', true)->delete();
        }

        $this->createAdmin();
    }

    private function createAdmin(): void
    {
        User::factory()->admin()->create([
            'name' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }
}
