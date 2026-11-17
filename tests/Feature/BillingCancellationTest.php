<?php
namespace Tests\Feature;

use App\Models\Shop;
use App\Services\ShopifyGraphql;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingCancellationTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        config(['shopify.api_key' => 'test-client-id', 'shopify.api_secret' => 'test-secret']);
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_cancellation_uses_the_active_shopify_subscription_and_confirms_its_status(): void {
        $shop = $this->shop();
        $activeSubscriptionId = 'gid://shopify/AppSubscription/active-subscription';
        $graphql = $this->createMock(ShopifyGraphql::class);
        $graphql->expects($this->once())
            ->method('getActiveSubscription')
            ->with($this->callback(fn (Shop $requestedShop) => $requestedShop->is($shop)))
            ->willReturn(['id' => $activeSubscriptionId, 'status' => 'ACTIVE']);
        $graphql->expects($this->once())
            ->method('cancelAppSubscription')
            ->with($this->anything(), $activeSubscriptionId)
            ->willReturn(['appSubscription' => ['id' => $activeSubscriptionId, 'status' => 'CANCELLED']]);
        $this->app->instance(ShopifyGraphql::class, $graphql);

        $this->withHeader('Authorization', 'Bearer ' . $this->jwt())
            ->postJson('/api/billing/cancel')
            ->assertOk()
            ->assertJsonPath('plan', 'free');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'plan' => 'free',
            'charge_id' => null,
            'subscription_status' => 'CANCELLED',
        ]);
    }

    public function test_cancellation_does_not_change_the_plan_without_shopify_confirmation(): void {
        $shop = $this->shop();
        $activeSubscriptionId = 'gid://shopify/AppSubscription/active-subscription';
        $graphql = $this->createMock(ShopifyGraphql::class);
        $graphql->method('getActiveSubscription')
            ->willReturn(['id' => $activeSubscriptionId, 'status' => 'ACTIVE']);
        $graphql->method('cancelAppSubscription')
            ->willReturn(['appSubscription' => ['id' => $activeSubscriptionId, 'status' => 'ACTIVE']]);
        $this->app->instance(ShopifyGraphql::class, $graphql);

        $this->withHeader('Authorization', 'Bearer ' . $this->jwt())
            ->postJson('/api/billing/cancel')
            ->assertStatus(500);

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'plan' => 'pro',
            'charge_id' => 'gid://shopify/AppSubscription/stale-subscription',
            'subscription_status' => 'ACTIVE',
        ]);
    }

    public function test_cancellation_clears_stale_local_billing_state_when_shopify_has_no_active_subscription(): void {
        $shop = $this->shop();
        $graphql = $this->createMock(ShopifyGraphql::class);
        $graphql->expects($this->once())->method('getActiveSubscription')->willReturn(null);
        $graphql->expects($this->never())->method('cancelAppSubscription');
        $this->app->instance(ShopifyGraphql::class, $graphql);

        $this->withHeader('Authorization', 'Bearer ' . $this->jwt())
            ->postJson('/api/billing/cancel')
            ->assertOk()
            ->assertJsonPath('plan', 'free');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'plan' => 'free',
            'charge_id' => null,
            'subscription_status' => null,
        ]);
    }

    private function shop(): Shop {
        return Shop::create([
            'shop_domain' => 'demo.myshopify.com',
            'access_token' => 'shpat_test',
            'granted_scopes' => 'read_products,write_products',
            'plan' => 'pro',
            'charge_id' => 'gid://shopify/AppSubscription/stale-subscription',
            'subscription_status' => 'ACTIVE',
            'installed_at' => now(),
        ]);
    }

    private function jwt(): string {
        $now = time();
        $head = $this->b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->b64(json_encode([
            'iss' => 'https://demo.myshopify.com/admin',
            'dest' => 'https://demo.myshopify.com',
            'aud' => 'test-client-id',
            'sub' => '123',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 60,
            'jti' => 'test-jti',
        ]));
        $signature = $this->b64(hash_hmac('sha256', $head . '.' . $body, 'test-secret', true));

        return $head . '.' . $body . '.' . $signature;
    }

    private function b64(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
