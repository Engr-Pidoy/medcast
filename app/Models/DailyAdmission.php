<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hospital_id',
    'admission_date',
    'regular_admissions',
    'emergency_admissions',
    'other_admissions',
    'total_admissions',
    'discharges',
    'occupied_beds',
    'occupancy_rate',
    'notes',
])]
class DailyAdmission extends Model
{
    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'regular_admissions' => 'integer',
            'emergency_admissions' => 'integer',
            'other_admissions' => 'integer',
            'total_admissions' => 'integer',
            'discharges' => 'integer',
            'occupied_beds' => 'integer',
            'occupancy_rate' => 'decimal:2',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    protected static function booted(): void
    {
        static::saving(function (DailyAdmission $admission): void {
            $admission->total_admissions = $admission->regular_admissions
                + $admission->emergency_admissions
                + $admission->other_admissions;

            if ($admission->occupied_beds !== null && $admission->hospital) {
                $beds = $admission->hospital->total_beds ?: 1;
                $admission->occupancy_rate = round(($admission->occupied_beds / $beds) * 100, 2);
            }
        });
    }
}
