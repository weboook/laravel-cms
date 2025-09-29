<?php

namespace Webook\LaravelCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AssetUsage extends Model
{
    protected $table = 'cms_asset_usage';

    protected $fillable = [
        'asset_id',
        'usable_type',
        'usable_id',
        'field_name',
        'context',
        'usage_type',
        'metadata',
        'used_at',
    ];

    protected $casts = [
        'context' => 'array',
        'metadata' => 'array',
        'used_at' => 'datetime',
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function usable(): MorphTo
    {
        return $this->morphTo();
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    public function scopeByType($query, string $type)
    {
        return $query->where('usage_type', $type);
    }

    public function scopeByModel($query, string $modelType)
    {
        return $query->where('usable_type', $modelType);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('used_at', '>=', now()->subDays($days));
    }

    // =============================================================================
    // METHODS
    // =============================================================================

    public static function trackUsage(Asset $asset, $model, string $fieldName = null, array $context = [], string $usageType = 'content'): self
    {
        return static::updateOrCreate([
            'asset_id' => $asset->id,
            'usable_type' => get_class($model),
            'usable_id' => $model->getKey(),
            'field_name' => $fieldName,
        ], [
            'context' => $context,
            'usage_type' => $usageType,
            'used_at' => now(),
        ]);
    }

    public static function removeUsage(Asset $asset, $model, string $fieldName = null): bool
    {
        return static::where([
            'asset_id' => $asset->id,
            'usable_type' => get_class($model),
            'usable_id' => $model->getKey(),
            'field_name' => $fieldName,
        ])->delete() > 0;
    }

    public static function getUnusedAssets(): \Illuminate\Database\Eloquent\Collection
    {
        return Asset::whereDoesntHave('usage')->get();
    }

    public static function getMostUsedAssets(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Asset::withCount('usage')
                   ->orderBy('usage_count', 'desc')
                   ->limit($limit)
                   ->get();
    }
}