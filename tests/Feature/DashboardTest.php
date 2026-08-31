<?php

use App\Models\DailyAdmission;
use App\Models\Hospital;
use App\Models\ModelBenchmark;
use App\Models\User;
use App\Services\ForecastUpdater;
use Database\Seeders\MedcastSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MedcastSeeder::class);
    $this->user = User::factory()->create([
        'email' => 'tester@norala.ph',
        'role' => 'admin',
    ]);
});

test('guests are redirected from medcast pages', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('medcast pages are reachable when authenticated', function (string $route) {
    $this->actingAs($this->user)
        ->get(route($route))
        ->assertOk()
        ->assertSee('MEDCAST');
})->with([
    'dashboard',
    'encode',
    'historical',
    'trends',
    'forecasting',
    'performance',
    'capacity-risk',
    'decision-support',
    'about',
]);

test('staff can encode a daily admission record', function () {
    $forecastUpdater = Mockery::mock(ForecastUpdater::class);
    $forecastUpdater->shouldReceive('run')->once()->andReturn(0);
    $this->app->instance(ForecastUpdater::class, $forecastUpdater);

    $this->actingAs($this->user)
        ->post(route('encode.store'), [
            'admission_date' => '2024-08-19',
            'regular_admissions' => 20,
            'emergency_admissions' => 8,
            'other_admissions' => 0,
            'discharges' => 25,
            'occupied_beds' => 90,
            'notes' => 'Test encode',
        ])
        ->assertRedirect(route('forecasting'));

    $this->assertDatabaseHas('daily_admissions', [
        'regular_admissions' => 20,
        'emergency_admissions' => 8,
        'total_admissions' => 28,
    ]);

    expect(DailyAdmission::query()->whereDate('admission_date', '2024-08-19')->exists())->toBeTrue();

    $this->actingAs($this->user)
        ->get(route('historical'))
        ->assertOk()
        ->assertSee('Aug 19, 2024')
        ->assertSee('28');
});

test('csv uploads merge by date and remain visible after refresh', function () {
    $hospital = Hospital::query()->where('code', 'NDH')->firstOrFail();
    $initialCount = $hospital->dailyAdmissions()->count();
    $preservedDate = $hospital->dailyAdmissions()->orderBy('admission_date')->value('admission_date');

    $forecastUpdater = Mockery::mock(ForecastUpdater::class);
    $forecastUpdater->shouldReceive('run')->once()->andReturn(0);
    $this->app->instance(ForecastUpdater::class, $forecastUpdater);

    $csv = implode("\n", [
        'Date,Daily Admissions,Daily Discharges,Total Occupied Beds',
        '2024-08-18,99,20,95',
        '2024-08-19,31,22,92',
    ]);

    $this->actingAs($this->user)
        ->post(route('historical.upload'), [
            'admissions_file' => UploadedFile::fake()->createWithContent('admissions-update.csv', $csv),
        ])
        ->assertRedirect(route('forecasting'));

    expect($hospital->dailyAdmissions()->count())->toBe($initialCount + 1);
    expect($hospital->dailyAdmissions()->whereDate('admission_date', $preservedDate)->exists())->toBeTrue();

    expect((int) $hospital->dailyAdmissions()->whereDate('admission_date', '2024-08-18')->value('total_admissions'))->toBe(99);
    expect((int) $hospital->dailyAdmissions()->whereDate('admission_date', '2024-08-19')->value('total_admissions'))->toBe(31);

    $this->actingAs($this->user)
        ->get(route('historical'))
        ->assertOk()
        ->assertSee('Aug 19, 2024')
        ->assertSee('31');
});

test('capacity risk scenarios support horizon capacity and penalty controls', function () {
    expect(Hospital::query()->where('code', 'NDH')->value('total_beds'))->toBe(120);

    $this->actingAs($this->user)
        ->get(route('capacity-risk', [
            'horizon' => 7,
            'capacity_mode' => 'custom',
            'custom_capacity' => 35,
            'penalty' => 'overload-sensitive',
        ]))
        ->assertOk()
        ->assertSee('Capacity-Risk Scenarios')
        ->assertSee('35')
        ->assertSee('Overload-sensitive');
});

test('performance page displays stronger evaluation and dataset transparency', function () {
    $hospital = Hospital::query()->where('code', 'NDH')->firstOrFail();
    $run = $hospital->activeForecastRun();
    $run->update([
        'batch_id' => 'test-batch',
        'model_params' => array_merge($run->model_params ?? [], [
            'dataset_version' => 'MEDCAST-test123',
            'dataset_records' => 780,
            'dataset_coverage_start' => '2022-07-01',
            'dataset_coverage_end' => '2024-08-18',
            'holdout_days' => 156,
            'training_records' => 624,
            'testing_records' => 156,
            'training_percent' => 80,
            'testing_percent' => 20,
            'split_method' => 'chronological_80_20',
        ]),
    ]);

    foreach ([1, 7, 30] as $horizon) {
        ModelBenchmark::query()->create([
            'hospital_id' => $hospital->id,
            'batch_id' => 'test-batch',
            'model_name' => 'SARIMA',
            'horizon_days' => $horizon,
            'mae' => 3.2,
            'rmse' => 4.1,
            'mase' => 0.8,
            'coverage_80' => 80,
            'coverage_95' => 95,
            'avg_width_80' => 8.5,
            'relative_width_80' => 31.2,
            'high_demand_mae' => 4.4,
            'high_demand_days' => 3,
            'sensitivity' => 75,
            'specificity' => 90,
            'precision' => 80,
            'f1_score' => 77.4,
            'false_alert_rate' => 10,
            'missed_event_rate' => 25,
            'rolling_mae_mean' => 3.1,
            'rolling_mae_std' => 0.4,
            'robustness_score' => 87.1,
            'diagnostics' => [
                'sensitivity_analysis' => [
                    'capacity' => ['p50' => ['capacity' => 27, 'forecast_overload' => 2.1]],
                    'threshold' => ['p66' => ['threshold' => 31, 'f1_score' => 77.4]],
                    'penalty' => ['balanced' => ['weighted_error_rate' => 14.2]],
                    'outlier' => ['winsorization_cap_p99' => 47, 'mae_change_percent' => -2.5],
                ],
            ],
            'is_best_for_horizon' => true,
            'evaluated_at' => now(),
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('performance'))
        ->assertOk()
        ->assertSee('Prediction-Interval Quality by Horizon')
        ->assertSee('High-Demand Detection Performance')
        ->assertSee('Sensitivity Analyses')
        ->assertSee('MEDCAST-test123')
        ->assertSee('80% training')
        ->assertSee('20% testing');
});
