<?php

use App\Platform\Modules\Http\Controllers\Assets\ScriptController;
use App\Platform\Modules\Http\Controllers\Assets\StyleController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/modules/styles/{style}', StyleController::class);
    Route::get('/modules/scripts/{script}', ScriptController::class);
});
