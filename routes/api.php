<?php
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['shopify.session', 'throttle:120,1'])->group(function () {
    Route::get('/dashboard', [CampaignController::class, 'dashboard']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/collections', [ProductController::class, 'collections']);
    Route::get('/snapshots', [CampaignController::class, 'snapshots']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::post('/campaigns/{campaign}/retry', [CampaignController::class, 'retry'])->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/restore', [CampaignController::class, 'restore'])->whereNumber('campaign');

    // Billing API
    Route::get('/billing', [BillingController::class, 'index']);
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);
    Route::post('/billing/cancel', [BillingController::class, 'cancel']);
});
