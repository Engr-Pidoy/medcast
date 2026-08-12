<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hospital_id',
    'batch_id',
    'model_name',
    'horizon_days',
    'mae',
    'rmse',
    'mase',
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
            'is_best_for_horizon' => 'boolean',
            'evaluated_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
