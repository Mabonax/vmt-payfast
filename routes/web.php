<?php

use Illuminate\Support\Facades\Route;
use Vmt\PayFast\Http\Controllers\PayFastCancelController;
use Vmt\PayFast\Http\Controllers\PayFastNotificationController;
use Vmt\PayFast\Http\Controllers\PayFastReturnController;

// No web middleware: only this stateless provider endpoint is outside CSRF protection.
Route::post('/payments/payfast/notify', PayFastNotificationController::class)->name('payfast.notify');
Route::get('/payments/payfast/return', PayFastReturnController::class)->name('payfast.return');
Route::get('/payments/payfast/cancel', PayFastCancelController::class)->name('payfast.cancel');
