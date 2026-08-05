<?php

use App\Domains\Catalog\Http\Controllers\ItemsController;
use App\Domains\Catalog\Http\Controllers\UnitsController;
use Illuminate\Support\Facades\Route;

Route::post('/items/delete', [ItemsController::class, 'delete']);
Route::resource('items', ItemsController::class);
Route::resource('units', UnitsController::class);
