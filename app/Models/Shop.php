<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model {
    protected $table = 'shops';

    protected $fillable = [
        'shop_domain',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_token_expires_at',
        'granted_scopes',
        'plan',
        'charge_id',
        'subscription_status',
        'trial_ends_at',
        'settings',
        'published_theme_id',
        'active_promo_theme_id',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public static function defaultSettings(): array {
        return [
            // Discount Safeguards
            'max_discount_cap' => 80,
            'price_rounding' => 'none', // 'none', '99', '95', 'round_dollar'
            'compare_at_mode' => 'set_original', // 'set_original', 'leave_unchanged'

            // Campaign Defaults
            'default_tag' => 'salessnap-sale',
            'default_desc_prefix' => 'Flash Sale Exclusive Deal: ',
            'auto_restore_on_end' => true,
            'snapshot_retention_days' => 90,

            // Countdown Banner Widget Settings
            'countdown_enabled' => true,
            'countdown_position' => 'top_sticky', // 'top_sticky', 'bottom_sticky'
            'countdown_headline' => 'FLASH SALE IS LIVE! Extra %discount%% Off Selected Items',
            'countdown_subtext' => 'Limited time store promotion. Discounts auto-applied in cart.',
            'countdown_bg_color' => '#111827',
            'countdown_text_color' => '#ffffff',
            'countdown_accent_color' => '#f59e0b',
            'countdown_btn_text' => 'Shop Deals Now',
            'countdown_btn_url' => '/collections/all',

            // Notifications
            'notify_on_conflict' => true,
            'webhook_url' => '',
        ];
    }

    public function getSetting(string $key, $default = null) {
        $merged = array_merge(self::defaultSettings(), $this->settings ?? []);
        return $merged[$key] ?? $default;
    }

    public function getMergedSettings(): array {
        return array_merge(self::defaultSettings(), $this->settings ?? []);
    }

    public function campaigns(): HasMany {
        return $this->hasMany(Campaign::class);
    }

    public function logs(): HasMany {
        return $this->hasMany(CampaignLog::class);
    }
}
