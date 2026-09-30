<?php
use App\Http\Controllers\AppController;
use App\Http\Controllers\InstallRedirectController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
Route::get('/auth',[AuthController::class,'start'])->middleware('throttle:30,1')->name('shopify.auth.start');
Route::get('/auth/callback',[AuthController::class,'callback'])->middleware('throttle:30,1')->name('shopify.auth.callback');
Route::post('/webhooks/shopify',WebhookController::class)->name('shopify.webhooks');
Route::get('/app',AppController::class)->name('app');
Route::get('/install',InstallRedirectController::class)->name('shopify.install');
Route::get('/support',SupportController::class)->name('support');
Route::get('/privacy',fn()=>response()->view('legal-placeholder',['page'=>'privacy']))->name('privacy');
Route::get('/terms',fn()=>response()->view('legal-placeholder',['page'=>'terms']))->name('terms');
Route::get('/',\App\Http\Controllers\MarketingController::class)->name('marketing.home');
