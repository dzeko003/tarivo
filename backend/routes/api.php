<?php

use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReferenceDataController;
use Illuminate\Support\Facades\Route;

Route::post('quotes/calculate', [QuoteController::class, 'calculate'])->name('quotes.calculate');
Route::post('quotes/{quote}/recompute', [QuoteController::class, 'recompute'])->name('quotes.recompute');
Route::apiResource('quotes', QuoteController::class)->only(['index', 'store', 'show']);

Route::get('reference-data', ReferenceDataController::class)->name('reference-data');
