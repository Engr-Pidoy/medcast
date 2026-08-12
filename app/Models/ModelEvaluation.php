<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
