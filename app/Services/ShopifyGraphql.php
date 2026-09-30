<?php
namespace App\Services;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShopifyGraphql {
    public function query(Shop $shop, string $query, array $variables=[]): array {
        $this->refreshIfNeeded($shop);
        $response=Http::withHeaders(['X-Shopify-Access-Token'=>$shop->access_token,'Content-Type'=>'application/json','Accept'=>'application/json'])
            ->timeout(30)->retry(2,300,throw:false)->post("https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/graphql.json',['query'=>$query,'variables'=>$variables]);
        if(!$response->successful()) throw new RuntimeException('Shopify API request failed (HTTP '.$response->status().').');
        $body=$response->json();
        if(!empty($body['errors'])) throw new RuntimeException('Shopify GraphQL error: '.mb_substr(json_encode($body['errors']),0,1200));
        return $body['data'] ?? [];
    }
    public function productsByIds(Shop $shop, array $ids): array {
        if(count($ids)>250) throw new RuntimeException('A campaign can include at most 250 products in this release.');
        // Keep query complexity bounded: deeply nested variants for 250 products can exceed Shopify's cost limit.
        $query=<<<'GQL'
query CampaignProduct($id: ID!) {
  node(id: $id) {
    ... on Product {
      id title descriptionHtml tags status
      variants(first: 250) { nodes { id price } pageInfo { hasNextPage } }
    }
  }
}
GQL;
        $items=[];
        foreach($ids as $id){
            $node=$this->query($shop,$query,['id'=>$id])['node'] ?? null;
            if(!$node || empty($node['id'])) continue;
            if($node['variants']['pageInfo']['hasNextPage'] ?? false) throw new RuntimeException('A selected product has more than 250 variants. It was not changed so its snapshot stays complete.');
            $items[$node['id']]=['id'=>$node['id'],'title'=>$node['title'] ?? '','descriptionHtml'=>$node['descriptionHtml'] ?? '','tags'=>$node['tags'] ?? [],'status'=>$node['status'] ?? 'ACTIVE','variants'=>$node['variants']['nodes'] ?? []];
        }
        return $items;
    }
    public function searchProducts(Shop $shop, string $term): array {
        $query=<<<'GQL'
query ProductSearch($query: String!) { products(first: 40, query: $query, sortKey: TITLE) { nodes { id title handle status featuredImage { url altText } variants(first: 1) { nodes { price } } } } }
GQL;
        return $this->query($shop,$query,['query'=>$term !== '' ? 'title:'.str_replace(['\\','*'],['',''],$term).'*' : ''])['products']['nodes'] ?? [];
    }
    public function updateProduct(Shop $shop, string $id, array $input): void {
        $query=<<<'GQL'
mutation ProductUpdate($product: ProductUpdateInput!) { productUpdate(product: $product) { product { id } userErrors { field message } } }
GQL;
        $res=$this->query($shop,$query,['product'=>array_merge(['id'=>$id],$input)])['productUpdate'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);
    }
    public function updateVariantPrices(Shop $shop, string $productId, array $variants): void {
        if(!$variants) return;
        $query=<<<'GQL'
mutation VariantPrices($productId: ID!, $variants: [ProductVariantsBulkInput!]!) { productVariantsBulkUpdate(productId: $productId, variants: $variants) { product { id } userErrors { field message } } }
GQL;
        $res=$this->query($shop,$query,['productId'=>$productId,'variants'=>$variants])['productVariantsBulkUpdate'] ?? [];
        $this->assertUserErrors($res['userErrors'] ?? []);
    }
    private function assertUserErrors(array $errors): void { if($errors) throw new RuntimeException('Shopify rejected a product change: '.mb_substr(json_encode($errors),0,1200)); }
    private function refreshIfNeeded(Shop $shop): void {
        if(!$shop->token_expires_at || $shop->token_expires_at->gt(now()->addMinutes(2))) return;
        // Refresh-token rotation is one-time; serialize by shop so workers cannot race the old refresh token.
        DB::transaction(function() use($shop){
            $locked=Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            if(!$locked->token_expires_at || $locked->token_expires_at->gt(now()->addMinutes(2))){$shop->refresh();return;}
            if(!$locked->refresh_token || ($locked->refresh_token_expires_at && $locked->refresh_token_expires_at->isPast())) throw new RuntimeException('Shopify refresh token expired. Re-open the app from Shopify Admin to re-authorize.');
            $r=Http::asJson()->acceptJson()->timeout(15)->post("https://{$locked->shop_domain}/admin/oauth/access_token",['client_id'=>config('shopify.api_key'),'client_secret'=>config('shopify.api_secret'),'grant_type'=>'refresh_token','refresh_token'=>$locked->refresh_token]);
            if(!$r->successful() || !$r->json('access_token') || !$r->json('expires_in') || !$r->json('refresh_token') || !$r->json('refresh_token_expires_in')) throw new RuntimeException('Could not refresh the Shopify access token. Re-authorize the app.');
            $data=$r->json();$locked->forceFill(['access_token'=>$data['access_token'],'refresh_token'=>$data['refresh_token'],'token_expires_at'=>isset($data['expires_in'])?now()->addSeconds((int)$data['expires_in']):null,'refresh_token_expires_at'=>isset($data['refresh_token_expires_in'])?now()->addSeconds((int)$data['refresh_token_expires_in']):$locked->refresh_token_expires_at])->save();
            $shop->refresh();
        });
    }
}
