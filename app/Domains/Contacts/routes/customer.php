<?php

use App\Domains\Contacts\Http\Controllers\CountriesController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\BootstrapController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\DashboardController;
use App\Domains\Contacts\Http\Controllers\CustomerPortal\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/bootstrap', BootstrapController::class);
Route::get('/dashboard', DashboardController::class);
Route::post('/profile', [ProfileController::class, 'updateProfile']);
Route::get('/me', [ProfileController::class, 'getUser']);
Route::get('/countries', CountriesController::class);
