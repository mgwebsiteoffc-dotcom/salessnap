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
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'app/webhooks/*',
            'webhooks/shopify',
            'app/webhooks/shopify',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $msg = $e->getMessage();
                $status = 500;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                } elseif (str_contains($msg, '401') || str_contains($msg, 'session expired')) {
                    $status = 401;
                } elseif (str_contains($msg, '403') || str_contains($msg, 'permissions') || str_contains($msg, 'scope') || str_contains($msg, 're-authorize')) {
                    $status = 403;
                }

                return response()->json([
                    'message' => $msg,
                    'reauthorize' => in_array($status, [401, 403], true) || str_contains($msg, 're-authorize') || str_contains($msg, 'permission'),
                ], $status);
            }
        });
    })->create();
