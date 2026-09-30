<?php
namespace Tests\Feature;
use Tests\TestCase;
class MarketingWebsiteTest extends TestCase {
    public function test_root_serves_public_marketing_site_and_install_cta(): void {
        $this->get('/')->assertOk()->assertSee('Run your sale.')->assertSee('/install');
    }
    public function test_install_route_shows_pending_page_until_listing_is_configured(): void {
        config(['shopify.store_listing_url'=>null]);
        $this->get('/install')->assertStatus(503)->assertSee('listing hasn’t been configured');
    }
    public function test_install_route_redirects_only_to_shopify_app_store(): void {
        config(['shopify.store_listing_url'=>'https://apps.shopify.com/promotion-manager']);
        $this->get('/install')->assertRedirect('https://apps.shopify.com/promotion-manager');
        config(['shopify.store_listing_url'=>'https://attacker.example/redirect']);
        $this->get('/install')->assertStatus(503);
    }
}
