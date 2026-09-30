<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CampaignSnapshot extends Model {
    protected $fillable=['campaign_id','product_gid','original_data','original_hash','applied_data','status','conflicts','last_error','restored_at'];
    protected function casts(): array { return ['original_data'=>'array','applied_data'=>'array','conflicts'=>'array','restored_at'=>'datetime']; }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
}
