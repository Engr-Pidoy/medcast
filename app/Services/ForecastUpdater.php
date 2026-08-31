<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

class ForecastUpdater
{
    private string $output = '';

    public function run(): int
    {
        $exitCode = Artisan::call('medcast:run-forecast');
        $this->output = trim(Artisan::output());

        return $exitCode;
    }

    public function output(): string
    {
        return $this->output;
    }
}
