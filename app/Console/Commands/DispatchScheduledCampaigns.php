<?php
namespace App\Console\Commands;
use App\Jobs\RollbackCampaign;
use App\Jobs\StartCampaign;
use App\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class DispatchScheduledCampaigns extends Command {
    protected $signature='promotions:dispatch-due';
    protected $description='Queue due promotion starts and safe snapshot rollbacks';
    public function handle(): int {
        Campaign::where('status','scheduled')->where('starts_at','<=',now())->orderBy('id')->limit(100)->pluck('id')->each(function($id){
            $claimed=DB::transaction(function() use($id){$c=Campaign::whereKey($id)->lockForUpdate()->first();if(!$c||$c->status!=='scheduled'||$c->starts_at->isFuture())return false;$c->update(['status'=>'applying']);return true;});
            if($claimed){try{StartCampaign::dispatch((int)$id);}catch(\Throwable $e){Campaign::whereKey($id)->where('status','applying')->update(['status'=>'scheduled']);throw $e;}}
        });
        Campaign::whereIn('status',['running','needs_attention'])->where('ends_at','<=',now())->where('snapshot_complete',true)->orderBy('id')->limit(100)->pluck('id')->each(function($id){
            $claimed=DB::transaction(function() use($id){$c=Campaign::whereKey($id)->lockForUpdate()->first();if(!$c||!in_array($c->status,['running','needs_attention'],true)||$c->ends_at->isFuture()||!$c->snapshot_complete)return false;$c->update(['status'=>'restoring']);return true;});
            if($claimed){try{RollbackCampaign::dispatch((int)$id,false);}catch(\Throwable $e){Campaign::whereKey($id)->where('status','restoring')->update(['status'=>'needs_attention']);throw $e;}}
        });
        return self::SUCCESS;
    }
}
