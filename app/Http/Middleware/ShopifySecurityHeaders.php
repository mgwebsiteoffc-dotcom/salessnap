<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class ShopifySecurityHeaders {
    public function handle(Request $request, Closure $next): Response {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', "frame-ancestors https://admin.shopify.com https://*.myshopify.com; object-src 'none'; base-uri 'self';");
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');
        return $response;
    }
}
