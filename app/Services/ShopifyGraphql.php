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

        if (!$response->successful() && $response->status() === 401) {
            Log::warning("Shopify GraphQL 401 Unauthorized for {$shop->shop_domain}. Attempting automatic token renewal via session token exchange...");
            
            $sessionToken = request()->attributes->get('shopify_session_token') ?: request()->bearerToken();
            if ($sessionToken && VerifyShopifySessionToken::exchangeSessionToken($shop, $sessionToken)) {
                $shop->refresh();
                Log::info("Retrying Shopify GraphQL query after successful token exchange for {$shop->shop_domain}");
                $response = $this->sendQuery($shop, $query, $variables);
            }
        }

        if (!$response->successful()) {
            if ($response->status() === 401) {
                Log::warning("Shopify GraphQL 401 Unauthorized for {$shop->shop_domain}. Stored access token may be revoked.");
                throw new RuntimeException('Shopify API session expired (HTTP 401). Please re-open or re-authorize the app in Shopify Admin.');
            }
            throw new RuntimeException('Shopify API request failed (HTTP ' . $response->status() . ').');
        }

        $body = $response->json();
        if (!empty($body['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: ' . mb_substr(json_encode($body['errors']), 0, 1200));
        }

        return $body['data'] ?? [];
    }

    private function sendQuery(Shop $shop, string $query, array $variables = []) {
        return Http::withHeaders([
            'X-Shopify-Access-Token' => (string) $shop->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->timeout(30)
        ->retry(2, 300, throw: false)
        ->post("https://{$shop->shop_domain}/admin/api/" . config('shopify.api_version') . '/graphql.json', [
            'query' => $query,
            'variables' => $variables,
        ]);
    }

    public function productsByIds(Shop $shop, array $ids): array {
        if (count($ids) > 250) {
            throw new RuntimeException('A campaign can include at most 250 products in this release.');
        }

        $query = <<<'GQL'
query CampaignProduct($id: ID!) {
  node(id: $id) {
    ... on Product {
      id title descriptionHtml tags status
      variants(first: 250) { nodes { id price } pageInfo { hasNextPage } }
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
        $query = <<<'GQL'
mutation VariantPrices($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
  productVariantsBulkUpdate(productId: $productId, variants: $variants) {
    product { id }
    userErrors { field message }
  }
}
GQL;
        $res = $this->query($shop, $query, ['productId' => $productId, 'variants' => $variants])['productVariantsBulkUpdate'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);
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

    private function assertUserErrors(array $errors): void {
        if ($errors) {
            throw new RuntimeException('Shopify rejected request: ' . mb_substr(json_encode($errors), 0, 1200));
        }
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
            $r = Http::asJson()->acceptJson()->timeout(15)->post("https://{$locked->shop_domain}/admin/oauth/access_token", [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $locked->refresh_token,
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
