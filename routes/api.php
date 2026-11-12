<?php

use App\Http\Controllers\Api\TallyBridgeController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations/tally')
    ->middleware('throttle:60,1')
    ->group(function () {
        Route::get('/pending', [TallyBridgeController::class, 'pullPending'])->name('api.tally.pending');
        Route::post('/ack', [TallyBridgeController::class, 'acknowledgeBatch'])->name('api.tally.ack');
        Route::get('/masters', [TallyBridgeController::class, 'downloadMasters'])->name('api.tally.masters');
    });

