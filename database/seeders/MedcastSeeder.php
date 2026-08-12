<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MedcastSeeder extends Seeder
{
    public function run(): void
    {
        $exit = Artisan::call('medcast:import-admissions', [
            '--fresh' => true,
        ]);

        if ($exit !== 0) {
            $this->command?->error(Artisan::output());

            return;
        }

        $this->command?->info(Artisan::output());
    }
}
