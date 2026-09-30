<?php
namespace App\Services;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\CampaignSnapshot;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CampaignRunner {
    public function __construct(private ShopifyGraphql $shopify) {}

    public function processDueForShop(\App\Models\Shop $shop): void {
        // 1. Process any scheduled campaigns that are due to start (starts_at <= now())
        $dueStarts = $shop->campaigns()
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->get();

        foreach ($dueStarts as $c) {
            try {
                $this->start($c);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to start scheduled campaign {$c->id} for shop {$shop->shop_domain}: " . $e->getMessage());
            }
        }

        // 2. Process any running campaigns that are due to end (ends_at <= now())
        $dueRollbacks = $shop->campaigns()
            ->whereIn('status', ['running', 'needs_attention'])
            ->where('ends_at', '<=', now())
            ->where('snapshot_complete', true)
            ->get();

        foreach ($dueRollbacks as $c) {
            try {
                $this->restore($c, false);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to rollback campaign {$c->id} for shop {$shop->shop_domain}: " . $e->getMessage());
            }
        }
    }

    public function processAllDue(): void {
        Campaign::where('status', 'scheduled')
            ->where('starts_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (Campaign $c) {
                try {
                    $this->start($c);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Error starting campaign {$c->id}: " . $e->getMessage());
                }
            });

        Campaign::whereIn('status', ['running', 'needs_attention'])
            ->where('ends_at', '<=', now())
            ->where('snapshot_complete', true)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (Campaign $c) {
                try {
                    $this->restore($c, false);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Error rolling back campaign {$c->id}: " . $e->getMessage());
                }
            });
    }

    public function start(Campaign $campaign): void {
        $campaign->refresh();
        if(!in_array($campaign->status,['scheduled','applying'],true) || $campaign->starts_at->isFuture()) return;
        $shop=$campaign->shop;
        if(!$campaign->snapshot_complete){
            // Read every product fully before the first write. No writes occur if any source value is missing.
            $products=$this->shopify->productsByIds($shop,$campaign->product_ids);
            if(count($products)!==count($campaign->product_ids)) throw new RuntimeException('One or more selected products no longer exist or are inaccessible. No changes were applied.');
            DB::transaction(function() use($campaign,$products){
                foreach($products as $p) CampaignSnapshot::updateOrCreate(['campaign_id'=>$campaign->id,'product_gid'=>$p['id']],['original_data'=>$p,'original_hash'=>$this->snapshotHash($p),'status'=>'snapshotted','last_error'=>null]);
                $campaign->update(['snapshot_complete'=>true,'status'=>'applying']);
            });
            $this->log($campaign,'snapshot_completed','info',['product_count'=>count($products)]);
        }
        $errors=[];
        foreach($campaign->snapshots()->get() as $snapshot){
            try { $this->applyOne($campaign,$snapshot); }
            catch(\Throwable $e){$snapshot->update(['status'=>'apply_error','last_error'=>mb_substr($e->getMessage(),0,2000)]);$errors[]=$snapshot->product_gid;}
        }
        $campaign->update(['status'=>$errors?'needs_attention':'running','started_at'=>$campaign->started_at ?? now(),'error_count'=>count($errors)]);
        $this->log($campaign,$errors?'campaign_apply_attention':'campaign_started',$errors?'warning':'info',['products_with_errors'=>count($errors)]);
    }
    public function restore(Campaign $campaign, bool $emergency=false): void {
        $campaign->refresh();
        if(!$campaign->snapshot_complete) { $campaign->update(['status'=>'cancelled','completed_at'=>now()]); return; }
        if($campaign->status==='completed' && !$emergency) return;
        $campaign->update(['status'=>'restoring']);
        $errors=0;
        foreach($campaign->snapshots()->get() as $snapshot){
            try { $this->restoreOne($campaign,$snapshot); }
            catch(\Throwable $e){$snapshot->update(['status'=>'restore_error','last_error'=>mb_substr($e->getMessage(),0,2000)]);$errors++;}
        }
        $hasConflicts=$campaign->snapshots()->get()->contains(fn($snapshot)=>(bool)($snapshot->conflicts ?? []));
        $campaign->update(['status'=>$errors?'needs_attention':($hasConflicts?'completed_with_conflicts':'completed'),'completed_at'=>$errors?null:now(),'error_count'=>$errors]);
        $this->log($campaign,$errors?'rollback_attention':'rollback_completed',$errors?'warning':'info',['products_with_errors'=>$errors,'manual'=>$emergency]);
    }
    private function applyOne(Campaign $campaign, CampaignSnapshot $snapshot): void {
        $shop=$campaign->shop;$original=$snapshot->original_data;$actions=$campaign->actions;
        if(!hash_equals((string)$snapshot->original_hash,$this->snapshotHash($original))) throw new RuntimeException('Snapshot integrity check failed. No product changes were made for this item.');
        $current=$this->shopify->productsByIds($shop,[$snapshot->product_gid])[$snapshot->product_gid] ?? null;
        if(!$current) throw new RuntimeException('Product was deleted or is no longer accessible.');
        $tag=(string)($actions['add_tag'] ?? '');$prefix=$this->prefixHtml((string)($actions['description_prefix'] ?? ''));
        $applied=$snapshot->applied_data ?? [];
        $digits=isset($applied['currency_digits'])?(int)$applied['currency_digits']:$this->currencyDigits($shop);
        $priceTargets=$applied['sale_prices'] ?? $this->priceTargets($shop,$original,$actions,$digits);
        $priceOwned=$applied['price_owned'] ?? [];$tagOwned=(bool)($applied['tag_added'] ?? false);$descriptionOwned=(bool)($applied['description_added'] ?? false);
        $productInput=[];$conflicts=$snapshot->conflicts ?? [];
        if($tag!==''&&!in_array($tag,$original['tags'],true)){
            if(in_array($tag,$current['tags'],true)){if(!$tagOwned)$conflicts[]='tag_present_before_apply';}
            else{$tagOwned=true;$productInput['tags']=array_values(array_unique(array_merge($current['tags'],[$tag])));}
        }
        if($prefix!==''&&!str_starts_with($original['descriptionHtml'],$prefix)){
            if(str_starts_with($current['descriptionHtml'],$prefix)){if(!$descriptionOwned)$conflicts[]='description_prefix_present_before_apply';}
            elseif($current['descriptionHtml']===$original['descriptionHtml']){$descriptionOwned=true;$productInput['descriptionHtml']=$prefix.$current['descriptionHtml'];}
            else $conflicts[]='description_changed_before_apply';
        }
        $currentVariants=[];foreach($current['variants'] as $v)$currentVariants[$v['id']]=$v['price'];$variantUpdates=[];
        foreach($original['variants'] as $v){
            if(!isset($priceTargets[$v['id']]))continue;
            $nowPrice=$currentVariants[$v['id']]??null;$target=$priceTargets[$v['id']];
            if($nowPrice!==null&&$this->normalizePrice($nowPrice,$digits)===$this->normalizePrice($target,$digits)){
                if($this->normalizePrice($target,$digits)!==$this->normalizePrice($v['price'],$digits)&&!($priceOwned[$v['id']]??false))$conflicts[]='price_already_at_sale_value_before_apply:'.$v['id'];
                continue;
            }
            if($nowPrice!==null&&$this->normalizePrice($nowPrice,$digits)===$this->normalizePrice($v['price'],$digits)){
                if($this->normalizePrice($target,$digits)!==$this->normalizePrice($v['price'],$digits)){$priceOwned[$v['id']]=true;$variantUpdates[]=['id'=>$v['id'],'price'=>$target];}
            } else $conflicts[]='price_changed_before_apply:'.$v['id'];
        }
        // Persist ownership intent before the remote writes, making retries idempotent after a process crash.
        $applied=['sale_prices'=>$priceTargets,'currency_digits'=>$digits,'price_owned'=>$priceOwned,'tag'=>$tag,'tag_added'=>$tagOwned,'description_prefix_html'=>$prefix,'description_added'=>$descriptionOwned];
        $snapshot->update(['applied_data'=>$applied]);
        if($productInput)$this->shopify->updateProduct($shop,$snapshot->product_gid,$productInput);
        if($variantUpdates)$this->shopify->updateVariantPrices($shop,$snapshot->product_gid,$variantUpdates);
        $snapshot->update(['status'=>$conflicts?'applied_with_conflicts':'applied','conflicts'=>array_values(array_unique($conflicts)),'last_error'=>null]);
    }
    private function restoreOne(Campaign $campaign, CampaignSnapshot $snapshot): void {
        $shop=$campaign->shop;$original=$snapshot->original_data;$applied=$snapshot->applied_data ?? [];
        if(!hash_equals((string)$snapshot->original_hash,$this->snapshotHash($original))) throw new RuntimeException('Snapshot integrity check failed. Manual review is required.');
        $current=$this->shopify->productsByIds($shop,[$snapshot->product_gid])[$snapshot->product_gid] ?? null;
        if(!$current) throw new RuntimeException('Product was deleted or is inaccessible; snapshot could not be restored.');
        $conflicts=$snapshot->conflicts ?? [];$priceTargets=$applied['sale_prices'] ?? [];$priceOwned=$applied['price_owned'] ?? [];$digits=(int)($applied['currency_digits']??2);
        $currentPrices=[];foreach($current['variants'] as $v)$currentPrices[$v['id']]=$v['price'];$restoreVariants=[];
        foreach($original['variants'] as $v){
            if(!isset($priceTargets[$v['id']])||!($priceOwned[$v['id']]??false))continue;
            $nowPrice=$currentPrices[$v['id']]??null;
            if($nowPrice!==null&&$this->normalizePrice($nowPrice,$digits)===$this->normalizePrice($v['price'],$digits))continue;
            if($nowPrice!==null&&$this->normalizePrice($nowPrice,$digits)===$this->normalizePrice($priceTargets[$v['id']],$digits))$restoreVariants[]=['id'=>$v['id'],'price'=>$v['price']];
            else $conflicts[]='price_changed_during_campaign:'.$v['id'];
        }
        if($restoreVariants)$this->shopify->updateVariantPrices($shop,$snapshot->product_gid,$restoreVariants);
        $productInput=[];$tag=(string)($applied['tag']??'');
        if(($applied['tag_added']??false)&&$tag!==''&&!in_array($tag,$original['tags'],true)&&in_array($tag,$current['tags'],true))$productInput['tags']=array_values(array_filter($current['tags'],fn($x)=>$x!==$tag));
        $prefix=(string)($applied['description_prefix_html']??'');
        if(($applied['description_added']??false)&&$prefix!==''){
            if(str_starts_with($current['descriptionHtml'],$prefix))$productInput['descriptionHtml']=substr($current['descriptionHtml'],strlen($prefix));
            elseif($current['descriptionHtml']!==$original['descriptionHtml'])$conflicts[]='description_changed_during_campaign';
        }
        if($productInput)$this->shopify->updateProduct($shop,$snapshot->product_gid,$productInput);
        $snapshot->update(['status'=>$conflicts?'restored_with_conflicts':'restored','conflicts'=>array_values(array_unique($conflicts)),'restored_at'=>now(),'last_error'=>null]);
    }
    private function priceTargets(\App\Models\Shop $shop, array $product, array $actions, int $digits): array {
        if(!isset($actions['price_percent'])) return [];
        $pct = (float)$actions['price_percent'];
        $rounding = $shop->getSetting('price_rounding', 'none');
        $targets = [];

        foreach($product['variants'] as $v) {
            $amount = (float)$v['price'];
            $sale = round($amount * (100 - $pct) / 100, $digits, PHP_ROUND_HALF_UP);

            if ($digits === 2) {
                if ($rounding === '99') {
                    $sale = max(0.99, floor($sale) + 0.99);
                } elseif ($rounding === '95') {
                    $sale = max(0.95, floor($sale) + 0.95);
                } elseif ($rounding === 'round_dollar') {
                    $sale = max(1.00, round($sale));
                }
            }

            $targets[$v['id']] = number_format($sale, $digits, '.', '');
        }
        return $targets;
    }
    private function currencyDigits($shop): int { try { $code=$this->shopify->query($shop,'query { shop { currencyCode } }')['shop']['currencyCode'] ?? 'USD'; } catch(\Throwable $e){$code='USD';} if(in_array($code,['BHD','IQD','JOD','KWD','LYD','OMR','TND'],true))return 3;if(in_array($code,['BIF','CLP','DJF','GNF','ISK','JPY','KRW','PYG','RWF','UGX','VND','VUV','XAF','XOF'],true))return 0;return 2; }
    private function normalizePrice(string $value,int $digits): string { return number_format((float)$value,$digits,'.',''); }
    private function prefixHtml(string $text): string {if($text==='')return '';return '<p>'.htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';}
    private function snapshotHash(array $data): string { $canonical=$this->canonicalize($data); return hash('sha256',json_encode($canonical,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
    private function canonicalize(array $data): array { foreach($data as &$value){if(is_array($value))$value=$this->canonicalize($value);}unset($value);if(!array_is_list($data))ksort($data);return $data; } 
    private function log(Campaign $c,string $event,string $severity,array $details):void {CampaignLog::create(['campaign_id'=>$c->id,'shop_id'=>$c->shop_id,'event'=>$event,'severity'=>$severity,'details'=>$details]);}
}
