<?php

use App\Http\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');

    Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');

    Route::get('campaigns/{campaign}', [CampaignController::class, 'dashboard'])
        ->middleware('campaign.access')
        ->name('campaigns.dashboard');
});

require __DIR__.'/settings.php';
