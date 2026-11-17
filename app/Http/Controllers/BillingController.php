<?php
namespace App\Http\Controllers;

use App\Services\ShopifyGraphql;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController {
    public function index(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        $activeSub = null;
        $subscriptionLookupSucceeded = false;
        try {
            $activeSub = $graphql->getActiveSubscription($shop);
            $subscriptionLookupSucceeded = true;
        } catch (\Throwable $e) {
            Log::warning('Could not query active subscription from Shopify: ' . $e->getMessage());
        }

        if ($activeSub && ($activeSub['status'] ?? '') === 'ACTIVE') {
            $shop->update([
                'plan' => 'pro',
                'charge_id' => $activeSub['id'] ?? $shop->charge_id,
                'subscription_status' => 'ACTIVE',
            ]);
        } elseif ($subscriptionLookupSucceeded && $shop->plan === 'pro' && !$activeSub) {
            $shop->update([
                'plan' => 'free',
                'charge_id' => null,
                'subscription_status' => null,
            ]);
        }

        return response()->json([
            'plan' => $shop->plan ?? 'free',
            'charge_id' => $shop->charge_id,
            'subscription_status' => $shop->subscription_status,
            'active_subscription' => $activeSub,
            'plans' => config('shopify.plans'),
            'test_mode' => (bool) config('shopify.billing_test_mode', true),
        ]);
    }

    public function subscribe(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');
        $planKey = (string) $request->input('plan', 'pro');

        $rawUrl = (string)(config('shopify.app_url') ?: $request->getSchemeAndHttpHost());
        $cleanUrl = preg_replace('~/app/?\z~i', '', rtrim($rawUrl, '/'));
        $returnUrl = $cleanUrl . '/app?page=billing&subscribed=1&shop=' . urlencode($shop->shop_domain);

        try {
            $result = $graphql->createAppSubscription($shop, $planKey, $returnUrl);
            $confirmationUrl = $result['confirmationUrl'] ?? null;
            $subscription = $result['appSubscription'] ?? [];

            if (!$confirmationUrl) {
                return response()->json([
                    'message' => 'Shopify did not return a subscription confirmation URL.',
                ], 500);
            }

            $shop->update([
                'charge_id' => $subscription['id'] ?? null,
                'subscription_status' => $subscription['status'] ?? 'PENDING',
            ]);

            return response()->json([
                'confirmation_url' => $confirmationUrl,
                'subscription_id' => $subscription['id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('App subscription creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, ShopifyGraphql $graphql) {
        $shop = $request->attributes->get('shop');

        try {
            $activeSub = $graphql->getActiveSubscription($shop);
            if (!$activeSub) {
                $shop->update([
                    'plan' => 'free',
                    'charge_id' => null,
                    'subscription_status' => null,
                ]);
                return response()->json([
                    'message' => 'No active subscription was found. Your store is on the Free plan.',
                    'plan' => 'free',
                ]);
            }

            $subscriptionId = $activeSub['id'] ?? null;
            if (!$subscriptionId) {
                throw new \RuntimeException('Shopify returned an active subscription without an ID.');
            }

            $result = $graphql->cancelAppSubscription($shop, $subscriptionId);
            $cancelledSubscription = $result['appSubscription'] ?? [];
            if (($cancelledSubscription['status'] ?? '') !== 'CANCELLED') {
                throw new \RuntimeException('Shopify did not confirm that the subscription was cancelled.');
            }

            $shop->update([
                'plan' => 'free',
                'charge_id' => null,
                'subscription_status' => 'CANCELLED',
            ]);
            return response()->json([
                'message' => 'Subscription successfully cancelled. Your store is now on the Free plan.',
                'plan' => 'free',
            ]);
        } catch (\Throwable $e) {
            Log::error('App subscription cancellation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to cancel subscription: ' . $e->getMessage(),
            ], 500);
        }
    }
}
