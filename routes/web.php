<?php

use App\Http\Controllers\MedcastController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->controller(MedcastController::class)->group(function () {
    Route::get('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/encode', 'encode')->name('encode');
    Route::post('/encode', 'storeAdmission')->name('encode.store');
    Route::get('/historical', 'historical')->name('historical');
    Route::post('/historical/upload', 'uploadAdmissions')->name('historical.upload');
    Route::get('/trends', 'trends')->name('trends');
    Route::get('/forecasting', 'forecasting')->name('forecasting');
    Route::post('/forecasting/run', 'runForecast')->name('forecasting.run');
    Route::get('/performance', 'performance')->name('performance');
    Route::get('/decision-support', 'decisionSupport')->name('decision-support');
    Route::get('/about', 'about')->name('about');
});

require __DIR__.'/settings.php';
