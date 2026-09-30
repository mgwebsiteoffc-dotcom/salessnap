<?php
namespace App\Jobs;
use App\Models\Campaign;
use App\Services\CampaignRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
class StartCampaign implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries=3; public int $timeout=1800;
    public function __construct(public int $campaignId) {}
    public function middleware(): array {return [(new WithoutOverlapping('campaign-'.$this->campaignId))->expireAfter(2100)];}
    public function handle(CampaignRunner $runner): void {$c=Campaign::with('shop')->find($this->campaignId);if($c)$runner->start($c);}
    public function failed(\Throwable $e): void {$c=Campaign::find($this->campaignId);if($c){$c->update(['status'=>'needs_attention','error_count'=>$c->error_count+1]);\App\Models\CampaignLog::create(['campaign_id'=>$c->id,'shop_id'=>$c->shop_id,'event'=>'campaign_job_failed','severity'=>'error','details'=>['message'=>mb_substr($e->getMessage(),0,500)]]);}}
}
