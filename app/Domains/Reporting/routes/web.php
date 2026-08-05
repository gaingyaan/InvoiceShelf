<?php

use App\Domains\Reporting\Http\Controllers\CustomerSalesReportController;
use App\Domains\Reporting\Http\Controllers\CustomerStatementReportController;
use App\Domains\Reporting\Http\Controllers\ExpensesReportController;
use App\Domains\Reporting\Http\Controllers\ItemSalesReportController;
use App\Domains\Reporting\Http\Controllers\ProfitLossReportController;
use App\Domains\Reporting\Http\Controllers\TaxSummaryReportController;
use Illuminate\Support\Facades\Route;

Route::get('/customers/{customer}/statement', CustomerStatementReportController::class);
Route::get('/sales/customers/{hash}', CustomerSalesReportController::class);
Route::get('/sales/items/{hash}', ItemSalesReportController::class);
Route::get('/expenses/{hash}', ExpensesReportController::class);
Route::get('/tax-summary/{hash}', TaxSummaryReportController::class);
Route::get('/profit-loss/{hash}', ProfitLossReportController::class);
