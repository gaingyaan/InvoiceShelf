<?php

use App\Platform\Operations\Http\AppVersionController;
use Illuminate\Support\Facades\Route;

Route::get('/app/version', AppVersionController::class);
