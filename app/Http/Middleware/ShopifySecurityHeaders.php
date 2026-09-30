<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShopifySecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, X-Requested-With',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        $response = $next($request);
        $response->headers->set('Content-Security-Policy', "frame-ancestors https://admin.shopify.com https://*.myshopify.com https://*.spin.dev; object-src 'none'; base-uri 'self';");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->remove('X-Frame-Options');
        return $response;
    }
}
