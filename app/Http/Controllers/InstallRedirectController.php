<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class InstallRedirectController {
    public function __invoke(Request $request) {
        $target=(string)config('shopify.store_listing_url','');
        $parts=parse_url($target);
        $valid=is_array($parts)
            && strtolower((string)($parts['scheme']??''))==='https'
            && strtolower((string)($parts['host']??''))==='apps.shopify.com'
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port']) && !isset($parts['fragment'])
            && preg_match('~\A/[a-z0-9][a-z0-9-]*(?:/[a-z0-9][a-z0-9-]*)?/?\z~i',(string)($parts['path']??''));
        if(!$valid) return response()->view('install-pending',[],503);
        return redirect()->away($target,302,['Cache-Control'=>'no-store']);
    }
}
