<?php

use App\Platform\Ai\Http\Admin\AiConfigurationController;
use App\Platform\Ai\Http\Company\ChatController;
use App\Platform\Ai\Http\Company\CompanyAiConfigurationController;
use App\Platform\Ai\Http\Company\ConversationController;
use App\Platform\Ai\Http\Company\GenerationController;
use Illuminate\Support\Facades\Route;

Route::get('/ai/drivers', [AiConfigurationController::class, 'getDrivers']);
Route::get('/ai/config', [AiConfigurationController::class, 'getConfig']);
Route::post('/ai/config', [AiConfigurationController::class, 'saveConfig']);
Route::post('/ai/test', [AiConfigurationController::class, 'testConnection']);

Route::get('/company/ai/config', [CompanyAiConfigurationController::class, 'getConfig']);
Route::post('/company/ai/config', [CompanyAiConfigurationController::class, 'saveConfig']);
Route::post('/company/ai/test', [CompanyAiConfigurationController::class, 'testConnection']);

Route::middleware('throttle:ai')->group(function () {
    Route::post('/ai/chat', ChatController::class);
    Route::get('/ai/conversations', [ConversationController::class, 'index']);
    Route::get('/ai/conversations/{id}', [ConversationController::class, 'show']);
    Route::patch('/ai/conversations/{id}', [ConversationController::class, 'update']);
    Route::delete('/ai/conversations/{id}', [ConversationController::class, 'destroy']);
    Route::post('/ai/generate', GenerationController::class);
});
