<?php

namespace Webook\LaravelCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssetFolder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cms_asset_folders';

    protected $fillable = [
        'name',
        'slug',
        'path',
        'description',
        'parent_id',
        'depth',
        'tree_path',
        'sort_order',
        'color',
        'is_public',
        'permissions',
        'owner_id',
        'assets_count',
        'total_size',
        'metadata',
        'settings',
    ];

    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'settings' => 'array',
        'is_public' => 'boolean',
    ];

    protected $appends = [
        'formatted_size',
        'full_path',
        'breadcrumbs',
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AssetFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AssetFolder::class, 'parent_id')->orderBy('sort_order');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'folder_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'owner_id');
    }

    public function descendants(): HasMany
    {
        return $this->hasMany(AssetFolder::class, 'parent_id');
    }

    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    public function allDescendants()
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->allDescendants());
        }

        return $descendants;
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeWithDepth($query, $depth)
    {
        return $query->where('depth', $depth);
    }

    public function scopeInParent($query, $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
    }

    public function scopeSearch($query, $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =============================================================================
    // ACCESSORS
    // =============================================================================

    public function getFormattedSizeAttribute(): string
    {
        return $this->formatBytes($this->total_size);
    }

    public function getFullPathAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->full_path . '/' . $this->name;
        }

        return $this->name;
    }

    public function getBreadcrumbsAttribute(): array
    {
        $breadcrumbs = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumbs, [
                'id' => $current->id,
                'name' => $current->name,
                'path' => $current->path,
            ]);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    // =============================================================================
    // METHODS
    // =============================================================================

    public function createChild(string $name, array $attributes = []): AssetFolder
    {
        $slug = Str::slug($name);
        $path = $this->path . '/' . $slug;

        return static::create(array_merge([
            'name' => $name,
            'slug' => $slug,
            'path' => $path,
            'parent_id' => $this->id,
            'depth' => $this->depth + 1,
            'tree_path' => $this->tree_path . '.' . $this->id,
            'is_public' => $this->is_public,
            'owner_id' => $this->owner_id,
        ], $attributes));
    }

    public function moveTo(AssetFolder $newParent = null): bool
    {
        if ($newParent && $newParent->id === $this->id) {
            throw new \InvalidArgumentException('Cannot move folder to itself');
        }

        if ($newParent && $this->isAncestorOf($newParent)) {
            throw new \InvalidArgumentException('Cannot move folder to its descendant');
        }

        $oldPath = $this->path;
        $oldDepth = $this->depth;

        if ($newParent) {
            $this->parent_id = $newParent->id;
            $this->depth = $newParent->depth + 1;
            $this->path = $newParent->path . '/' . $this->slug;
            $this->tree_path = $newParent->tree_path . '.' . $newParent->id;
        } else {
            $this->parent_id = null;
            $this->depth = 0;
            $this->path = $this->slug;
            $this->tree_path = null;
        }

        $this->save();

        // Update all descendants
        $depthDifference = $this->depth - $oldDepth;
        $this->updateDescendantsPaths($oldPath, $this->path, $depthDifference);

        return true;
    }

    public function rename(string $newName): bool
    {
        $oldPath = $this->path;
        $oldSlug = $this->slug;

        $this->name = $newName;
        $this->slug = Str::slug($newName);

        if ($this->parent) {
            $this->path = $this->parent->path . '/' . $this->slug;
        } else {
            $this->path = $this->slug;
        }

        $this->save();

        // Update all descendants
        $this->updateDescendantsPaths($oldPath, $this->path, 0);

        return true;
    }

    public function canContainAssets(): bool
    {
        $maxDepth = config('cms-assets.folders.max_depth', 5);
        return $this->depth < $maxDepth;
    }

    public function isAncestorOf(AssetFolder $folder): bool
    {
        $current = $folder->parent;

        while ($current) {
            if ($current->id === $this->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    public function isDescendantOf(AssetFolder $folder): bool
    {
        return $folder->isAncestorOf($this);
    }

    public function getTotalAssetsCount(): int
    {
        $count = $this->assets()->count();

        foreach ($this->children as $child) {
            $count += $child->getTotalAssetsCount();
        }

        return $count;
    }

    public function getTotalSize(): int
    {
        $size = $this->assets()->sum('size');

        foreach ($this->children as $child) {
            $size += $child->getTotalSize();
        }

        return $size;
    }

    public function updateStatistics(): void
    {
        $this->update([
            'assets_count' => $this->assets()->count(),
            'total_size' => $this->assets()->sum('size'),
        ]);
    }

    public function getAvailablePermissions(): array
    {
        return [
            'view' => 'View folder and assets',
            'upload' => 'Upload new assets to folder',
            'edit' => 'Edit assets in folder',
            'delete' => 'Delete assets from folder',
            'manage' => 'Full folder management',
        ];
    }

    public function grantPermission($user, string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        $userId = is_object($user) ? $user->id : $user;

        if (!isset($permissions['users'])) {
            $permissions['users'] = [];
        }

        if (!isset($permissions['users'][$userId])) {
            $permissions['users'][$userId] = [];
        }

        if (!in_array($permission, $permissions['users'][$userId])) {
            $permissions['users'][$userId][] = $permission;
            $this->update(['permissions' => $permissions]);
            return true;
        }

        return false;
    }

    public function revokePermission($user, string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        $userId = is_object($user) ? $user->id : $user;

        if (isset($permissions['users'][$userId])) {
            $key = array_search($permission, $permissions['users'][$userId]);
            if ($key !== false) {
                unset($permissions['users'][$userId][$key]);
                $permissions['users'][$userId] = array_values($permissions['users'][$userId]);

                if (empty($permissions['users'][$userId])) {
                    unset($permissions['users'][$userId]);
                }

                $this->update(['permissions' => $permissions]);
                return true;
            }
        }

        return false;
    }

    public function hasPermission($user, string $permission): bool
    {
        if (!$user) {
            return $this->is_public && $permission === 'view';
        }

        $userId = is_object($user) ? $user->id : $user;

        // Owner has all permissions
        if ($this->owner_id === $userId) {
            return true;
        }

        // Check specific permissions
        $permissions = $this->permissions ?? [];

        if (isset($permissions['users'][$userId])) {
            return in_array($permission, $permissions['users'][$userId]) || in_array('manage', $permissions['users'][$userId]);
        }

        // Check role-based permissions
        if (is_object($user) && method_exists($user, 'roles')) {
            $userRoles = $user->roles()->pluck('name')->toArray();

            if (isset($permissions['roles'])) {
                foreach ($userRoles as $role) {
                    if (isset($permissions['roles'][$role])) {
                        if (in_array($permission, $permissions['roles'][$role]) || in_array('manage', $permissions['roles'][$role])) {
                            return true;
                        }
                    }
                }
            }
        }

        // Check parent folder permissions
        if ($this->parent) {
            return $this->parent->hasPermission($user, $permission);
        }

        return false;
    }

    public function getTree(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'depth' => $this->depth,
            'assets_count' => $this->assets_count,
            'total_size' => $this->total_size,
            'formatted_size' => $this->formatted_size,
            'children' => $this->children->map(function ($child) {
                return $child->getTree();
            })->toArray(),
        ];
    }

    public function delete(): bool
    {
        // Move all assets to parent folder or root
        $targetFolder = $this->parent;

        foreach ($this->assets as $asset) {
            $asset->move($targetFolder);
        }

        // Move all child folders to parent
        foreach ($this->children as $child) {
            $child->moveTo($targetFolder);
        }

        return parent::delete();
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    protected function updateDescendantsPaths(string $oldBasePath, string $newBasePath, int $depthDifference): void
    {
        $descendants = static::where('tree_path', 'like', '%.' . $this->id . '.%')
                             ->orWhere('tree_path', 'like', '%.' . $this->id)
                             ->get();

        foreach ($descendants as $descendant) {
            $descendant->path = str_replace($oldBasePath, $newBasePath, $descendant->path);
            $descendant->depth += $depthDifference;
            $descendant->save();

            // Update assets in this descendant folder
            $descendant->assets()->update(['folder_path' => $descendant->path]);
        }
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // =============================================================================
    // BOOT METHOD
    // =============================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($folder) {
            if (!$folder->slug) {
                $folder->slug = Str::slug($folder->name);
            }

            if (!$folder->path) {
                if ($folder->parent) {
                    $folder->path = $folder->parent->path . '/' . $folder->slug;
                    $folder->depth = $folder->parent->depth + 1;
                    $folder->tree_path = $folder->parent->tree_path . '.' . $folder->parent->id;
                } else {
                    $folder->path = $folder->slug;
                    $folder->depth = 0;
                }
            }

            // Ensure unique path
            $originalPath = $folder->path;
            $counter = 1;
            while (static::where('path', $folder->path)->exists()) {
                $folder->path = $originalPath . '-' . $counter;
                $counter++;
            }
        });

        static::saved(function ($folder) {
            // Update parent folder statistics
            if ($folder->parent) {
                $folder->parent->updateStatistics();
            }
        });

        static::deleted(function ($folder) {
            // Update parent folder statistics
            if ($folder->parent) {
                $folder->parent->updateStatistics();
            }
        });
    }
}