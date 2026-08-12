<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hospital_id',
    'stat_type',
    'stat_key',
    'value',
    'meta',
])]
class AdmissionTrendStat extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
