<?php
namespace App\Http\Controllers;

use App\Models\OAuthState;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController {
    public function start(Request $request) {
        $shop = strtolower((string)$request->query('shop'));
        abort_unless($this->validShop($shop), 400, 'A valid *.myshopify.com shop domain is required.');

        $apiKey = (string) config('shopify.api_key');
        $apiSecret = (string) config('shopify.api_secret');
        abort_unless($apiKey !== '' && $apiSecret !== '', 500, 'Shopify app credentials (SHOPIFY_API_KEY and SHOPIFY_API_SECRET) are not configured.');

        $state = Str::random(48);
        try {
            OAuthState::where('expires_at', '<', now())->delete();
            OAuthState::create([
                'state_hash' => hash('sha256', $state),
                'shop_domain' => $shop,
                'expires_at' => now()->addMinutes(10),
            ]);
        } catch (\Throwable $e) {
            Log::error('OAuth state creation failed: ' . $e->getMessage());
            abort(500, 'Database error while preparing Shopify authorization. Run database migrations with `php artisan migrate`.');
        }

        $appUrl = rtrim((string)(config('shopify.app_url') ?: $request->getSchemeAndHttpHost()), '/');
        $params = [
            'client_id' => $apiKey,
            'scope' => (string) config('shopify.scopes', 'read_products,write_products'),
            'redirect_uri' => $appUrl . '/auth/callback',
            'state' => $state,
        ];
        return redirect()->away("https://{$shop}/admin/oauth/authorize?" . http_build_query($params));
    }

    public function callback(Request $request) {
        $query = $request->query();
        $shop = strtolower((string)($query['shop'] ?? ''));
        abort_unless($this->validShop($shop), 400, 'Invalid shop domain.');
        abort_unless(isset($query['timestamp']) && abs(time() - (int)$query['timestamp']) <= 600, 401, 'OAuth callback is expired. Please retry installation.');
        abort_unless($this->validHmac($query), 401, 'Invalid Shopify OAuth signature.');

        $state = (string)($query['state'] ?? '');
        try {
            $row = OAuthState::where('state_hash', hash('sha256', $state))->where('shop_domain', $shop)->where('expires_at', '>', now())->first();
        } catch (\Throwable $e) {
            Log::error('OAuth callback state query failed: ' . $e->getMessage());
            abort(500, 'Database error during OAuth callback. Ensure database is running and migrated.');
        }

        abort_unless($row, 401, 'OAuth state is invalid or expired. Please start installation again.');
        $row->delete();

        $response = Http::asForm()->acceptJson()->timeout(15)->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('shopify.api_key'),
            'client_secret' => config('shopify.api_secret'),
            'code' => (string)$request->query('code'),
        ]);

        if (!$response->successful() || !$response->json('access_token')) {
            Log::error('Shopify access token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            abort(502, 'Failed to obtain access token from Shopify (HTTP ' . $response->status() . '). Please retry installation.');
        }

        $data = $response->json();
        $granted = array_filter(explode(',', (string)($data['scope'] ?? '')));
        foreach (array_filter(explode(',', (string)config('shopify.scopes'))) as $required) {
            abort_unless(in_array(trim($required), array_map('trim', $granted), true), 403, 'Shopify did not grant a required product permission. Reinstall and approve the requested permissions.');
        }

        $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : null;
        $refreshTokenExpiresIn = isset($data['refresh_token_expires_in']) ? (int)$data['refresh_token_expires_in'] : null;

        try {
            Shop::updateOrCreate(
                ['shop_domain' => $shop],
                [
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                    'refresh_token_expires_at' => $refreshTokenExpiresIn ? now()->addSeconds($refreshTokenExpiresIn) : null,
                    'granted_scopes' => $data['scope'] ?? config('shopify.scopes'),
                    'installed_at' => now(),
                    'uninstalled_at' => null,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to save shop record in database: ' . $e->getMessage());
            abort(500, 'Database error saving shop credentials. Run `php artisan migrate`.');
        }

        return response()->view('auth-return', [
            'shop' => $shop,
            'host' => (string)$request->query('host', ''),
        ]);
    }

    private function validShop(string $shop): bool {
        return (bool)preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/', $shop);
    }

    private function validHmac(array $query): bool {
        $given = (string)($query['hmac'] ?? '');
        unset($query['hmac'], $query['signature']);
        ksort($query);
        $pairs = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) continue;
            $pairs[] = $key . '=' . $value;
        }
        $expected = hash_hmac('sha256', implode('&', $pairs), (string)config('shopify.api_secret'));
        return $given !== '' && hash_equals($expected, $given);
    }
}
