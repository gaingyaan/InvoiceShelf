<?php

use App\Domains\Taxation\Http\Controllers\TaxTypesController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tax-types', TaxTypesController::class);
