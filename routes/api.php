<?php

use App\Http\Controllers\Api\V1\BridgePairController;
use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\DonorController;
use App\Http\Controllers\Api\V1\IngestController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/bridge/pair', [BridgePairController::class, 'pair'])
    ->middleware('throttle:sync')
    ->name('api.bridge.pair');

Route::prefix('v1')
    ->middleware([\App\Http\Middleware\AuthenticateOrgApiToken::class])
    ->group(function () {
        Route::get('/donors', [DonorController::class, 'index']);
        Route::post('/donors', [DonorController::class, 'store']);
        Route::get('/donations', [DonationController::class, 'index']);
        Route::post('/ingest/donors', [IngestController::class, 'donors']);
    });
