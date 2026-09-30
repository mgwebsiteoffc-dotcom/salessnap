<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model {
    protected $table = 'campaigns';

    protected $fillable = [
        'shop_id',
        'name',
        'status',
        'product_ids',
        'actions',
        'timezone',
        'starts_at',
        'ends_at',
        'snapshot_complete',
        'started_at',
        'completed_at',
        'restore_requested_at',
        'error_count',
    ];

    protected function casts(): array {
        return [
            'product_ids' => 'array',
            'actions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'snapshot_complete' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'restore_requested_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo {
        return $this->belongsTo(Shop::class);
    }

    public function snapshots(): HasMany {
        return $this->hasMany(CampaignSnapshot::class);
    }
}
