<?php
namespace App\Console\Commands;

use App\Http\Middleware\VerifyShopifySessionToken;
use App\Models\Shop;
use Illuminate\Console\Command;

class MigrateShopifyTokens extends Command {
    protected $signature = 'shopify:migrate-tokens {--shop= : Specific myshopify domain to migrate}';
    protected $description = 'Migrate deprecated non-expiring offline tokens to compliant expiring offline tokens (expiring=1)';

    public function handle(): int {
        $shopDomain = $this->option('shop');
        $query = Shop::whereNotNull('access_token');

        if ($shopDomain) {
            $query->where('shop_domain', $shopDomain);
        } else {
            $query->whereNull('token_expires_at');
        }

        $shops = $query->get();
        $this->info("Found {$shops->count()} store(s) to migrate to expiring offline tokens.");

        $success = 0;
        $failed = 0;

        foreach ($shops as $shop) {
            $this->line("Migrating {$shop->shop_domain}...");
            $migrated = VerifyShopifySessionToken::migrateOfflineTokenToExpiring($shop);
            if ($migrated) {
                $this->info("✓ Successfully migrated {$shop->shop_domain} to expiring offline token.");
                $success++;
            } else {
                $this->warn("✗ Migration direct exchange skipped or rejected for {$shop->shop_domain}. Merchant can re-open app to auto-exchange.");
                $failed++;
            }
        }

        $this->info("Migration completed. Success: {$success}, Pending/Failed: {$failed}");
        return 0;
    }
}
