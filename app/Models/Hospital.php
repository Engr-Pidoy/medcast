<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $code
 * @property int $total_beds
 * @property string $timezone
 * @property bool $is_active
 */
#[Fillable([
    'name',
    'code',
    'total_beds',
    'timezone',
    'is_active',
])]
class Hospital extends Model
{
    public const BASE_BEDS = 100;

    public const MEAN_OPERATIONAL_BEDS = 120;

    protected function casts(): array
    {
        return [
            'total_beds' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<DailyAdmission, $this> */
    public function dailyAdmissions(): HasMany
    {
        return $this->hasMany(DailyAdmission::class);
    }

    /** @return HasMany<ForecastRun, $this> */
    public function forecastRuns(): HasMany
    {
        return $this->hasMany(ForecastRun::class);
    }

    /** @return HasMany<ModelEvaluation, $this> */
    public function modelEvaluations(): HasMany
    {
        return $this->hasMany(ModelEvaluation::class);
    }

    /** @return HasMany<ModelBenchmark, $this> */
    public function modelBenchmarks(): HasMany
    {
        return $this->hasMany(ModelBenchmark::class);
    }

    /** @return HasMany<DemandThreshold, $this> */
    public function demandThresholds(): HasMany
    {
        return $this->hasMany(DemandThreshold::class);
    }

    /** @return HasMany<AdmissionTrendStat, $this> */
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
