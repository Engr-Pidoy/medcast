<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string|null $batch_id
 * @property string $model_name
 * @property string|null $model_order
 * @property array<string, mixed>|null $model_params
 * @property int $horizon_days
 * @property Carbon|null $train_start_date
 * @property Carbon|null $train_end_date
 * @property Carbon|null $run_at
 * @property string $status
 * @property bool $is_active
 * @property bool $is_primary
 */
#[Fillable([
    'hospital_id',
    'batch_id',
    'model_name',
    'model_order',
    'model_params',
    'horizon_days',
    'train_start_date',
    'train_end_date',
    'run_at',
    'status',
    'is_active',
    'is_primary',
    'notes',
])]
class ForecastRun extends Model
{
    protected function casts(): array
    {
        return [
            'model_params' => 'array',
            'horizon_days' => 'integer',
            'train_start_date' => 'date',
            'train_end_date' => 'date',
            'run_at' => 'datetime',
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /** @return HasMany<ForecastPoint, $this> */
    public function points(): HasMany
    {
        return $this->hasMany(ForecastPoint::class)->orderBy('forecast_date');
    }

    /** @return HasMany<ModelEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(ModelEvaluation::class);
    }
}
