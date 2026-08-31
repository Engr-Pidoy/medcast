<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $period_label
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property numeric-string $mae
 * @property numeric-string $rmse
 * @property numeric-string $mape
 * @property numeric-string $r_squared
 * @property numeric-string|null $coverage_80
 * @property numeric-string|null $coverage_95
 * @property string|null $status
 */
#[Fillable([
    'hospital_id',
    'forecast_run_id',
    'period_label',
    'period_start',
    'period_end',
    'mae',
    'rmse',
    'mape',
    'r_squared',
    'coverage_80',
    'coverage_95',
    'status',
    'evaluated_at',
])]
class ModelEvaluation extends Model
{
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'mae' => 'decimal:3',
            'rmse' => 'decimal:3',
            'mape' => 'decimal:3',
            'r_squared' => 'decimal:4',
            'coverage_80' => 'decimal:2',
            'coverage_95' => 'decimal:2',
            'evaluated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /** @return BelongsTo<ForecastRun, $this> */
    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
