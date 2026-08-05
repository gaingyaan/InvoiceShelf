<?php

use App\Platform\Mail\Http\Admin\MailConfigurationController;
use App\Platform\Mail\Http\Company\CompanyMailConfigurationController;
use Illuminate\Support\Facades\Route;

Route::get('/mail/drivers', [MailConfigurationController::class, 'getMailDrivers']);
Route::get('/mail/config', [MailConfigurationController::class, 'getMailEnvironment']);
Route::post('/mail/config', [MailConfigurationController::class, 'saveMailEnvironment']);
Route::post('/mail/test', [MailConfigurationController::class, 'testEmailConfig']);

Route::get('/company/mail/config', [CompanyMailConfigurationController::class, 'getDefaultConfig']);
Route::get('/company/mail/company-config', [CompanyMailConfigurationController::class, 'getMailConfig']);
Route::post('/company/mail/company-config', [CompanyMailConfigurationController::class, 'saveMailConfig']);
Route::post('/company/mail/company-test', [CompanyMailConfigurationController::class, 'testMailConfig']);
