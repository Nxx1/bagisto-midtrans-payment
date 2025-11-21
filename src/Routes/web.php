<?php

use Illuminate\Support\Facades\Route;
use Akara\MidtransPayment\Http\Controllers\MidtransController;

Route::prefix('payment')->group(function () {
    // Route::get('/finish', [MidtransController::class, 'redirectFinish'])->name('midtrans.finish');

    // Route::get('/cancel', [MidtransController::class, 'redirectCancel'])->name('midtrans.cancel');

    // Route::get('/failed', [MidtransController::class, 'redirectError'])->name('midtrans.failed');

    Route::get('/redirect/{order}', [MidtransController::class, 'redirect'])->name('midtrans.redirect');

    Route::post('/callback', [MidtransController::class, 'callback'])->name('midtrans.callback');
});
