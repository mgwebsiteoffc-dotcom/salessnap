<?php
namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifySessionToken {
    public function handle(Request $request, Closure $next): Response {
        $jwt = $request->bearerToken();
        abort_unless(is_string($jwt) && $jwt !== '', 401, 'A Shopify session token is required.');
        $parts = explode('.', $jwt);
        abort_unless(count($parts) === 3, 401, 'Invalid session token.');
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode($this->decode($encodedHeader), true);
        $claims = json_decode($this->decode($encodedPayload), true);
        abort_unless(is_array($header) && ($header['alg'] ?? null) === 'HS256' && is_array($claims), 401, 'Invalid session token.');
        $secret = (string) config('shopify.api_secret');
        abort_unless($secret !== '', 500, 'Shopify credentials are not configured.');
        $expected = $this->base64Url(hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $secret, true));
        abort_unless(hash_equals($expected, $encodedSignature), 401, 'Invalid session token signature.');

        $now = time();
        $leeway = 120; // 2 minutes clock skew tolerance
        abort_unless(
            isset($claims['exp'], $claims['nbf'], $claims['iat'])
            && (int)$claims['exp'] >= ($now - $leeway)
            && (int)$claims['nbf'] <= ($now + $leeway)
            && (int)$claims['iat'] <= ($now + $leeway),
            401,
            'Session token expired or not yet valid.'
        );

        $aud = $claims['aud'] ?? null;
        abort_unless($aud === config('shopify.api_key') || (is_array($aud) && in_array(config('shopify.api_key'), $aud, true)), 401, 'Session token audience mismatch.');

        $dest = (string)($claims['dest'] ?? '');
        $iss = (string)($claims['iss'] ?? '');
        $shopDomain = $this->extractShopDomain($dest, $iss);
        abort_unless($shopDomain !== null, 401, 'Session token shop mismatch.');

        $shop = Shop::where('shop_domain', $shopDomain)->first();
        if (!$shop) {
            $shop = Shop::create([
                'shop_domain' => $shopDomain,
                'installed_at' => now(),
            ]);
        }

        // If access token is missing, expired, or expiring, perform immediate Token Exchange
        if (empty($shop->access_token) || ($shop->token_expires_at && $shop->token_expires_at->lte(now()->addMinutes(2)))) {
            $this->exchangeSessionToken($shop, $jwt);
        }

        $request->attributes->set('shop', $shop);
        $request->attributes->set('shopify_claims', $claims);
        $request->attributes->set('shopify_session_token', $jwt);

        return $next($request);
    }

    public static function exchangeSessionToken(Shop $shop, string $idToken): bool {
        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post("https://{$shop->shop_domain}/admin/oauth/access_token", [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                'subject_token' => $idToken,
                'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
                'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
            ]);

            if ($response->successful() && $response->json('access_token')) {
                $data = $response->json();
                $expiresIn = isset($data['expires_in']) ? (int)$data['expires_in'] : null;
                $refreshTokenExpiresIn = isset($data['refresh_token_expires_in']) ? (int)$data['refresh_token_expires_in'] : null;

                $shop->forceFill([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $shop->refresh_token,
                    'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                    'refresh_token_expires_at' => $refreshTokenExpiresIn ? now()->addSeconds($refreshTokenExpiresIn) : $shop->refresh_token_expires_at,
                    'granted_scopes' => $data['scope'] ?? $shop->granted_scopes,
                    'uninstalled_at' => null,
                ])->save();

                Log::info("Successfully exchanged session token for offline access token for shop: {$shop->shop_domain}");
                return true;
            } else {
                Log::warning("Shopify token exchange rejected", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Shopify token exchange exception: ' . $e->getMessage());
        }
        return false;
    }

    private function decode(string $value): string {
        $result = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        abort_unless($result !== false, 401, 'Invalid token encoding.');
        return $result;
    }

    private function base64Url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function extractShopDomain(string $dest, string $iss): ?string {
        $destParts = parse_url($dest);
        if (!is_array($destParts) || ($destParts['scheme'] ?? '') !== 'https') {
            return null;
        }
        $destHost = strtolower((string)($destParts['host'] ?? ''));
        if (!preg_match('/\A([a-z0-9][a-z0-9-]*)\.(?:myshopify\.com|myshopify\.io|spin\.dev)\z/i', $destHost, $destMatch)) {
            return null;
        }
        $shopHandle = strtolower($destMatch[1]);
        $shopDomain = strtolower($destMatch[0]);

        $issParts = parse_url($iss);
        if (!is_array($issParts) || ($issParts['scheme'] ?? '') !== 'https') {
            return null;
        }
        $issHost = strtolower((string)($issParts['host'] ?? ''));
        $issPath = rtrim((string)($issParts['path'] ?? ''), '/');

        if ($issHost === 'admin.shopify.com') {
            if ($issPath !== '' && $issPath !== '/' && $issPath !== '/admin') {
                if (preg_match('~/store/([a-z0-9-]+)~i', $issPath, $pm)) {
                    if (strtolower($pm[1]) !== $shopHandle) {
                        return null;
                    }
                }
            }
            return $shopDomain;
        }

        if (preg_match('/\A([a-z0-9][a-z0-9-]*)\.(?:myshopify\.com|myshopify\.io|spin\.dev)\z/i', $issHost, $issMatch)) {
            if (strtolower($issMatch[1]) !== $shopHandle) {
                return null;
            }
            return $shopDomain;
        }

        return null;
    }
}
