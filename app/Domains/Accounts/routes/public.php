<?php

use App\Domains\Accounts\Http\Controllers\Auth\AuthController;
use App\Domains\Accounts\Http\Controllers\Auth\ForgotPasswordController;
use App\Domains\Accounts\Http\Controllers\Auth\InvitationRegistrationController;
use App\Domains\Accounts\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->middleware('throttle:10,2');
    Route::post('reset/password', [ResetPasswordController::class, 'reset']);
});

Route::get('/invitations/{token}/details', [InvitationRegistrationController::class, 'details']);
Route::post('/auth/register-with-invitation', [InvitationRegistrationController::class, 'register']);
