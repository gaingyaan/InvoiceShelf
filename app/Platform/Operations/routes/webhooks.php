<?php

use App\Platform\Operations\Http\Webhooks\CronJobController;
use Illuminate\Support\Facades\Route;

Route::get('/cron', CronJobController::class)->middleware('cron-job');
