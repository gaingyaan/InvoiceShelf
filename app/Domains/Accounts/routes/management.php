<?php

use App\Domains\Accounts\Http\Controllers\Admin\CompaniesController;
use App\Domains\Accounts\Http\Controllers\Company\CompanySettingsController;
use App\Domains\Accounts\Http\Controllers\Company\MembersController;
use Illuminate\Support\Facades\Route;

Route::post('companies', [CompaniesController::class, 'store']);
Route::post('/transfer/ownership/{user}', [CompanySettingsController::class, 'transferOwnership']);
Route::post('companies/delete', [CompaniesController::class, 'destroy']);
Route::get('companies', [CompaniesController::class, 'userCompanies']);

Route::post('/members/delete', [MembersController::class, 'delete']);
Route::apiResource('/members', MembersController::class);
