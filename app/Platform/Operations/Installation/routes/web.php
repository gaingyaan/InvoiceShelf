<?php

use App\Platform\Operations\Installation\Http\Controllers\SessionLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/installation', function () {
    return view('app');
})->name('install')
    ->middleware(['redirect-if-installed']);

// The Vue Router renders the wizard steps. This catch-all keeps deep links
// and hard refreshes inside the installation SPA from returning a 404.
Route::get('/installation/{vue?}', function () {
    return view('app');
})->where('vue', '.*')
    ->middleware(['redirect-if-installed']);

Route::post('/installation/session-login', SessionLoginController::class)
    ->middleware(['redirect-if-installed', 'auth:sanctum']);
