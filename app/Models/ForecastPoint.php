<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'forecast_run_id',
    'forecast_date',
    'point_forecast',
    'pi80_low',
    'pi80_high',
    'pi95_low',
    'pi95_high',
])]
class ForecastPoint extends Model
{
    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'point_forecast' => 'decimal:2',
            'pi80_low' => 'decimal:2',
            'pi80_high' => 'decimal:2',
            'pi95_low' => 'decimal:2',
            'pi95_high' => 'decimal:2',
        ];
    }

    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
