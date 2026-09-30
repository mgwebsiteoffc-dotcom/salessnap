<?php
namespace App\Http\Controllers;
use App\Models\OAuthState;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController {
    public function start(Request $request) {
        $shop = strtolower((string)$request->query('shop'));
        abort_unless($this->validShop($shop), 400, 'A valid *.myshopify.com shop is required.');
        // Always re-authorize on a fresh Shopify install/reinstall entry point.
        $state = Str::random(48);
        OAuthState::where('expires_at','<',now())->delete();
        OAuthState::create(['state_hash'=>hash('sha256',$state),'shop_domain'=>$shop,'expires_at'=>now()->addMinutes(10)]);
        $params = http_build_query(['client_id'=>config('shopify.api_key'),'scope'=>config('shopify.scopes'),'redirect_uri'=>rtrim(config('shopify.app_url'),'/').'/auth/callback','state'=>$state]);
        return redirect()->away("https://{$shop}/admin/oauth/authorize?".$params);
    }
    public function callback(Request $request) {
        $query = $request->query();
        $shop = strtolower((string)($query['shop'] ?? ''));
        abort_unless($this->validShop($shop), 400, 'Invalid shop domain.');
        abort_unless(isset($query['timestamp']) && abs(time()-(int)$query['timestamp'])<=600, 401, 'OAuth callback is expired. Please retry installation.');
        abort_unless($this->validHmac($query), 401, 'Invalid Shopify OAuth signature.');
        $state = (string)($query['state'] ?? '');
        $row = OAuthState::where('state_hash',hash('sha256',$state))->where('shop_domain',$shop)->where('expires_at','>',now())->first();
        abort_unless($row, 401, 'OAuth state is invalid or expired. Please start installation again.');
        $row->delete();
        $response = Http::asForm()->acceptJson()->timeout(15)->post("https://{$shop}/admin/oauth/access_token",[
            'client_id'=>config('shopify.api_key'),'client_secret'=>config('shopify.api_secret'),'code'=>(string)$request->query('code'),'expiring'=>'1'
        ]);
        abort_unless($response->successful() && $response->json('access_token') && $response->json('expires_in') && $response->json('refresh_token') && $response->json('refresh_token_expires_in'), 502, 'Shopify did not issue an expiring offline token. Please retry installation.');
        $data = $response->json();
        $granted=array_filter(explode(',',(string)($data['scope'] ?? '')));
        foreach(array_filter(explode(',',(string)config('shopify.scopes'))) as $required) abort_unless(in_array(trim($required),array_map('trim',$granted),true),403,'Shopify did not grant a required product permission. Reinstall and approve the requested permissions.');
        Shop::updateOrCreate(['shop_domain'=>$shop],['access_token'=>$data['access_token'],'refresh_token'=>$data['refresh_token'] ?? null,'token_expires_at'=>isset($data['expires_in'])?now()->addSeconds((int)$data['expires_in']):null,'refresh_token_expires_at'=>isset($data['refresh_token_expires_in'])?now()->addSeconds((int)$data['refresh_token_expires_in']):null,'granted_scopes'=>$data['scope'] ?? config('shopify.scopes'),'installed_at'=>now(),'uninstalled_at'=>null]);
        return response()->view('auth-return',['shop'=>$shop,'host'=>(string)$request->query('host','')]);
    }
    private function validShop(string $shop): bool { return (bool)preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/',$shop); }
    private function validHmac(array $query): bool {
        $given = (string)($query['hmac'] ?? ''); unset($query['hmac'],$query['signature']); ksort($query);
        $pairs=[]; foreach($query as $key=>$value){ if(is_array($value)) continue; $pairs[]=$key.'='.$value; }
        $expected=hash_hmac('sha256',implode('&',$pairs),(string)config('shopify.api_secret'));
        return $given!=='' && hash_equals($expected,$given);
    }
}
