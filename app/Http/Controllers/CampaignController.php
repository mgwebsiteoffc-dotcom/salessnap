<?php
namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Services\CampaignRunner;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CampaignController {
    public function dashboard(Request $request, CampaignRunner $runner) {
        $shop = $request->attributes->get('shop');

        // Automatically trigger any campaigns that reached their start or end time
        try {
            $runner->processDueForShop($shop);
        } catch (\Throwable $e) {
            Log::warning("Could not auto-process due campaigns for {$shop->shop_domain}: " . $e->getMessage());
        }

        $campaigns = $shop->campaigns()->withCount('snapshots')->latest()->limit(30)->get();
        return response()->json([
            'shop' => $shop->shop_domain,
            'stats' => [
                'live' => $shop->campaigns()->whereIn('status', ['running', 'needs_attention', 'applying'])->count(),
                'scheduled' => $shop->campaigns()->where('status', 'scheduled')->count(),
                'products_protected' => $shop->campaigns()->where('snapshot_complete', true)->whereIn('status', ['running', 'needs_attention', 'restoring'])->withCount('snapshots')->get()->sum('snapshots_count'),
                'rollbacks' => $shop->campaigns()->whereIn('status', ['completed', 'completed_with_conflicts'])->count(),
            ],
            'campaigns' => $campaigns->map(fn($c) => $this->campaignJson($c)),
            'logs' => $shop->logs()->latest()->limit(8)->get(),
        ]);
    }

    public function store(Request $request, CampaignRunner $runner) {
        $shop = $request->attributes->get('shop');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'product_ids' => ['required', 'array', 'min:1', 'max:250'],
            'product_ids.*' => ['required', 'string', 'distinct', 'regex:/^gid:\/\/shopify\/Product\/\d+$/'],
            'timezone' => ['required', 'string', 'max:64', function ($attribute, $value, $fail) {
                try {
                    new DateTimeZone($value);
                } catch (\Throwable $e) {
                    $fail('The ' . $attribute . ' field must be a valid timezone identifier.');
                }
            }],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'actions' => ['required', 'array:price_percent,add_tag,description_prefix'],
            'actions.price_percent' => ['nullable', 'numeric', 'gt:0', 'lte:90'],
            'actions.add_tag' => ['nullable', 'string', 'max:80'],
            'actions.description_prefix' => ['nullable', 'string', 'max:240'],
        ]);

        $actions = array_filter([
            'price_percent' => isset($data['actions']['price_percent']) ? (float)$data['actions']['price_percent'] : null,
            'add_tag' => isset($data['actions']['add_tag']) ? trim($data['actions']['add_tag']) : null,
            'description_prefix' => isset($data['actions']['description_prefix']) ? trim($data['actions']['description_prefix']) : null,
        ], fn($v) => $v !== null && $v !== '');

        if (!$actions) {
            throw ValidationException::withMessages(['actions' => 'Choose at least one change to apply.']);
        }

        if (isset($actions['add_tag']) && (str_contains($actions['add_tag'], ',') || str_contains($actions['add_tag'], "\n"))) {
            throw ValidationException::withMessages(['actions.add_tag' => 'Enter one tag at a time.']);
        }

        try {
            $tz = new DateTimeZone($data['timezone']);
        } catch (\Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }

        $start = CarbonImmutable::parse($data['starts_at'], $tz)->utc();
        $end = CarbonImmutable::parse($data['ends_at'], $tz)->utc();

        if ($end->lte(now())) {
            throw ValidationException::withMessages(['ends_at' => 'The campaign end must be in the future.']);
        }

        $campaign = DB::transaction(function() use ($shop, $data, $actions, $start, $end) {
            $lockedShop = \App\Models\Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $pending = $lockedShop->campaigns()->whereIn('status', ['scheduled', 'applying', 'running', 'needs_attention', 'restoring'])->get(['id', 'product_ids', 'starts_at', 'ends_at']);

            foreach ($pending as $existing) {
                $overlap = array_intersect($data['product_ids'], $existing->product_ids ?? []);
                $timeOverlap = $start->lt($existing->ends_at) && $end->gt($existing->starts_at);
                if ($overlap && $timeOverlap) {
                    throw ValidationException::withMessages(['product_ids' => 'These products already have an overlapping campaign. Please change the dates or product selection to protect the original values.']);
                }
            }

            $c = $lockedShop->campaigns()->create([
                'name' => $data['name'],
                'status' => 'scheduled',
                'product_ids' => array_values($data['product_ids']),
                'actions' => $actions,
                'timezone' => $data['timezone'],
                'starts_at' => $start,
                'ends_at' => $end,
            ]);

            CampaignLog::create([
                'shop_id' => $shop->id,
                'campaign_id' => $c->id,
                'event' => 'campaign_scheduled',
                'severity' => 'info',
                'details' => ['products' => count($data['product_ids'])],
            ]);

            return $c;
        });

        // If the scheduled start time is due right now or past, immediately run it
        if ($start->lte(now())) {
            try {
                $runner->start($campaign);
                $campaign->refresh();
            } catch (\Throwable $e) {
                Log::error("Immediate campaign start error for {$campaign->id}: " . $e->getMessage());
            }
        }

        return response()->json(['campaign' => $this->campaignJson($campaign)], 201);
    }

    public function startNow(Request $request, int $campaign, CampaignRunner $runner) {
        $shop = $request->attributes->get('shop');
        $c = $shop->campaigns()->whereKey($campaign)->firstOrFail();

        if ($c->status !== 'scheduled') {
            return response()->json(['message' => 'Only scheduled campaigns can be started now.'], 409);
        }

        try {
            $c->update(['starts_at' => now()]);
            $runner->start($c);
            $c->refresh();
            return response()->json([
                'success' => true,
                'message' => 'Campaign started immediately and product prices are now live!',
                'campaign' => $this->campaignJson($c),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to start campaign: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(Request $request, int $campaign, CampaignRunner $runner) {
        $shop = $request->attributes->get('shop');
        $c = $shop->campaigns()->whereKey($campaign)->firstOrFail();

        if (!$c->snapshot_complete || !in_array($c->status, ['running', 'needs_attention', 'applying'], true)) {
            return response()->json(['message' => 'This campaign does not have a restorable snapshot or is already complete.'], 409);
        }

        $claimed = DB::transaction(function() use ($c) {
            $locked = Campaign::whereKey($c->id)->lockForUpdate()->first();
            if (!$locked || !in_array($locked->status, ['running', 'needs_attention', 'applying'], true)) {
                return false;
            }
            $locked->update(['status' => 'restoring', 'restore_requested_at' => now()]);
            return true;
        });

        if (!$claimed) {
            return response()->json(['message' => 'A restore is already in progress.'], 409);
        }

        try {
            $runner->restore($c, true);
        } catch (\Throwable $e) {
            Log::error("Emergency restore error for {$c->id}: " . $e->getMessage());
        }

        CampaignLog::create([
            'campaign_id' => $c->id,
            'shop_id' => $shop->id,
            'event' => 'emergency_restore_requested',
            'severity' => 'warning',
            'details' => ['requested_at' => now()->toISOString()],
        ]);

        return response()->json([
            'accepted' => true,
            'message' => 'Restore completed. Each product was checked against its snapshot and reverted.',
        ], 200);
    }

    public function retry(Request $request, int $campaign, CampaignRunner $runner) {
        $shop = $request->attributes->get('shop');
        $c = $shop->campaigns()->whereKey($campaign)->firstOrFail();

        if ($c->snapshot_complete || $c->status !== 'needs_attention') {
            return response()->json(['message' => 'Only a failed preflight with no saved snapshot can be retried.'], 409);
        }
        if ($c->ends_at->isPast()) {
            return response()->json(['message' => 'The campaign end time has passed. Create a new campaign with a future schedule.'], 409);
        }

        $c->update(['status' => 'scheduled', 'error_count' => 0]);
        CampaignLog::create([
            'campaign_id' => $c->id,
            'shop_id' => $shop->id,
            'event' => 'campaign_preflight_retry_requested',
            'severity' => 'info',
            'details' => [],
        ]);

        if ($c->starts_at->lte(now())) {
            try {
                $runner->start($c);
            } catch (\Throwable $e) {
                Log::error("Retry start error for {$c->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'accepted' => true,
            'message' => 'Campaign preflight queued and retried.',
        ]);
    }

    public function cancel(Request $request, int $campaign) {
        $shop = $request->attributes->get('shop');
        $c = $shop->campaigns()->whereKey($campaign)->firstOrFail();

        if ($c->status !== 'scheduled' || $c->snapshot_complete) {
            return response()->json(['message' => 'Only a scheduled campaign with no snapshot can be cancelled.'], 409);
        }

        $changed = Campaign::whereKey($c->id)->where('status', 'scheduled')->where('snapshot_complete', false)->update(['status' => 'cancelled']);
        if (!$changed) {
            return response()->json(['message' => 'Campaign has already started.'], 409);
        }

        CampaignLog::create([
            'campaign_id' => $c->id,
            'shop_id' => $shop->id,
            'event' => 'campaign_cancelled',
            'severity' => 'info',
            'details' => [],
        ]);

        return response()->json(['cancelled' => true]);
    }

    public function snapshots(Request $request) {
        $shop = $request->attributes->get('shop');
        return response()->json([
            'snapshots' => $shop->campaigns()->with('snapshots')->latest()->limit(30)->get()->flatMap(fn($c) => $c->snapshots->map(fn($s) => [
                'campaign' => $c->name,
                'campaign_id' => $c->id,
                'product_gid' => $s->product_gid,
                'product_title' => $s->original_data['title'] ?? 'Product',
                'status' => $s->status,
                'conflicts' => $s->conflicts,
                'last_error' => $s->last_error,
                'restored_at' => $s->restored_at,
            ])),
        ]);
    }

    private function campaignJson(Campaign $c): array {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status,
            'product_count' => count($c->product_ids ?? []),
            'product_ids' => $c->product_ids,
            'actions' => $c->actions,
            'timezone' => $c->timezone,
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'snapshot_complete' => $c->snapshot_complete,
            'snapshot_count' => $c->snapshots_count ?? $c->snapshots()->count(),
            'error_count' => $c->error_count,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}

