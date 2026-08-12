<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(ForecastPoint::class)->orderBy('forecast_date');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(ModelEvaluation::class);
    }
}
