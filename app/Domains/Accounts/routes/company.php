<?php

use App\Domains\Accounts\Http\Controllers\Auth\AuthController;
use App\Domains\Accounts\Http\Controllers\Company\AbilitiesController;
use App\Domains\Accounts\Http\Controllers\Company\CompanyController;
use App\Domains\Accounts\Http\Controllers\Company\CompanySettingsController;
use App\Domains\Accounts\Http\Controllers\Company\InvitationController;
use App\Domains\Accounts\Http\Controllers\Company\InvitationResponseController;
use App\Domains\Accounts\Http\Controllers\Company\RolesController;
use App\Domains\Accounts\Http\Controllers\Company\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/invitations/pending', [InvitationResponseController::class, 'pending']);
Route::post('/invitations/{invitation:token}/accept', [InvitationResponseController::class, 'accept']);
Route::post('/invitations/{invitation:token}/decline', [InvitationResponseController::class, 'decline']);
Route::get('/auth/check', [AuthController::class, 'check']);

Route::apiResource('company-invitations', InvitationController::class)->only(['index', 'store', 'destroy']);

Route::get('/me', [UserProfileController::class, 'show']);
Route::put('/me', [UserProfileController::class, 'update']);
Route::get('/me/settings', [UserProfileController::class, 'showSettings']);
Route::put('/me/settings', [UserProfileController::class, 'updateSettings']);
Route::post('/me/upload-avatar', [UserProfileController::class, 'uploadAvatar']);

Route::put('/company', [CompanyController::class, 'updateCompany']);
Route::post('/company/upload-logo', [CompanyController::class, 'uploadCompanyLogo']);
Route::get('/company/settings', [CompanySettingsController::class, 'show']);
Route::post('/company/settings', [CompanySettingsController::class, 'update']);
Route::get('/company/has-transactions', [CompanySettingsController::class, 'checkTransactions']);

Route::get('abilities', AbilitiesController::class);
Route::apiResource('roles', RolesController::class);
