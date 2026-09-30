<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class AppController {
    public function __invoke(Request $request) {
        $shop = strtolower((string)$request->query('shop', ''));
        abort_unless(preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/', $shop), 400, 'Open this app from your Shopify Admin.');
        $appUrl = rtrim((string)(config('shopify.app_url') ?: $request->getSchemeAndHttpHost()), '/');
        $host = (string)$request->query('host', '');

        if (!Shop::where('shop_domain', $shop)->whereNull('uninstalled_at')->exists()) {
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
