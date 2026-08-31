<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hospital_id',
    'low_max',
    'moderate_max',
    'high_min',
    'method',
    'meta',
    'is_active',
])]
class DemandThreshold extends Model
{
    protected function casts(): array
    {
        return [
            'low_max' => 'decimal:2',
            'moderate_max' => 'decimal:2',
            'high_min' => 'decimal:2',
            'meta' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function classify(float $value): string
    {
        if ($value <= (float) $this->low_max) {
            return 'Low';
        }

        if ($value <= (float) $this->moderate_max) {
            return 'Moderate';
        }

        return 'High';
    }
}
