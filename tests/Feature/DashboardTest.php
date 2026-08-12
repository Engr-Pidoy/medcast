<?php

use App\Models\User;
use Database\Seeders\MedcastSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    'decision-support',
    'about',
]);

test('staff can encode a daily admission record', function () {
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
        ->assertRedirect(route('encode'));

    $this->assertDatabaseHas('daily_admissions', [
        'regular_admissions' => 20,
        'emergency_admissions' => 8,
        'total_admissions' => 28,
    ]);

    expect(\App\Models\DailyAdmission::query()->whereDate('admission_date', '2024-08-19')->exists())->toBeTrue();
});
