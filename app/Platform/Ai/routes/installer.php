<?php

use App\Platform\Ai\Http\Setup\AiConfigurationController;
use Illuminate\Support\Facades\Route;

Route::get('/ai/config', [AiConfigurationController::class, 'show']);
Route::post('/ai/config', [AiConfigurationController::class, 'save']);
