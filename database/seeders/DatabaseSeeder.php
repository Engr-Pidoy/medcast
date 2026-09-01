<?php

namespace Database\Seeders;

use App\Models\DailyAdmission;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@norala.ph'],
            [
                'name' => 'MEDCAST Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'staff@norala.ph'],
            [
                'name' => 'Records Staff',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'email_verified_at' => now(),
            ]
        );

        // Never replace operator-uploaded production data during a later seed.
        // The bundled dataset is bootstrap data only for an empty installation.
        if (! DailyAdmission::query()->exists()) {
            $this->call(MedcastSeeder::class);
        }
    }
}
