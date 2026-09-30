<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class SettingController {
    public function index(Request $request) {
        $shop = $request->attributes->get('shop');
        return response()->json([
            'settings' => $shop->getMergedSettings(),
            'published_theme_id' => $shop->published_theme_id,
            'active_promo_theme_id' => $shop->active_promo_theme_id,
        ]);
    }

    public function update(Request $request) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            // Pricing Guardrails
            'max_discount_cap' => ['required', 'integer', 'min:5', 'max:95'],
            'price_rounding' => ['required', 'in:none,99,95,round_dollar'],
            'compare_at_mode' => ['required', 'in:set_original,leave_unchanged'],

            // Campaign Defaults
            'default_tag' => ['nullable', 'string', 'max:80'],
            'default_desc_prefix' => ['nullable', 'string', 'max:240'],
            'auto_restore_on_end' => ['required', 'boolean'],
            'snapshot_retention_days' => ['required', 'integer', 'min:7', 'max:365'],

            // Countdown Banner Widget
            'countdown_enabled' => ['required', 'boolean'],
            'countdown_position' => ['required', 'in:top_sticky,bottom_sticky'],
            'countdown_headline' => ['required', 'string', 'max:255'],
            'countdown_subtext' => ['nullable', 'string', 'max:255'],
            'countdown_bg_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'countdown_text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'countdown_accent_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'countdown_btn_text' => ['required', 'string', 'max:60'],
            'countdown_btn_url' => ['required', 'string', 'max:255'],

            // Notifications & Integrations
            'notify_on_conflict' => ['required', 'boolean'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
        ]);

        $shop->settings = array_merge($shop->getMergedSettings(), $data);
        $shop->save();

        return response()->json([
            'message' => 'Settings saved successfully!',
            'settings' => $shop->getMergedSettings(),
        ]);
    }
}
