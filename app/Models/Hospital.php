<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'total_beds',
    'timezone',
    'is_active',
])]
class Hospital extends Model
{
    protected function casts(): array
    {
        return [
            'total_beds' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function dailyAdmissions(): HasMany
    {
        return $this->hasMany(DailyAdmission::class);
    }

    public function forecastRuns(): HasMany
    {
        return $this->hasMany(ForecastRun::class);
    }

    public function modelEvaluations(): HasMany
    {
        return $this->hasMany(ModelEvaluation::class);
    }

    public function modelBenchmarks(): HasMany
    {
        return $this->hasMany(ModelBenchmark::class);
    }

    public function demandThresholds(): HasMany
    {
        return $this->hasMany(DemandThreshold::class);
    }

    public function trendStats(): HasMany
    {
        return $this->hasMany(AdmissionTrendStat::class);
    }

    public function activeForecastRun(): ?ForecastRun
    {
        return $this->forecastRuns()
            ->where('is_active', true)
            ->where('is_primary', true)
            ->where('status', 'completed')
            ->latest('run_at')
            ->first()
            ?? $this->forecastRuns()
                ->where('is_active', true)
                ->where('status', 'completed')
                ->latest('run_at')
                ->first();
    }

    public function activeDemandThreshold(): ?DemandThreshold
    {
        return $this->demandThresholds()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }
}
