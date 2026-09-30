<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstallRedirectController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/auth', [AuthController::class, 'start'])->middleware('throttle:60,1')->name('shopify.auth.start');
Route::get('/app/auth', [AuthController::class, 'start'])->middleware('throttle:60,1')->name('shopify.auth.start.app');

Route::get('/auth/callback', [AuthController::class, 'callback'])->middleware('throttle:60,1')->name('shopify.auth.callback');
Route::get('/app/auth/callback', [AuthController::class, 'callback'])->middleware('throttle:60,1')->name('shopify.auth.callback.app');

Route::post('/webhooks/shopify', WebhookController::class)->name('shopify.webhooks');
Route::post('/app/webhooks/shopify', WebhookController::class)->name('shopify.webhooks.app');

Route::get('/app', AppController::class)->name('app');
Route::get('/install', InstallRedirectController::class)->name('shopify.install');
Route::get('/support', SupportController::class)->name('support');
Route::get('/privacy', fn() => response()->view('legal-placeholder', ['page' => 'privacy']))->name('privacy');
Route::get('/terms', fn() => response()->view('legal-placeholder', ['page' => 'terms']))->name('terms');
Route::get('/', MarketingController::class)->name('marketing.home');
