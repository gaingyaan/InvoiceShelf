<?php

use App\Platform\Operations\Installation\Http\Controllers\AppDomainController;
use App\Platform\Operations\Installation\Http\Controllers\DatabaseConfigurationController;
use App\Platform\Operations\Installation\Http\Controllers\FilePermissionsController;
use App\Platform\Operations\Installation\Http\Controllers\FinishController;
use App\Platform\Operations\Installation\Http\Controllers\LanguagesController;
use App\Platform\Operations\Installation\Http\Controllers\LoginController;
use App\Platform\Operations\Installation\Http\Controllers\OnboardingWizardController;
use App\Platform\Operations\Installation\Http\Controllers\RequirementsController;
use Illuminate\Support\Facades\Route;

Route::get('/wizard-step', [OnboardingWizardController::class, 'getStep']);
Route::post('/wizard-step', [OnboardingWizardController::class, 'updateStep']);
Route::post('/wizard-language', [OnboardingWizardController::class, 'saveLanguage']);
Route::get('/languages', [LanguagesController::class, 'languages']);
Route::get('/requirements', [RequirementsController::class, 'requirements']);
Route::get('/permissions', [FilePermissionsController::class, 'permissions']);
Route::post('/database/config', [DatabaseConfigurationController::class, 'saveDatabaseEnvironment']);
Route::get('/database/config', [DatabaseConfigurationController::class, 'getDatabaseEnvironment']);
Route::put('/set-domain', AppDomainController::class);
Route::post('/login', LoginController::class);
Route::post('/finish', FinishController::class);
