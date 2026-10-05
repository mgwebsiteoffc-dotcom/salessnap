<?php
namespace App\Services;

use App\Http\Middleware\VerifyShopifySessionToken;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShopifyGraphql {
    public function query(Shop $shop, string $query, array $variables = []): array {
        $this->refreshIfNeeded($shop);
        $response = $this->sendQuery($shop, $query, $variables);

        if (!$response->successful() && in_array($response->status(), [401, 403], true)) {
            $bodyStr = (string)$response->body();
            Log::warning("Shopify GraphQL HTTP {$response->status()} for {$shop->shop_domain}. Body: {$bodyStr}. Attempting token renewal...");
            
            $renewed = false;
            // 1. If it's a legacy non-expiring token deprecation error, migrate directly
            if (str_contains($bodyStr, 'Non-expiring access tokens') || str_contains($bodyStr, 'offline-access-tokens') || empty($shop->token_expires_at)) {
                $renewed = VerifyShopifySessionToken::migrateOfflineTokenToExpiring($shop);
            }

            // 2. If not renewed, try session token exchange
            if (!$renewed) {
                $sessionToken = request()->attributes->get('shopify_session_token') ?: request()->bearerToken();
                if ($sessionToken) {
                    $renewed = VerifyShopifySessionToken::exchangeSessionToken($shop, $sessionToken);
                }
            }

            // 3. If renewed, refresh shop model and retry query
            if ($renewed) {
                $shop->refresh();
                Log::info("Retrying Shopify GraphQL query after successful token renewal for {$shop->shop_domain}");
                $response = $this->sendQuery($shop, $query, $variables);
            }
        }

        if (!$response->successful()) {
            $status = $response->status();
            $bodyText = $response->body();
            Log::warning("Shopify GraphQL request failed with HTTP {$status} for {$shop->shop_domain}: {$bodyText}");

            if ($status === 401 || $status === 403) {
                throw new RuntimeException("Shopify API permissions expired or scope upgrade required (HTTP {$status}). Please re-authorize the app in Shopify Admin.");
            }
            throw new RuntimeException("Shopify API request failed (HTTP {$status}).");
        }

        $body = $response->json();
        if (!empty($body['errors'])) {
            $errMsg = is_array($body['errors']) ? json_encode($body['errors']) : (string)$body['errors'];
            Log::warning("Shopify GraphQL returned errors for {$shop->shop_domain}: {$errMsg}");
            if (str_contains($errMsg, 'ACCESS_DENIED') || str_contains($errMsg, 'access scope') || str_contains($errMsg, 'permission')) {
                throw new RuntimeException("Shopify API scope approval required: {$errMsg}");
            }
            throw new RuntimeException('Shopify GraphQL error: ' . mb_substr($errMsg, 0, 1200));
        }

        return $body['data'] ?? [];
    }

    private function sendQuery(Shop $shop, string $query, array $variables = []) {
        $payload = ['query' => $query];
        if (!empty($variables)) {
            $payload['variables'] = $variables;
        }

        return Http::withHeaders([
            'X-Shopify-Access-Token' => (string) $shop->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->timeout(30)
        ->retry(2, 300, throw: false)
        ->post("https://{$shop->shop_domain}/admin/api/" . config('shopify.api_version') . '/graphql.json', $payload);
    }

    public function productsByIds(Shop $shop, array $ids): array {
        if (count($ids) > 250) {
            throw new RuntimeException('A campaign can include at most 250 products in this release.');
        }

        $query = <<<'GQL'
query CampaignProduct($id: ID!) {
  node(id: $id) {
    ... on Product {
      id title handle descriptionHtml tags status
      featuredImage { url altText }
      variants(first: 250) {
        nodes { id title price compareAtPrice sku }
        pageInfo { hasNextPage }
      }
    }
  }
}
GQL;
        $items = [];
        foreach ($ids as $id) {
            $node = $this->query($shop, $query, ['id' => $id])['node'] ?? null;
            if (!$node || empty($node['id'])) continue;
            if ($node['variants']['pageInfo']['hasNextPage'] ?? false) {
                throw new RuntimeException('A selected product has more than 250 variants. It was skipped for safety.');
            }
            $items[$node['id']] = [
                'id' => $node['id'],
                'title' => $node['title'] ?? '',
                'handle' => $node['handle'] ?? '',
                'image' => $node['featuredImage']['url'] ?? null,
                'descriptionHtml' => $node['descriptionHtml'] ?? '',
                'tags' => $node['tags'] ?? [],
                'status' => $node['status'] ?? 'ACTIVE',
                'variants' => $node['variants']['nodes'] ?? [],
            ];
        }
        return $items;
    }

    public function searchProducts(Shop $shop, string $term = ''): array {
        $query = <<<'GQL'
query ProductSearch($query: String!) {
  products(first: 50, query: $query, sortKey: TITLE) {
    nodes {
      id
      title
      handle
      status
      featuredImage { url altText }
      variants(first: 5) {
        nodes { id price title }
      }
    }
  }
}
GQL;
        $q = $term !== '' ? 'title:' . str_replace(['\\', '*'], ['', ''], $term) . '*' : '';
        $nodes = $this->query($shop, $query, ['query' => $q])['products']['nodes'] ?? [];
        return array_map(function ($p) {
            return [
                'id' => $p['id'],
                'title' => $p['title'] ?? '',
                'handle' => $p['handle'] ?? '',
                'status' => $p['status'] ?? 'ACTIVE',
                'image' => $p['featuredImage']['url'] ?? null,
                'price' => $p['variants']['nodes'][0]['price'] ?? '0.00',
                'variants_count' => count($p['variants']['nodes'] ?? []),
            ];
        }, $nodes);
    }

    public function searchCollections(Shop $shop, string $term = ''): array {
        $query = <<<'GQL'
query CollectionSearch($query: String!) {
  collections(first: 50, query: $query, sortKey: TITLE) {
    nodes {
      id
      title
      handle
      productsCount { count }
      image { url altText }
    }
  }
}
GQL;
        $q = $term !== '' ? 'title:' . str_replace(['\\', '*'], ['', ''], $term) . '*' : '';
        $nodes = $this->query($shop, $query, ['query' => $q])['collections']['nodes'] ?? [];
        return array_map(function ($c) {
            return [
                'id' => $c['id'],
                'title' => $c['title'] ?? 'Collection',
                'handle' => $c['handle'] ?? '',
                'products_count' => (int) ($c['productsCount']['count'] ?? 0),
                'image' => $c['image']['url'] ?? null,
            ];
        }, $nodes);
    }

    public function productsByCollection(Shop $shop, string $collectionId): array {
        $query = <<<'GQL'
query ProductsByCollection($id: ID!) {
  collection(id: $id) {
    id
    title
    products(first: 100) {
      nodes {
        id
        title
        handle
        status
        featuredImage { url altText }
        variants(first: 5) {
          nodes { id price title }
        }
      }
    }
  }
}
GQL;
        $nodes = $this->query($shop, $query, ['id' => $collectionId])['collection']['products']['nodes'] ?? [];
        return array_map(function ($p) {
            return [
                'id' => $p['id'],
                'title' => $p['title'] ?? '',
                'handle' => $p['handle'] ?? '',
                'status' => $p['status'] ?? 'ACTIVE',
                'image' => $p['featuredImage']['url'] ?? null,
                'price' => $p['variants']['nodes'][0]['price'] ?? '0.00',
                'variants_count' => count($p['variants']['nodes'] ?? []),
            ];
        }, $nodes);
    }

    public function updateProduct(Shop $shop, string $id, array $input): void {
        $query = <<<'GQL'
mutation ProductUpdate($product: ProductUpdateInput!) {
  productUpdate(product: $product) {
    product { id }
    userErrors { field message }
  }
}
GQL;
        $res = $this->query($shop, $query, ['product' => array_merge(['id' => $id], $input)])['productUpdate'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);
    }

    public function updateVariantPrices(Shop $shop, string $productId, array $variants): void {
        if (!$variants) return;

        $bulkQuery = <<<'GQL'
mutation VariantPrices($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
  productVariantsBulkUpdate(productId: $productId, variants: $variants) {
    product { id }
    userErrors { field message }
  }
}
GQL;

        $formatted = array_map(function ($v) {
            $item = [
                'id' => (string)$v['id'],
                'price' => (string)$v['price'],
            ];
            if (array_key_exists('compareAtPrice', $v)) {
                $item['compareAtPrice'] = $v['compareAtPrice'] !== null ? (string)$v['compareAtPrice'] : null;
            }
            return $item;
        }, $variants);

        $hasError = false;
        try {
            $res = $this->query($shop, $bulkQuery, ['productId' => $productId, 'variants' => $formatted])['productVariantsBulkUpdate'] ?? [];
            if (!empty($res['userErrors'])) {
                Log::warning("productVariantsBulkUpdate userErrors for {$productId}: " . json_encode($res['userErrors']));
                $hasError = true;
            }
        } catch (\Throwable $e) {
            Log::warning("productVariantsBulkUpdate failed for {$productId}: " . $e->getMessage());
            $hasError = true;
        }

        if ($hasError) {
            $singleQuery = <<<'GQL'
mutation ProductVariantUpdate($input: ProductVariantInput!) {
  productVariantUpdate(input: $input) {
    productVariant { id price compareAtPrice }
    userErrors { field message }
  }
}
GQL;
            foreach ($formatted as $item) {
                try {
                    $res = $this->query($shop, $singleQuery, ['input' => $item])['productVariantUpdate'] ?? [];
                    if (!empty($res['userErrors'])) {
                        $this->updateVariantViaRest($shop, $item);
                    }
                } catch (\Throwable $e) {
                    $this->updateVariantViaRest($shop, $item);
                }
            }
        }
    }

    private function updateVariantViaRest(Shop $shop, array $item): void {
        preg_match('/(\d+)$/', $item['id'], $m);
        $variantIdNum = $m[1] ?? '';
        if (!$variantIdNum) return;

        $payload = [
            'variant' => [
                'id' => (int)$variantIdNum,
                'price' => (string)$item['price'],
            ],
        ];
        if (array_key_exists('compareAtPrice', $item)) {
            $payload['variant']['compare_at_price'] = $item['compareAtPrice'] !== null ? (string)$item['compareAtPrice'] : null;
        }

        try {
            $this->restPut($shop, "variants/{$variantIdNum}.json", $payload);
        } catch (\Throwable $e) {
            Log::error("REST fallback variant update failed for variant {$variantIdNum}: " . $e->getMessage());
        }
    }

    public function createAppSubscription(Shop $shop, string $planKey = 'pro', string $returnUrl = ''): array {
        $plan = config("shopify.plans.{$planKey}", config('shopify.plans.pro'));
        $testMode = (bool) config('shopify.billing_test_mode', true);

        $mutation = <<<'GQL'
mutation AppSubscriptionCreate($name: String!, $lineItems: [AppSubscriptionLineItemInput!]!, $returnUrl: URL!, $test: Boolean, $trialDays: Int) {
  appSubscriptionCreate(name: $name, lineItems: $lineItems, returnUrl: $returnUrl, test: $test, trialDays: $trialDays) {
    confirmationUrl
    appSubscription { id status }
    userErrors { field message }
  }
}
GQL;

        $variables = [
            'name' => $plan['name'],
            'returnUrl' => $returnUrl,
            'test' => $testMode,
            'trialDays' => (int) ($plan['trial_days'] ?? 0),
            'lineItems' => [
                [
                    'plan' => [
                        'appRecurringPricingDetails' => [
                            'price' => [
                                'amount' => (float) $plan['price'],
                                'currencyCode' => $plan['currency'] ?? 'USD',
                            ],
                            'interval' => $plan['interval'] ?? 'EVERY_30_DAYS',
                        ],
                    ],
                ],
            ],
        ];

        $res = $this->query($shop, $mutation, $variables)['appSubscriptionCreate'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);
        return $res;
    }

    public function getActiveSubscription(Shop $shop): ?array {
        $query = <<<'GQL'
query AppActiveSubscription {
  appInstallation {
    activeSubscriptions {
      id
      name
      status
      test
      createdAt
      currentPeriodEnd
      lineItems {
        plan {
          pricingDetails {
            ... on AppRecurringPricing {
              price { amount currencyCode }
              interval
            }
          }
        }
      }
    }
  }
}
GQL;
        $data = $this->query($shop, $query)['appInstallation']['activeSubscriptions'] ?? [];
        return $data[0] ?? null;
    }

    public function cancelAppSubscription(Shop $shop, string $subscriptionId): array {
        $mutation = <<<'GQL'
mutation AppSubscriptionCancel($id: ID!) {
  appSubscriptionCancel(id: $id) {
    appSubscription { id status }
    userErrors { field message }
  }
}
GQL;
        $res = $this->query($shop, $mutation, ['id' => $subscriptionId])['appSubscriptionCancel'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);
        return $res;
    }

    public function createBundleProduct(Shop $shop, array $data): array {
        $title = (string) ($data['title'] ?? 'Custom Bundle');
        $descriptionHtml = (string) ($data['description_html'] ?? '');
        $tags = (array) ($data['tags'] ?? ['bundle', 'salessnap-bundle']);
        $status = strtoupper((string) ($data['status'] ?? 'ACTIVE'));
        if (!in_array($status, ['ACTIVE', 'DRAFT', 'ARCHIVED'], true)) {
            $status = 'ACTIVE';
        }
        $price = number_format((float) ($data['price'] ?? 0), 2, '.', '');
        $compareAtPrice = !empty($data['compare_at_price']) ? number_format((float) $data['compare_at_price'], 2, '.', '') : null;
        $sku = !empty($data['sku']) ? (string) $data['sku'] : null;
        $imageUrl = !empty($data['image_url']) ? (string) $data['image_url'] : null;

        $createMutation = <<<'GQL'
mutation CreateBundleProduct($input: ProductInput!) {
  productCreate(input: $input) {
    product {
      id
      title
      handle
      status
      variants(first: 5) {
        nodes {
          id
          price
          compareAtPrice
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}
GQL;

        $input = [
            'title' => $title,
            'descriptionHtml' => $descriptionHtml,
            'tags' => array_values(array_unique(array_filter($tags))),
            'status' => $status,
            'vendor' => 'SaleSnap Bundles',
        ];

        $res = $this->query($shop, $createMutation, ['input' => $input])['productCreate'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);

        $product = $res['product'] ?? null;
        if (!$product || empty($product['id'])) {
            throw new RuntimeException('Failed to retrieve created product from Shopify.');
        }

        $productId = $product['id'];
        $defaultVariant = $product['variants']['nodes'][0] ?? null;

        if ($defaultVariant && !empty($defaultVariant['id'])) {
            $variantInput = [
                'id' => $defaultVariant['id'],
                'price' => $price,
            ];
            if ($compareAtPrice !== null && (float) $compareAtPrice > (float) $price) {
                $variantInput['compareAtPrice'] = $compareAtPrice;
            }
            if ($sku !== null) {
                $variantInput['sku'] = $sku;
            }

            $this->updateVariantPrices($shop, $productId, [$variantInput]);
        }

        if ($imageUrl) {
            try {
                $this->createProductMedia($shop, $productId, $imageUrl, $title);
            } catch (\Throwable $e) {
                Log::warning("Could not attach media image to bundle product {$productId}: " . $e->getMessage());
            }
        }

        preg_match('/(\d+)$/', $productId, $m);
        $idNumber = $m[1] ?? '';
        $shopSlug = explode('.', $shop->shop_domain)[0];

        return [
            'id' => $productId,
            'title' => $title,
            'handle' => $product['handle'] ?? '',
            'status' => $status,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'admin_url' => "https://admin.shopify.com/store/{$shopSlug}/products/{$idNumber}",
        ];
    }

    public function createProductMedia(Shop $shop, string $productId, string $imageUrl, string $alt = ''): void {
        $mutation = <<<'GQL'
mutation ProductCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
  productCreateMedia(productId: $productId, media: $media) {
    media {
      id
      status
    }
    userErrors {
      field
      message
    }
  }
}
GQL;

        $media = [
            [
                'originalSource' => $imageUrl,
                'mediaContentType' => 'IMAGE',
                'alt' => $alt ?: 'Product Image',
            ],
        ];

        $res = $this->query($shop, $mutation, [
            'productId' => $productId,
            'media' => $media,
        ])['productCreateMedia'] ?? [];

        $this->assertUserErrors($res['userErrors'] ?? []);
    }

    public function searchBundleProducts(Shop $shop, string $term = ''): array {
        $query = <<<'GQL'
query BundleProductsSearch($query: String!) {
  products(first: 50, query: $query, sortKey: UPDATED_AT, reverse: true) {
    nodes {
      id
      title
      handle
      status
      tags
      featuredImage { url altText }
      variants(first: 5) {
        nodes { id price compareAtPrice }
      }
    }
  }
}
GQL;

        $q = 'tag:bundle OR tag:salessnap-bundle';
        if ($term !== '') {
            $q = '(' . $q . ') AND title:' . str_replace(['\\', '*'], ['', ''], $term) . '*';
        }

        $nodes = $this->query($shop, $query, ['query' => $q])['products']['nodes'] ?? [];
        return array_map(function ($p) use ($shop) {
            $price = $p['variants']['nodes'][0]['price'] ?? '0.00';
            $compareAtPrice = $p['variants']['nodes'][0]['compareAtPrice'] ?? null;
            preg_match('/(\d+)$/', $p['id'], $m);
            $idNumber = $m[1] ?? '';
            $shopSlug = explode('.', $shop->shop_domain)[0];

            return [
                'id' => $p['id'],
                'title' => $p['title'] ?? '',
                'handle' => $p['handle'] ?? '',
                'status' => $p['status'] ?? 'ACTIVE',
                'tags' => $p['tags'] ?? [],
                'image' => $p['featuredImage']['url'] ?? null,
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'admin_url' => "https://admin.shopify.com/store/{$shopSlug}/products/{$idNumber}",
            ];
        }, $nodes);
    }

    private function assertUserErrors(array $errors): void {
        if ($errors) {
            throw new RuntimeException('Shopify rejected request: ' . mb_substr(json_encode($errors), 0, 1200));
        }
    }

    public function restGet(Shop $shop, string $endpoint, array $query = []): array {
        $this->refreshIfNeeded($shop);
        $url = "https://{$shop->shop_domain}/admin/api/" . config('shopify.api_version') . '/' . ltrim($endpoint, '/');
        $res = Http::withHeaders([
            'X-Shopify-Access-Token' => (string) $shop->access_token,
            'Accept' => 'application/json',
        ])
        ->timeout(30)
        ->retry(2, 300, throw: false)
        ->get($url, $query);

        if (!$res->successful() && in_array($res->status(), [401, 403], true)) {
            $bodyStr = (string)$res->body();
            if (str_contains($bodyStr, 'Non-expiring access tokens') || str_contains($bodyStr, 'offline-access-tokens') || empty($shop->token_expires_at)) {
                if (VerifyShopifySessionToken::migrateOfflineTokenToExpiring($shop)) {
                    $shop->refresh();
                    $res = Http::withHeaders([
                        'X-Shopify-Access-Token' => (string) $shop->access_token,
                        'Accept' => 'application/json',
                    ])->timeout(30)->get($url, $query);
                }
            }
        }

        if (!$res->successful()) {
            throw new RuntimeException("Shopify REST GET {$endpoint} failed (HTTP {$res->status()}): " . mb_substr($res->body(), 0, 500));
        }
        return $res->json() ?? [];
    }

    public function restPost(Shop $shop, string $endpoint, array $data = []): array {
        $this->refreshIfNeeded($shop);
        $url = "https://{$shop->shop_domain}/admin/api/" . config('shopify.api_version') . '/' . ltrim($endpoint, '/');
        $res = Http::withHeaders([
            'X-Shopify-Access-Token' => (string) $shop->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->timeout(35)
        ->retry(2, 300, throw: false)
        ->post($url, $data);

        if (!$res->successful() && in_array($res->status(), [401, 403], true)) {
            $bodyStr = (string)$res->body();
            if (str_contains($bodyStr, 'Non-expiring access tokens') || str_contains($bodyStr, 'offline-access-tokens') || empty($shop->token_expires_at)) {
                if (VerifyShopifySessionToken::migrateOfflineTokenToExpiring($shop)) {
                    $shop->refresh();
                    $res = Http::withHeaders([
                        'X-Shopify-Access-Token' => (string) $shop->access_token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->timeout(35)->post($url, $data);
                }
            }
        }

        if (!$res->successful()) {
            throw new RuntimeException("Shopify REST POST {$endpoint} failed (HTTP {$res->status()}): " . mb_substr($res->body(), 0, 500));
        }
        return $res->json() ?? [];
    }

    public function restPut(Shop $shop, string $endpoint, array $data = []): array {
        $this->refreshIfNeeded($shop);
        $url = "https://{$shop->shop_domain}/admin/api/" . config('shopify.api_version') . '/' . ltrim($endpoint, '/');
        $res = Http::withHeaders([
            'X-Shopify-Access-Token' => (string) $shop->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->timeout(30)
        ->retry(2, 300, throw: false)
        ->put($url, $data);

        if (!$res->successful() && in_array($res->status(), [401, 403], true)) {
            $bodyStr = (string)$res->body();
            if (str_contains($bodyStr, 'Non-expiring access tokens') || str_contains($bodyStr, 'offline-access-tokens') || empty($shop->token_expires_at)) {
                if (VerifyShopifySessionToken::migrateOfflineTokenToExpiring($shop)) {
                    $shop->refresh();
                    $res = Http::withHeaders([
                        'X-Shopify-Access-Token' => (string) $shop->access_token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])->timeout(30)->put($url, $data);
                }
            }
        }

        if (!$res->successful()) {
            throw new RuntimeException("Shopify REST PUT {$endpoint} failed (HTTP {$res->status()}): " . mb_substr($res->body(), 0, 500));
        }
        return $res->json() ?? [];
    }

    public function getThemes(Shop $shop): array {
        $data = $this->restGet($shop, 'themes.json');
        $themes = $data['themes'] ?? [];
        $slug = explode('.', $shop->shop_domain)[0];

        return array_map(function ($t) use ($shop, $slug) {
            $isMain = ($t['role'] ?? '') === 'main';
            $isSalessnap = str_contains(strtolower($t['name'] ?? ''), 'salessnap') || str_contains(strtolower($t['name'] ?? ''), 'promo') || str_contains(strtolower($t['name'] ?? ''), 'countdown');
            return [
                'id' => (string)$t['id'],
                'name' => $t['name'] ?? 'Theme',
                'role' => $t['role'] ?? 'unpublished',
                'is_main' => $isMain,
                'is_salessnap_copy' => $isSalessnap,
                'processing' => (bool)($t['processing'] ?? false),
                'updated_at' => $t['updated_at'] ?? null,
                'preview_url' => "https://{$shop->shop_domain}?preview_theme_id={$t['id']}",
                'admin_url' => "https://admin.shopify.com/store/{$slug}/themes/{$t['id']}/editor",
            ];
        }, $themes);
    }

    public function duplicateTheme(Shop $shop, string|int $sourceThemeId, string $newName, ?array $countdownConfig = null): array {
        $source = (string)$sourceThemeId;
        $themes = $this->getThemes($shop);
        $srcTheme = collect($themes)->firstWhere('id', $source);
        $srcName = $srcTheme['name'] ?? 'Theme';

        if (empty($newName)) {
            $newName = "[SaleSnap Promo] {$srcName} with Countdown";
        }

        // Create new theme copy in Shopify
        $res = $this->restPost($shop, 'themes.json', [
            'theme' => [
                'name' => $newName,
                'role' => 'unpublished',
            ],
        ]);

        $created = $res['theme'] ?? null;
        if (!$created || empty($created['id'])) {
            throw new RuntimeException('Shopify could not create promo theme copy.');
        }

        $createdId = $created['id'];

        // Copy source theme's layout/theme.liquid and inject countdown banner
        try {
            $sourceLayout = $this->restGet($shop, "themes/{$source}/assets.json", ['asset[key]' => 'layout/theme.liquid']);
            $sourceContent = $sourceLayout['asset']['value'] ?? null;

            $snippet = $this->generateCountdownLiquid($countdownConfig ?? [], $shop);
            $this->restPut($shop, "themes/{$createdId}/assets.json", [
                'asset' => [
                    'key' => 'snippets/salessnap-countdown.liquid',
                    'value' => $snippet,
                ],
            ]);

            if ($sourceContent) {
                if (!str_contains($sourceContent, 'salessnap-countdown')) {
                    $modified = str_ireplace('<body', "<body\n{% render 'salessnap-countdown' %}", $sourceContent);
                    if ($modified === $sourceContent) {
                        $modified = "{% render 'salessnap-countdown' %}\n" . $sourceContent;
                    }
                    $this->restPut($shop, "themes/{$createdId}/assets.json", [
                        'asset' => [
                            'key' => 'layout/theme.liquid',
                            'value' => $modified,
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Could not copy theme layout assets for new theme {$createdId}: " . $e->getMessage());
        }

        return [
            'id' => (string)($created['id'] ?? ''),
            'name' => $created['name'] ?? $newName,
            'role' => $created['role'] ?? 'unpublished',
            'preview_url' => "https://{$shop->shop_domain}?preview_theme_id=" . ($created['id'] ?? ''),
        ];
    }

    public function publishTheme(Shop $shop, string|int $themeId): array {
        $currentThemes = $this->getThemes($shop);
        $currentMain = collect($currentThemes)->firstWhere('is_main', true);
        if ($currentMain && $currentMain['id'] !== (string)$themeId) {
            $shop->update(['published_theme_id' => $currentMain['id']]);
        }

        $res = $this->restPut($shop, "themes/{$themeId}.json", [
            'theme' => [
                'id' => (int)$themeId,
                'role' => 'main',
            ],
        ]);

        $shop->update(['active_promo_theme_id' => (string)$themeId]);

        return [
            'success' => true,
            'published_theme_id' => (string)$themeId,
            'previous_theme_id' => $shop->published_theme_id,
            'theme' => $res['theme'] ?? [],
        ];
    }

    public function revertTheme(Shop $shop): array {
        $targetId = $shop->published_theme_id;
        if (!$targetId) {
            throw new RuntimeException('No previous original theme was recorded to restore.');
        }

        $res = $this->restPut($shop, "themes/{$targetId}.json", [
            'theme' => [
                'id' => (int)$targetId,
                'role' => 'main',
            ],
        ]);

        $shop->update([
            'published_theme_id' => null,
            'active_promo_theme_id' => null,
        ]);

        return [
            'success' => true,
            'restored_theme_id' => (string)$targetId,
        ];
    }

    public function injectCountdown(Shop $shop, string|int $themeId, array $config): bool {
        $snippetContent = $this->generateCountdownLiquid($config, $shop);
        $this->restPut($shop, "themes/{$themeId}/assets.json", [
            'asset' => [
                'key' => 'snippets/salessnap-countdown.liquid',
                'value' => $snippetContent,
            ],
        ]);

        try {
            $themeLiquid = $this->restGet($shop, "themes/{$themeId}/assets.json", [
                'asset[key]' => 'layout/theme.liquid',
            ]);
            $content = $themeLiquid['asset']['value'] ?? '';
            $renderTag = "{% render 'salessnap-countdown' %}";

            if ($content !== '' && !str_contains($content, 'salessnap-countdown')) {
                if (str_contains($content, '<body')) {
                    $content = preg_replace('/(<body[^>]*>)/i', "$1\n  " . $renderTag, $content, 1);
                } elseif (str_contains($content, '</head>')) {
                    $content = str_replace('</head>', "  " . $renderTag . "\n</head>", $content);
                }

                $this->restPut($shop, "themes/{$themeId}/assets.json", [
                    'asset' => [
                        'key' => 'layout/theme.liquid',
                        'value' => $content,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Could not automatically update layout/theme.liquid for theme {$themeId}: " . $e->getMessage());
        }

        return true;
    }

    public function generateCountdownLiquid(array $config, Shop $shop): string {
        $headline = htmlspecialchars($config['headline'] ?? 'FLASH SALE IS LIVE! Extra Discount Auto-Applied', ENT_QUOTES, 'UTF-8');
        $subtext = htmlspecialchars($config['subtext'] ?? 'Special promotional deals ending soon. Shop now while supplies last!', ENT_QUOTES, 'UTF-8');
        $endsAt = htmlspecialchars($config['ends_at'] ?? now()->addDays(2)->toIso8601String(), ENT_QUOTES, 'UTF-8');
        $bgColor = htmlspecialchars($config['bg_color'] ?? '#111827', ENT_QUOTES, 'UTF-8');
        $textColor = htmlspecialchars($config['text_color'] ?? '#ffffff', ENT_QUOTES, 'UTF-8');
        $accentColor = htmlspecialchars($config['accent_color'] ?? '#f59e0b', ENT_QUOTES, 'UTF-8');
        $btnText = htmlspecialchars($config['btn_text'] ?? 'Shop Deals Now', ENT_QUOTES, 'UTF-8');
        $btnUrl = htmlspecialchars($config['btn_url'] ?? '/collections/all', ENT_QUOTES, 'UTF-8');
        $position = ($config['position'] ?? 'top_sticky') === 'bottom_sticky' ? 'bottom: 0;' : 'top: 0;';

        return <<<LIQUID
{% comment %}
  SaleSnap Flash Sale Countdown Announcement Bar
  Automatically generated & synchronized with active promotions.
{% endcomment %}
<div id="salessnap-countdown-banner" style="position: sticky; {$position} z-index: 2147483640; width: 100%; background: {$bgColor}; color: {$textColor}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-bottom: 2px solid {$accentColor}; line-height: 1.4;">
  <div style="max-width: 1200px; margin: 0 auto; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div style="display: flex; align-items: center; gap: 12px; min-width: 240px;">
      <svg style="width:20px;height:20px;color:{$accentColor};flex-shrink:0;" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm1-12a1 1 0 1 0-2 0v4.4a1 1 0 0 0 .3.7l2.8 2.8a1 1 0 0 0 1.4-1.4L11 9.9V6z" clip-rule="evenodd"/></svg>
      <div>
        <div style="font-weight: 700; font-size: 14px; letter-spacing: -0.01em;">{$headline}</div>
        <div style="font-size: 12px; opacity: 0.85; margin-top: 2px;">{$subtext}</div>
      </div>
    </div>

    <!-- Countdown Timer Units -->
    <div style="display: flex; align-items: center; gap: 8px;">
      <div style="text-align: center; background: rgba(255,255,255,0.12); border-radius: 6px; padding: 4px 8px; min-width: 44px;">
        <span id="ss-days" style="font-size: 16px; font-weight: 800; color: {$accentColor}; display: block; line-height: 1.1;">00</span>
        <span style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Days</span>
      </div>
      <span style="font-weight: 700; color: {$accentColor};">:</span>
      <div style="text-align: center; background: rgba(255,255,255,0.12); border-radius: 6px; padding: 4px 8px; min-width: 44px;">
        <span id="ss-hours" style="font-size: 16px; font-weight: 800; color: {$accentColor}; display: block; line-height: 1.1;">00</span>
        <span style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Hours</span>
      </div>
      <span style="font-weight: 700; color: {$accentColor};">:</span>
      <div style="text-align: center; background: rgba(255,255,255,0.12); border-radius: 6px; padding: 4px 8px; min-width: 44px;">
        <span id="ss-mins" style="font-size: 16px; font-weight: 800; color: {$accentColor}; display: block; line-height: 1.1;">00</span>
        <span style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Mins</span>
      </div>
      <span style="font-weight: 700; color: {$accentColor};">:</span>
      <div style="text-align: center; background: rgba(255,255,255,0.12); border-radius: 6px; padding: 4px 8px; min-width: 44px;">
        <span id="ss-secs" style="font-size: 16px; font-weight: 800; color: {$accentColor}; display: block; line-height: 1.1;">00</span>
        <span style="font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Secs</span>
      </div>
    </div>

    <!-- CTA Button & Close -->
    <div style="display: flex; align-items: center; gap: 8px;">
      <a href="{$btnUrl}" style="display: inline-block; background: {$accentColor}; color: #111827; font-weight: 700; font-size: 12px; padding: 7px 16px; border-radius: 20px; text-decoration: none; transition: transform 0.1s ease, filter 0.1s ease;">{$btnText} →</a>
      <button type="button" onclick="document.getElementById('salessnap-countdown-banner').style.display='none'" style="background: none; border: none; color: {$textColor}; opacity: 0.6; cursor: pointer; font-size: 18px; line-height: 1; padding: 4px;">×</button>
    </div>
  </div>
</div>

<script>
(function() {
  const targetDate = new Date("{$endsAt}").getTime();
  function updateTimer() {
    const now = new Date().getTime();
    const diff = Math.max(0, targetDate - now);

    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const s = Math.floor((diff % (1000 * 60)) / 1000);

    const elD = document.getElementById('ss-days');
    const elH = document.getElementById('ss-hours');
    const elM = document.getElementById('ss-mins');
    const elS = document.getElementById('ss-secs');

    if (elD) elD.textContent = String(d).padStart(2, '0');
    if (elH) elH.textContent = String(h).padStart(2, '0');
    if (elM) elM.textContent = String(m).padStart(2, '0');
    if (elS) elS.textContent = String(s).padStart(2, '0');

    if (diff <= 0) {
      const banner = document.getElementById('salessnap-countdown-banner');
      if (banner) banner.style.display = 'none';
    }
  }
  updateTimer();
  setInterval(updateTimer, 1000);
})();
</script>
LIQUID;
    }

    private function refreshIfNeeded(Shop $shop): void {
        if (!$shop->token_expires_at || $shop->token_expires_at->gt(now()->addMinutes(2))) return;
        DB::transaction(function () use ($shop) {
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            if (!$locked->token_expires_at || $locked->token_expires_at->gt(now()->addMinutes(2))) {
                $shop->refresh();
                return;
            }
            if (!$locked->refresh_token || ($locked->refresh_token_expires_at && $locked->refresh_token_expires_at->isPast())) {
                throw new RuntimeException('Shopify token expired. Re-open the app in Shopify Admin to refresh authorization.');
            }
            $r = Http::asForm()->acceptJson()->timeout(15)->post("https://{$locked->shop_domain}/admin/oauth/access_token", [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $locked->refresh_token,
                'expiring' => 1,
            ]);
            if (!$r->successful() || !$r->json('access_token')) {
                throw new RuntimeException('Could not refresh the Shopify access token. Re-authorize the app.');
            }
            $data = $r->json();
            $locked->forceFill([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $locked->refresh_token,
                'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int)$data['expires_in']) : null,
                'refresh_token_expires_at' => isset($data['refresh_token_expires_in']) ? now()->addSeconds((int)$data['refresh_token_expires_in']) : $locked->refresh_token_expires_at,
            ])->save();
            $shop->refresh();
        });
    }
}
