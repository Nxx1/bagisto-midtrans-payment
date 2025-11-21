<?php

use Illuminate\Support\Facades\Route;
use Akara\MidtransPayment\Http\Controllers\MidtransCoreController;

Route::group(['prefix' => 'api/checkout', 'middleware' => ['api']], function () {
    Route::post('/midtrans/charge', [MidtransCoreController::class, 'charge']);
});


// Public webhook (no CSRF)
Route::group(['prefix' => 'api', 'middleware' => []], function () {
    Route::get('/order-status/{orderId}', [MidtransCoreController::class, 'orderStatus']);

    Route::post('/midtrans/notification', [MidtransCoreController::class, 'notification']);
});

