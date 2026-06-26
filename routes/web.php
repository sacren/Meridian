<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\BlastController;
use App\Http\Controllers\CampaignAnalyticsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\SegmentController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // The API console: a session-authed page (and its token-minting endpoint) that
    // drives the v1 REST API over HTTP. Token issuance is not campaign-scoped, so
    // these sit outside the campaign.access group below.
    Route::get('api-demo', [ApiTokenController::class, 'create'])->name('api-token.create');

    Route::post('api-demo/token', [ApiTokenController::class, 'store'])->name('api-token.generate');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');

    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

    Route::get('campaigns/{campaign}', [CampaignController::class, 'dashboard'])
        ->middleware('campaign.access')
        ->name('campaigns.dashboard');

    Route::get('campaigns/{campaign}/analytics', [CampaignAnalyticsController::class, 'index'])
        ->middleware('campaign.access')
        ->name('campaigns.analytics.index');

    Route::middleware('campaign.access')->scopeBindings()->group(function () {
        Route::get('campaigns/{campaign}/contacts', [ContactController::class, 'index'])
            ->name('campaigns.contacts.index');

        Route::post('campaigns/{campaign}/contacts', [ContactController::class, 'store'])
            ->name('campaigns.contacts.store');

        Route::put('campaigns/{campaign}/contacts/{contact}', [ContactController::class, 'update'])
            ->name('campaigns.contacts.update');

        Route::delete('campaigns/{campaign}/contacts/{contact}', [ContactController::class, 'destroy'])
            ->name('campaigns.contacts.destroy');

        Route::get('campaigns/{campaign}/segments', [SegmentController::class, 'index'])
            ->name('campaigns.segments.index');

        Route::post('campaigns/{campaign}/segments', [SegmentController::class, 'store'])
            ->name('campaigns.segments.store');

        Route::post('campaigns/{campaign}/segments/preview', [SegmentController::class, 'preview'])
            ->name('campaigns.segments.preview');

        Route::put('campaigns/{campaign}/segments/{segment}', [SegmentController::class, 'update'])
            ->name('campaigns.segments.update');

        Route::delete('campaigns/{campaign}/segments/{segment}', [SegmentController::class, 'destroy'])
            ->name('campaigns.segments.destroy');

        Route::get('campaigns/{campaign}/blasts', [BlastController::class, 'index'])
            ->name('campaigns.blasts.index');

        Route::post('campaigns/{campaign}/blasts', [BlastController::class, 'store'])
            ->name('campaigns.blasts.store');

        Route::put('campaigns/{campaign}/blasts/{blast}', [BlastController::class, 'update'])
            ->name('campaigns.blasts.update');

        Route::delete('campaigns/{campaign}/blasts/{blast}', [BlastController::class, 'destroy'])
            ->name('campaigns.blasts.destroy');

        Route::post('campaigns/{campaign}/blasts/{blast}/send', [BlastController::class, 'send'])
            ->name('campaigns.blasts.send');
    });
});

require __DIR__.'/settings.php';
