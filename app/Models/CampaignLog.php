<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CampaignLog extends Model { protected $fillable=['campaign_id','shop_id','event','severity','details']; protected function casts(): array { return ['details'=>'array']; } }
