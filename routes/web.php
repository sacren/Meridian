<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');

    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

    Route::get('campaigns/{campaign}', [CampaignController::class, 'dashboard'])
        ->middleware('campaign.access')
        ->name('campaigns.dashboard');

    Route::middleware('campaign.access')->scopeBindings()->group(function () {
        Route::get('campaigns/{campaign}/contacts', [ContactController::class, 'index'])
            ->name('campaigns.contacts.index');

        Route::post('campaigns/{campaign}/contacts', [ContactController::class, 'store'])
            ->name('campaigns.contacts.store');

        Route::put('campaigns/{campaign}/contacts/{contact}', [ContactController::class, 'update'])
            ->name('campaigns.contacts.update');

        Route::delete('campaigns/{campaign}/contacts/{contact}', [ContactController::class, 'destroy'])
            ->name('campaigns.contacts.destroy');
    });
});

require __DIR__.'/settings.php';
