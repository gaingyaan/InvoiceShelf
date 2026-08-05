<?php

use App\Platform\Storage\Http\BackupsController;
use App\Platform\Storage\Http\DiskController;
use Illuminate\Support\Facades\Route;

Route::apiResource('backups', BackupsController::class);
Route::apiResource('/disks', DiskController::class);

Route::get('download-backup', [BackupsController::class, 'download']);

Route::get('/disk/drivers', [DiskController::class, 'getDiskDrivers']);
Route::get('/disk/purposes', [DiskController::class, 'getDiskPurposes']);
Route::put('/disk/purposes', [DiskController::class, 'updateDiskPurposes']);
