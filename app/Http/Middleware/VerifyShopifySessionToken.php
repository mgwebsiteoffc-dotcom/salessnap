<?php
namespace App\Http\Middleware;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Closure;
use Illuminate\Http\Request;
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
        $expected = $this->base64Url(hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $secret, true));
        abort_unless(hash_equals($expected, $encodedSignature), 401, 'Invalid session token signature.');
        $now = time();
        abort_unless(isset($claims['exp'], $claims['nbf'], $claims['iat']) && (int)$claims['exp'] >= $now && (int)$claims['nbf'] <= $now && (int)$claims['iat'] <= $now + 5, 401, 'Session token expired or not yet valid.');
        abort_unless(((int)($claims['exp'] ?? 0) - (int)($claims['iat'] ?? 0)) <= (int)config('shopify.session_token_max_age', 90) + 30, 401, 'Session token lifetime is invalid.');
        $aud = $claims['aud'] ?? null;
        abort_unless($aud === config('shopify.api_key') || (is_array($aud) && in_array(config('shopify.api_key'), $aud, true)), 401, 'Session token audience mismatch.');
        $shopDomain = $this->shopFromUrl((string)($claims['dest'] ?? ''));
        $issuerShop = $this->shopFromUrl((string)($claims['iss'] ?? ''));
        abort_unless($shopDomain !== null && hash_equals($shopDomain, (string)$issuerShop) && rtrim((string)parse_url((string)($claims['iss'] ?? ''), PHP_URL_PATH), '/') === '/admin', 401, 'Session token shop mismatch.');
        $shop = Shop::where('shop_domain', $shopDomain)->whereNull('uninstalled_at')->first();
        abort_unless($shop, 401, 'This shop is not installed. Re-open the app from Shopify Admin.');
        $this->exchangeIfExpiring($shop, $jwt);
        $request->attributes->set('shop', $shop);
        $request->attributes->set('shopify_claims', $claims);
        return $next($request);
    }

    private function exchangeIfExpiring(Shop $shop, string $idToken): void {
        if(!$shop->token_expires_at || $shop->token_expires_at->gt(now()->addMinutes(2))) return;
        try {
            DB::transaction(function() use($shop,$idToken){
                $locked=Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
                if(!$locked->token_expires_at || $locked->token_expires_at->gt(now()->addMinutes(2))){$shop->refresh();return;}
                $response=Http::asForm()->acceptJson()->timeout(15)->post("https://{$locked->shop_domain}/admin/oauth/access_token",[
                    'client_id'=>config('shopify.api_key'),'client_secret'=>config('shopify.api_secret'),
                    'grant_type'=>'urn:ietf:params:oauth:grant-type:token-exchange','subject_token'=>$idToken,
                    'subject_token_type'=>'urn:shopify:params:oauth:token-type:id_token',
                    'requested_token_type'=>'urn:shopify:params:oauth:token-type:offline-access-token','expiring'=>'1'
                ]);
                if(!$response->successful() || !$response->json('access_token') || !$response->json('expires_in') || !$response->json('refresh_token') || !$response->json('refresh_token_expires_in')) throw new RuntimeException('Shopify token exchange failed. Re-open the app or re-authorize it.');
                $data=$response->json();
                $locked->forceFill(['access_token'=>$data['access_token'],'refresh_token'=>$data['refresh_token'],'token_expires_at'=>now()->addSeconds((int)$data['expires_in']),'refresh_token_expires_at'=>isset($data['refresh_token_expires_in'])?now()->addSeconds((int)$data['refresh_token_expires_in']):$locked->refresh_token_expires_at])->save();
                $shop->refresh();
            });
        } catch(\Throwable $e) {
            if($e instanceof RuntimeException) abort(503,$e->getMessage());
            abort(503,'Could not renew Shopify access. Retry from Shopify Admin.');
        }
    }
    private function decode(string $value): string { $result = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true); abort_unless($result !== false, 401, 'Invalid token encoding.'); return $result; }
    private function base64Url(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private function shopFromUrl(string $url): ?string {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https' || parse_url($url, PHP_URL_PORT) !== null) return null;
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!preg_match('/\A([a-z0-9][a-z0-9-]*\.myshopify\.com)\z/', $host, $m)) return null;
        return $m[1];
    }
}
