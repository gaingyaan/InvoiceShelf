<?php

use App\Domains\Money\Http\Controllers\CurrenciesController;
use App\Domains\Money\Http\Controllers\ExchangeRateProviderController;
use Illuminate\Support\Facades\Route;

Route::get('/currencies', CurrenciesController::class);
Route::get('/currencies/used', [ExchangeRateProviderController::class, 'usedCurrenciesWithoutRate']);
Route::post('/currencies/bulk-update-exchange-rate', [ExchangeRateProviderController::class, 'bulkUpdate']);
Route::get('/currencies/{currency}/exchange-rate', [ExchangeRateProviderController::class, 'getRate']);
Route::get('/currencies/{currency}/active-provider', [ExchangeRateProviderController::class, 'activeProvider']);
Route::get('/used-currencies', [ExchangeRateProviderController::class, 'usedCurrencies']);
Route::get('/supported-currencies', [ExchangeRateProviderController::class, 'supportedCurrencies']);
Route::apiResource('exchange-rate-providers', ExchangeRateProviderController::class);
