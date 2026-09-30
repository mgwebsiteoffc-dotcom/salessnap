<?php

use App\Http\Middleware\VerifyShopifySessionToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withCommands([\App\Console\Commands\DispatchScheduledCampaigns::class, \App\Console\Commands\PurgePromotionData::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['shopify.session' => VerifyShopifySessionToken::class]);
        $middleware->append(\App\Http\Middleware\ShopifySecurityHeaders::class);
        $middleware->validateCsrfTokens(except: ['webhooks/shopify']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Report errors through the configured production logger; never expose details with APP_DEBUG=false.
    })->create();
