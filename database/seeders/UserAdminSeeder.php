<?php

namespace Database\Seeders;

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
            User::query()->where(User::IS_ADMIN, true)->delete();
        }

        $this->createAdmin();
    }

    private function createAdmin(): void
    {
        $user = [
            User::NAME => 'superadmin',
            User::PASSWORD => Hash::make('password'),
            User::IS_ADMIN => true,
        ];
        User::factory()->create($user);
    }
}
