<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model {
    protected $table = 'shops';

    protected $fillable = [
        'shop_domain',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'refresh_token_expires_at',
        'granted_scopes',
        'installed_at',
        'uninstalled_at',
    ];

    protected function casts(): array {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'installed_at' => 'datetime',
            'uninstalled_at' => 'datetime',
        ];
    }

    public function campaigns(): HasMany {
        return $this->hasMany(Campaign::class);
    }

    public function logs(): HasMany {
        return $this->hasMany(CampaignLog::class);
    }
}
