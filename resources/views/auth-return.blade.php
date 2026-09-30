<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="shopify-api-key" content="{{ config('shopify.api_key') }}">
<title>Opening SaleSnap</title>
<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
<style>
* { box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    margin: 0;
    background: #f6f6f7;
    color: #202223;
    padding: 20px;
}
.card {
    background: #ffffff;
    padding: 36px 32px;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    text-align: center;
    max-width: 440px;
    width: 100%;
}
.spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #e1e3e5;
    border-top-color: #008060;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 20px;
}
@keyframes spin { to { transform: rotate(360deg); } }
h1 {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 10px;
    color: #202223;
}
p {
    font-size: 14px;
    color: #6d7175;
    line-height: 1.5;
    margin: 0 0 24px;
}
.btn {
    display: inline-block;
    background: #008060;
    color: #ffffff;
    text-decoration: none;
    padding: 11px 24px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.15s ease;
}
.btn:hover { background: #006e52; }
</style>
</head>
<body>
@php
    $rawBaseUrl = (string)(config('shopify.app_url') ?: request()->getSchemeAndHttpHost());
    $appBaseUrl = preg_replace('~/app/?\z~i', '', rtrim($rawBaseUrl, '/'));
    $destinationParams = ['shop' => $shop];
    if (!empty($host)) {
        $destinationParams['host'] = $host;
    }
    $destinationUrl = $appBaseUrl . '/app?' . http_build_query($destinationParams);
@endphp
<div class="card">
    <div class="spinner"></div>
    <h1>Opening SaleSnap</h1>
    <p>Installation authorized. Returning to Shopify Admin…</p>
    <a href="{{ $destinationUrl }}" target="_top" class="btn" id="return-btn">Continue to SaleSnap</a>
</div>
<script>
(() => {
    const destinationUrl = @json($destinationUrl);
    function redirect() {
        if (window.shopify && typeof window.shopify.open === 'function') {
            window.shopify.open(destinationUrl, '_top');
        } else if (typeof open === 'function' && window !== window.top) {
            try {
                open(destinationUrl, '_top');
            } catch (e) {
                try { window.top.location.href = destinationUrl; } catch (err) {}
            }
        } else {
            try { window.top.location.href = destinationUrl; } catch (e) {
                window.location.href = destinationUrl;
            }
        }
    }
    redirect();
    setTimeout(redirect, 500);
})();
</script>
<noscript>
    <p><a href="{{ $destinationUrl }}">Continue to SaleSnap</a></p>
</noscript>
</body>
</html>
