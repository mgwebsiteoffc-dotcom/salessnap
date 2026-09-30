<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OAuthState extends Model { protected $fillable=['state_hash','shop_domain','expires_at']; protected function casts(): array { return ['expires_at'=>'datetime']; } }
