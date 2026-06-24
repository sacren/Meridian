<?php

use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| The versioned JSON API. Every v1 route is rate limited via the `api`
| limiter; resource routes additionally sit behind `auth:sanctum`. Exception
| rendering for `api/*` is forced to JSON in bootstrap/app.php, so failures
| return JSON envelopes rather than the Inertia error page.
|
*/
Route::prefix('v1')
    ->middleware('throttle:api')
    ->group(function () {
        Route::post('/tokens', [TokenController::class, 'store']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/user', fn (Request $request) => $request->user());
            Route::delete('/tokens', [TokenController::class, 'destroy']);

            Route::get('/campaigns', [CampaignController::class, 'index']);
            Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])
                ->middleware('campaign.access');

            Route::middleware('campaign.access')->scopeBindings()->group(function () {
                Route::get('/campaigns/{campaign}/contacts', [ContactController::class, 'index']);
                Route::post('/campaigns/{campaign}/contacts', [ContactController::class, 'store']);
                Route::put('/campaigns/{campaign}/contacts/{contact}', [ContactController::class, 'update']);
                Route::delete('/campaigns/{campaign}/contacts/{contact}', [ContactController::class, 'destroy']);
            });
        });
    });
