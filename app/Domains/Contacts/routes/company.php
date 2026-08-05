<?php

use App\Domains\Contacts\Http\Controllers\Company\CustomersController;
use App\Domains\Contacts\Http\Controllers\Company\CustomerStatsController;
use Illuminate\Support\Facades\Route;

Route::post('/customers/delete', [CustomersController::class, 'delete']);
Route::get('customers/{customer}/stats', CustomerStatsController::class);
Route::resource('customers', CustomersController::class);
