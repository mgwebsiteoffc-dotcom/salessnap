<?php
namespace App\Http\Controllers;

use App\Models\CampaignLog;
use App\Services\ShopifyGraphql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ThemeController {
    public function index(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        try {
            $themes = $graphql->getThemes($shop);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isScopeError = str_contains($msg, 'read_themes') || str_contains($msg, '403') || str_contains($msg, '401') || str_contains($msg, 'Invalid API key or access token') || str_contains($msg, 're-authorize');
            if ($isScopeError) {
                Log::info("Store {$shop->shop_domain} requires theme permissions re-authorization: {$msg}");
            } else {
                Log::warning("Could not fetch themes for {$shop->shop_domain}: " . $msg);
            }

            return response()->json([
                'themes' => [],
                'published_theme_id' => $shop->published_theme_id,
                'active_promo_theme_id' => $shop->active_promo_theme_id,
                'scope_required' => $isScopeError,
                'auth_required' => $isScopeError,
                'reauth_url' => '/auth?shop=' . urlencode($shop->shop_domain),
                'error' => $isScopeError
                    ? 'Theme management requires read_themes and write_themes permissions approved in Shopify. Please click "Grant Theme Permissions" below to enable theme features.'
                    : 'Unable to read themes: ' . $msg,
            ]);
        }

        $mainTheme = collect($themes)->firstWhere('is_main', true);
        $promoThemes = collect($themes)->where('is_salessnap_copy', true)->values();

        return response()->json([
            'themes' => $themes,
            'main_theme' => $mainTheme,
            'promo_themes' => $promoThemes,
            'published_theme_id' => $shop->published_theme_id,
            'active_promo_theme_id' => $shop->active_promo_theme_id,
            'can_revert' => !empty($shop->published_theme_id),
        ]);
    }

    public function duplicate(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'source_theme_id' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:120'],
            'with_countdown' => ['nullable', 'boolean'],
            'campaign_id' => ['nullable', 'integer'],
        ]);

        $countdownConfig = null;
        if ($data['with_countdown'] ?? true) {
            $settings = $shop->getMergedSettings();
            $campaign = null;
            if (!empty($data['campaign_id'])) {
                $campaign = $shop->campaigns()->find($data['campaign_id']);
            }
            if (!$campaign) {
                $campaign = $shop->campaigns()->whereIn('status', ['running', 'scheduled'])->latest()->first();
            }

            $endsAt = $campaign ? $campaign->ends_at->toIso8601String() : now()->addDays(3)->toIso8601String();
            $discountPct = (int)($campaign?->actions['price_percent'] ?? 20);

            $headline = str_replace('%discount%', (string)$discountPct, $settings['countdown_headline']);

            $countdownConfig = [
                'headline' => $headline,
                'subtext' => $settings['countdown_subtext'],
                'ends_at' => $endsAt,
                'position' => $settings['countdown_position'],
                'bg_color' => $settings['countdown_bg_color'],
                'text_color' => $settings['countdown_text_color'],
                'accent_color' => $settings['countdown_accent_color'],
                'btn_text' => $settings['countdown_btn_text'],
                'btn_url' => $settings['countdown_btn_url'],
            ];
        }

        $theme = $graphql->duplicateTheme($shop, $data['source_theme_id'], $data['name'] ?? '', $countdownConfig);

        CampaignLog::create([
            'shop_id' => $shop->id,
            'campaign_id' => $data['campaign_id'] ?? null,
            'event' => 'theme_copy_created',
            'severity' => 'info',
            'details' => ['theme_id' => $theme['id'], 'theme_name' => $theme['name']],
        ]);

        return response()->json([
            'message' => 'Promo theme copy created successfully with countdown timer!',
            'theme' => $theme,
        ]);
    }

    public function publish(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'theme_id' => ['required', 'string'],
            'campaign_id' => ['nullable', 'integer'],
        ]);

        $result = $graphql->publishTheme($shop, $data['theme_id']);

        CampaignLog::create([
            'shop_id' => $shop->id,
            'campaign_id' => $data['campaign_id'] ?? null,
            'event' => 'theme_published',
            'severity' => 'info',
            'details' => ['theme_id' => $data['theme_id']],
        ]);

        return response()->json([
            'message' => 'Theme published live to storefront!',
            'result' => $result,
        ]);
    }

    public function revert(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $result = $graphql->revertTheme($shop);

        CampaignLog::create([
            'shop_id' => $shop->id,
            'event' => 'theme_reverted_to_original',
            'severity' => 'info',
            'details' => ['restored_theme_id' => $result['restored_theme_id']],
        ]);

        return response()->json([
            'message' => 'Original theme restored as live active storefront theme.',
            'result' => $result,
        ]);
    }

    public function inject(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'theme_id' => ['required', 'string'],
            'campaign_id' => ['nullable', 'integer'],
        ]);

        $settings = $shop->getMergedSettings();
        $campaign = null;
        if (!empty($data['campaign_id'])) {
            $campaign = $shop->campaigns()->find($data['campaign_id']);
        }
        if (!$campaign) {
            $campaign = $shop->campaigns()->whereIn('status', ['running', 'scheduled'])->latest()->first();
        }

        $endsAt = $campaign ? $campaign->ends_at->toIso8601String() : now()->addDays(3)->toIso8601String();
        $discountPct = (int)($campaign?->actions['price_percent'] ?? 20);
        $headline = str_replace('%discount%', (string)$discountPct, $settings['countdown_headline']);

        $countdownConfig = [
            'headline' => $headline,
            'subtext' => $settings['countdown_subtext'],
            'ends_at' => $endsAt,
            'position' => $settings['countdown_position'],
            'bg_color' => $settings['countdown_bg_color'],
            'text_color' => $settings['countdown_text_color'],
            'accent_color' => $settings['countdown_accent_color'],
            'btn_text' => $settings['countdown_btn_text'],
            'btn_url' => $settings['countdown_btn_url'],
        ];

        $graphql->injectCountdown($shop, $data['theme_id'], $countdownConfig);

        return response()->json([
            'message' => 'Countdown timer widget injected into theme successfully!',
        ]);
    }
}
