<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppController {
    public function __invoke(Request $request) {
        $shop = strtolower((string)$request->query('shop', ''));
        abort_unless(preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/', $shop), 400, 'Open this app from your Shopify Admin.');
        $rawUrl = (string)(config('shopify.app_url') ?: $request->getSchemeAndHttpHost());
        $appUrl = preg_replace('~/app/?\z~i', '', rtrim($rawUrl, '/'));
        $host = (string)$request->query('host', '');

        try {
            $isInstalled = Shop::where('shop_domain', $shop)->whereNull('uninstalled_at')->exists();
        } catch (\Throwable $e) {
            Log::error('Database error in AppController: ' . $e->getMessage());
            abort(500, 'Database connection error. Ensure database credentials in .env are correct and migrations have been run (`php artisan migrate --force`).');
        }

        if (!$isInstalled) {
            $authParams = ['shop' => $shop];
            if ($host !== '') {
                $authParams['host'] = $host;
            }
            return response()->view('auth-required', [
                'shop' => $shop,
                'host' => $host,
                'apiKey' => config('shopify.api_key'),
                'authUrl' => $appUrl . '/auth?' . http_build_query($authParams),
            ]);
        }

        return response()->view('app', [
            'apiKey' => config('shopify.api_key'),
            'shop' => $shop,
            'host' => $host,
        ]);
    }
}
