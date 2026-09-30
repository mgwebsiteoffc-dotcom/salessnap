<?php
namespace App\Http\Controllers;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class WebhookController {
    public function __invoke(Request $request) {
        $raw=$request->getContent();
        $given=(string)$request->header('X-Shopify-Hmac-Sha256','');
        $expected=base64_encode(hash_hmac('sha256',$raw,(string)config('shopify.webhook_secret'),true));
        abort_unless($given!=='' && hash_equals($expected,$given),401,'Invalid webhook signature.');
        $shopDomain=strtolower((string)$request->header('X-Shopify-Shop-Domain',''));
        $topic=strtolower((string)$request->header('X-Shopify-Topic',''));
        if ($topic==='app/uninstalled') {
            // Erase shop credentials and associated campaign/snapshot records on uninstall.
            Shop::where('shop_domain',$shopDomain)->delete();
            \App\Models\OAuthState::where('shop_domain',$shopDomain)->delete();
        } elseif ($topic==='shop/redact') {
            Shop::where('shop_domain',$shopDomain)->delete();
            \App\Models\OAuthState::where('shop_domain',$shopDomain)->delete();
        } elseif (in_array($topic,['customers/data_request','customers/redact'],true)) {
            // This app does not request or persist customer data. Acknowledge without logging payload PII.
        } else {
            Log::notice('Unrecognized Shopify webhook topic',['topic'=>$topic]);
        }
        return response()->json(['received'=>true],200);
    }
}
