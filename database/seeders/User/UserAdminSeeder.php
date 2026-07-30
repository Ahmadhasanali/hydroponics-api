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
        User::withTrashed()->where('name', 'superadmin')->forceDelete();

        User::create([
            'name' => 'superadmin',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);
    }
}
