<?php
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BundleController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['shopify.session', 'throttle:120,1'])->group(function () {
    Route::get('/dashboard', [CampaignController::class, 'dashboard']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/collections', [ProductController::class, 'collections']);
    Route::get('/snapshots', [CampaignController::class, 'snapshots']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/start-now', [CampaignController::class, 'startNow'])->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/retry', [CampaignController::class, 'retry'])->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->whereNumber('campaign');
    Route::post('/campaigns/{campaign}/restore', [CampaignController::class, 'restore'])->whereNumber('campaign');

    // Bundles API
    Route::get('/bundles', [BundleController::class, 'index']);
    Route::post('/bundles', [BundleController::class, 'store']);
    Route::post('/bundles/bulk-multipack', [BundleController::class, 'bulkMultipack']);

    // Themes & Countdown Publishing API
    Route::get('/themes', [ThemeController::class, 'index']);
    Route::post('/themes/duplicate', [ThemeController::class, 'duplicate']);
    Route::post('/themes/publish', [ThemeController::class, 'publish']);
    Route::post('/themes/revert', [ThemeController::class, 'revert']);
    Route::post('/themes/inject', [ThemeController::class, 'inject']);

    // Settings API
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'update']);

    // Billing API
    Route::get('/billing', [BillingController::class, 'index']);
    Route::post('/billing/subscribe', [BillingController::class, 'subscribe']);
    Route::post('/billing/cancel', [BillingController::class, 'cancel']);
});
