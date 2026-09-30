<?php
namespace App\Http\Controllers;

use App\Models\CampaignLog;
use App\Services\ShopifyGraphql;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BundleController {
    public function index(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');
        $term = mb_substr(trim((string)$request->query('q', '')), 0, 80);
        return response()->json([
            'bundles' => $graphql->searchBundleProducts($shop, $term),
        ]);
    }

    public function store(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['required', 'string', 'regex:/^gid:\/\/shopify\/Product\/\d+$/'],
            'pricing_type' => ['required', 'in:percentage,fixed_price,fixed_discount'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'fixed_price' => ['nullable', 'numeric', 'min:0.01'],
            'fixed_discount' => ['nullable', 'numeric', 'min:0.01'],
            'status' => ['required', 'in:ACTIVE,DRAFT'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:255'],
        ]);

        $productMap = $graphql->productsByIds($shop, $data['product_ids']);
        if (empty($productMap)) {
            throw ValidationException::withMessages(['product_ids' => 'None of the selected products could be found in Shopify.']);
        }

        $totalOriginalPrice = 0.0;
        $itemsHtml = '';
        $firstImage = null;

        // Also search full product info for images
        $searchResults = $graphql->searchProducts($shop, '');
        $imageLookup = [];
        foreach ($searchResults as $sr) {
            if (!empty($sr['image'])) {
                $imageLookup[$sr['id']] = $sr['image'];
            }
        }

        foreach ($productMap as $pid => $p) {
            $variantPrice = (float) ($p['variants'][0]['price'] ?? 0.0);
            $totalOriginalPrice += $variantPrice;
            $titleEsc = htmlspecialchars($p['title'] ?? 'Product', ENT_QUOTES, 'UTF-8');
            $priceFormatted = number_format($variantPrice, 2);
            $itemsHtml .= "<li><strong>{$titleEsc}</strong> — Individual Value: \${$priceFormatted}</li>\n";

            if (!$firstImage && !empty($imageLookup[$pid])) {
                $firstImage = $imageLookup[$pid];
            }
        }

        $pricingType = $data['pricing_type'];
        $bundlePrice = $totalOriginalPrice;

        if ($pricingType === 'percentage') {
            $discountPct = (float) ($data['discount_percent'] ?? 10);
            $bundlePrice = max(0.01, $totalOriginalPrice * (1 - ($discountPct / 100)));
        } elseif ($pricingType === 'fixed_price') {
            $bundlePrice = (float) ($data['fixed_price'] ?? $totalOriginalPrice);
        } elseif ($pricingType === 'fixed_discount') {
            $discountAmt = (float) ($data['fixed_discount'] ?? 0);
            $bundlePrice = max(0.01, $totalOriginalPrice - $discountAmt);
        }

        $savingsAmount = max(0, $totalOriginalPrice - $bundlePrice);
        $savingsPct = $totalOriginalPrice > 0 ? round(($savingsAmount / $totalOriginalPrice) * 100) : 0;

        $desc = '<div class="salessnap-bundle-overview" style="font-family: inherit; margin: 16px 0; padding: 16px; border: 1px solid #e1e3e5; border-radius: 8px; background: #fafbfb;">' . "\n";
        if ($savingsAmount > 0) {
            $desc .= '<p style="color: #0e5b38; font-weight: 600; font-size: 15px; margin: 0 0 10px 0;">🎉 Special Value Bundle Offer: Save ' . $savingsPct . '% ($' . number_format($savingsAmount, 2) . ') off individual prices!</p>' . "\n";
        }
        if (!empty($data['custom_description'])) {
            $desc .= '<p style="margin: 0 0 12px 0;">' . nl2br(htmlspecialchars($data['custom_description'], ENT_QUOTES, 'UTF-8')) . '</p>' . "\n";
        }
        $desc .= '<h4 style="margin: 12px 0 8px 0; font-size: 14px;">📦 Bundle Includes:</h4>' . "\n";
        $desc .= '<ul style="margin: 0 0 12px 20px; padding: 0;">' . "\n" . $itemsHtml . '</ul>' . "\n";
        $desc .= '<p style="font-size: 13px; color: #6d7175; margin: 0;">Total Individual Value: <strike>$' . number_format($totalOriginalPrice, 2) . '</strike> · <strong>Bundle Price: $' . number_format($bundlePrice, 2) . '</strong></p>' . "\n";
        $desc .= '</div>';

        $parsedTags = ['bundle', 'salessnap-bundle'];
        if (!empty($data['tags'])) {
            $customTags = array_map('trim', explode(',', $data['tags']));
            $parsedTags = array_merge($parsedTags, $customTags);
        }

        $createdBundle = $graphql->createBundleProduct($shop, [
            'title' => $data['title'],
            'description_html' => $desc,
            'price' => $bundlePrice,
            'compare_at_price' => $totalOriginalPrice > $bundlePrice ? $totalOriginalPrice : null,
            'tags' => $parsedTags,
            'status' => $data['status'],
            'image_url' => $firstImage,
        ]);

        CampaignLog::create([
            'shop_id' => $shop->id,
            'campaign_id' => null,
            'event' => 'bundle_product_created',
            'severity' => 'info',
            'details' => [
                'title' => $data['title'],
                'bundle_id' => $createdBundle['id'],
                'items_count' => count($data['product_ids']),
                'bundle_price' => $bundlePrice,
                'compare_at_price' => $totalOriginalPrice,
                'status' => $data['status'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Bundle product \"{$data['title']}\" created successfully in Shopify!",
            'bundle' => $createdBundle,
        ], 201);
    }

    public function bulkMultipack(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['required', 'string', 'regex:/^gid:\/\/shopify\/Product\/\d+$/'],
            'pack_size' => ['required', 'integer', 'min:2', 'max:10'],
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:90'],
            'status' => ['required', 'in:ACTIVE,DRAFT'],
            'tag' => ['nullable', 'string', 'max:80'],
        ]);

        $productMap = $graphql->productsByIds($shop, $data['product_ids']);
        if (empty($productMap)) {
            throw ValidationException::withMessages(['product_ids' => 'None of the selected products could be found in Shopify.']);
        }

        $packSize = (int) $data['pack_size'];
        $discountPct = (float) $data['discount_percent'];
        $createdList = [];

        // Image lookup
        $searchResults = $graphql->searchProducts($shop, '');
        $imageLookup = [];
        foreach ($searchResults as $sr) {
            if (!empty($sr['image'])) {
                $imageLookup[$sr['id']] = $sr['image'];
            }
        }

        foreach ($productMap as $pid => $p) {
            $unitPrice = (float) ($p['variants'][0]['price'] ?? 0.0);
            $totalPackValue = $unitPrice * $packSize;
            $bundlePrice = max(0.01, $totalPackValue * (1 - ($discountPct / 100)));
            $savingsAmount = max(0, $totalPackValue - $bundlePrice);

            $title = "{$p['title']} ({$packSize}-Pack Value Bundle)";
            $desc = '<div class="salessnap-bundle-overview" style="margin: 16px 0; padding: 16px; border: 1px solid #e1e3e5; border-radius: 8px; background: #fafbfb;">' . "\n";
            $desc .= '<p style="color: #0e5b38; font-weight: 600; font-size: 15px; margin: 0 0 10px 0;">⚡ Bulk ' . $packSize . '-Pack Saver: Get ' . $discountPct . '% OFF!</p>' . "\n";
            $desc .= '<p>Includes <strong>' . $packSize . 'x ' . htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') . '</strong> at an exclusive multipack rate.</p>' . "\n";
            $desc .= '<p style="font-size: 13px; color: #6d7175; margin: 0;">Regular Price: <strike>$' . number_format($totalPackValue, 2) . '</strike> · <strong>Bundle Price: $' . number_format($bundlePrice, 2) . ' (Save $' . number_format($savingsAmount, 2) . ')</strong></p>' . "\n";
            $desc .= '</div>';

            $tags = ['bundle', 'salessnap-bundle', 'multipack', "{$packSize}-pack"];
            if (!empty($data['tag'])) {
                $tags[] = trim($data['tag']);
            }

            $img = $imageLookup[$pid] ?? null;

            $bundle = $graphql->createBundleProduct($shop, [
                'title' => $title,
                'description_html' => $desc,
                'price' => $bundlePrice,
                'compare_at_price' => $totalPackValue,
                'tags' => $tags,
                'status' => $data['status'],
                'image_url' => $img,
            ]);

            $createdList[] = $bundle;
        }

        CampaignLog::create([
            'shop_id' => $shop->id,
            'campaign_id' => null,
            'event' => 'bulk_multipacks_created',
            'severity' => 'info',
            'details' => [
                'count' => count($createdList),
                'pack_size' => $packSize,
                'discount_percent' => $discountPct,
                'status' => $data['status'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Successfully created ' . count($createdList) . ' bundle products in Shopify!',
            'count' => count($createdList),
            'bundles' => $createdList,
        ], 201);
    }
}
