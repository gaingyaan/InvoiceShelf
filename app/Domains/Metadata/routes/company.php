<?php

use App\Domains\Metadata\Http\Controllers\CustomFieldsController;
use App\Domains\Metadata\Http\Controllers\NotesController;
use Illuminate\Support\Facades\Route;

Route::resource('custom-fields', CustomFieldsController::class);
Route::apiResource('notes', NotesController::class);
