<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => view('demo.login'))->name('login');
Route::post('/login', [DemoController::class, 'login'])->middleware('throttle:5,1');
Route::middleware('auth')->group(function () {
    Route::get('/', [DemoController::class, 'index']);
    Route::post('/logout', [DemoController::class, 'logout']);
    Route::post('/checkout', [DemoController::class, 'checkout'])->middleware('throttle:10,1');
    Route::get('/orders/{reference}', [DemoController::class, 'show']);
});
