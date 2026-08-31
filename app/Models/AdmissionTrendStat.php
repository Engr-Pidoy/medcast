<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $stat_type
 * @property string $stat_key
 * @property numeric-string $value
 * @property array<string, mixed>|null $meta
 */
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

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
