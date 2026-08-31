<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $forecast_date
 * @property numeric-string $point_forecast
 * @property numeric-string|null $pi80_low
 * @property numeric-string|null $pi80_high
 * @property numeric-string|null $pi95_low
 * @property numeric-string|null $pi95_high
 */
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

    /** @return BelongsTo<ForecastRun, $this> */
    public function forecastRun(): BelongsTo
    {
        return $this->belongsTo(ForecastRun::class);
    }
}
