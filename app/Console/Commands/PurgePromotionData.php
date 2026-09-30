<?php
namespace App\Console\Commands;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\OAuthState;
use Illuminate\Console\Command;
class PurgePromotionData extends Command {
    protected $signature='promotions:purge-retained-data';
    protected $description='Delete expired campaign snapshots and operational logs';
    public function handle(): int {
        $cutoff=now()->subDays((int)config('shopify.retention_days',90));
        Campaign::whereIn('status',['completed','completed_with_conflicts','cancelled'])->where(function($q)use($cutoff){$q->where('completed_at','<',$cutoff)->orWhere(function($sub)use($cutoff){$sub->whereNull('completed_at')->where('created_at','<',$cutoff);});})->delete();
        CampaignLog::where('created_at','<',$cutoff)->delete();
        OAuthState::where('expires_at','<',now())->delete();
        return self::SUCCESS;
    }
}
