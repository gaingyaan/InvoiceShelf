<?php

use App\Domains\Accounts\Http\Controllers\Admin\CompaniesController;
use App\Domains\Accounts\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('companies', [CompaniesController::class, 'index']);
Route::get('companies/{company}', [CompaniesController::class, 'show']);
Route::put('companies/{company}', [CompaniesController::class, 'update']);
Route::get('users', [UsersController::class, 'index']);
Route::get('users/{user}', [UsersController::class, 'show']);
Route::put('users/{user}', [UsersController::class, 'update']);
Route::post('users/{user}/impersonate', [UsersController::class, 'impersonate']);
