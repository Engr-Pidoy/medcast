<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $batch_id
 * @property string $model_name
 * @property int $horizon_days
 * @property numeric-string $mae
 * @property numeric-string $rmse
 * @property numeric-string|null $mase
 * @property numeric-string|null $coverage_80
 * @property numeric-string|null $coverage_95
 * @property array<string, mixed>|null $diagnostics
 * @property bool $is_best_for_horizon
 * @property Carbon|null $evaluated_at
 */
#[Fillable([
    'hospital_id',
    'batch_id',
    'model_name',
    'horizon_days',
    'mae',
    'rmse',
    'mase',
    'coverage_80',
    'coverage_95',
    'avg_width_80',
    'avg_width_95',
    'relative_width_80',
    'relative_width_95',
    'high_demand_mae',
    'high_demand_days',
    'sensitivity',
    'specificity',
    'precision',
    'f1_score',
    'false_alert_rate',
    'missed_event_rate',
    'rolling_mae_mean',
    'rolling_mae_std',
    'robustness_score',
    'diagnostics',
    'is_best_for_horizon',
    'evaluated_at',
])]
class ModelBenchmark extends Model
{
    protected function casts(): array
    {
        return [
            'horizon_days' => 'integer',
            'mae' => 'decimal:4',
            'rmse' => 'decimal:4',
            'mase' => 'decimal:4',
            'coverage_80' => 'decimal:2',
            'coverage_95' => 'decimal:2',
            'avg_width_80' => 'decimal:4',
            'avg_width_95' => 'decimal:4',
            'relative_width_80' => 'decimal:2',
            'relative_width_95' => 'decimal:2',
            'high_demand_mae' => 'decimal:4',
            'high_demand_days' => 'integer',
            'sensitivity' => 'decimal:2',
            'specificity' => 'decimal:2',
            'precision' => 'decimal:2',
            'f1_score' => 'decimal:2',
            'false_alert_rate' => 'decimal:2',
            'missed_event_rate' => 'decimal:2',
            'rolling_mae_mean' => 'decimal:4',
            'rolling_mae_std' => 'decimal:4',
            'robustness_score' => 'decimal:2',
            'diagnostics' => 'array',
            'is_best_for_horizon' => 'boolean',
            'evaluated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
