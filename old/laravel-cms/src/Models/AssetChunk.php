<?php

namespace Webook\LaravelCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetChunk extends Model
{
    protected $table = 'cms_asset_chunks';

    protected $fillable = [
        'upload_id',
        'chunk_hash',
        'chunk_number',
        'total_chunks',
        'original_filename',
        'mime_type',
        'total_size',
        'chunk_size',
        'disk',
        'chunk_path',
        'user_id',
        'session_id',
        'metadata',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    // =============================================================================
    // RELATIONSHIPS
    // =============================================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    // =============================================================================
    // SCOPES
    // =============================================================================

    public function scopeByUpload($query, string $uploadId)
    {
        return $query->where('upload_id', $uploadId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // =============================================================================
    // METHODS
    // =============================================================================

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(): bool
    {
        return $this->update(['status' => 'completed']);
    }

    public function markAsFailed(): bool
    {
        return $this->update(['status' => 'failed']);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->chunk_path);
    }

    public function getSize(): int
    {
        if (!$this->exists()) {
            return 0;
        }

        return Storage::disk($this->disk)->size($this->chunk_path);
    }

    public function getContents(): string
    {
        if (!$this->exists()) {
            throw new \Exception("Chunk file does not exist: {$this->chunk_path}");
        }

        return Storage::disk($this->disk)->get($this->chunk_path);
    }

    public function delete(): bool
    {
        // Delete the physical chunk file
        if ($this->exists()) {
            Storage::disk($this->disk)->delete($this->chunk_path);
        }

        return parent::delete();
    }

    public static function getAllChunksForUpload(string $uploadId): \Illuminate\Database\Eloquent\Collection
    {
        return static::byUpload($uploadId)
                    ->orderBy('chunk_number')
                    ->get();
    }

    public static function isUploadComplete(string $uploadId): bool
    {
        $chunks = static::byUpload($uploadId)->get();

        if ($chunks->isEmpty()) {
            return false;
        }

        $totalChunks = $chunks->first()->total_chunks;
        $completedChunks = $chunks->where('status', 'completed')->count();

        return $completedChunks === $totalChunks;
    }

    public static function assembleFile(string $uploadId): string
    {
        $chunks = static::byUpload($uploadId)
                       ->completed()
                       ->orderBy('chunk_number')
                       ->get();

        if ($chunks->isEmpty()) {
            throw new \Exception('No completed chunks found for upload: ' . $uploadId);
        }

        $firstChunk = $chunks->first();
        if ($chunks->count() !== $firstChunk->total_chunks) {
            throw new \Exception('Not all chunks are available for assembly');
        }

        // Create temporary file for assembly
        $tempPath = tempnam(sys_get_temp_dir(), 'asset_assembly_');
        $handle = fopen($tempPath, 'wb');

        foreach ($chunks as $chunk) {
            if (!$chunk->exists()) {
                fclose($handle);
                unlink($tempPath);
                throw new \Exception("Chunk file missing: {$chunk->chunk_path}");
            }

            $chunkContents = $chunk->getContents();
            fwrite($handle, $chunkContents);
        }

        fclose($handle);

        // Verify assembled file size
        $assembledSize = filesize($tempPath);
        if ($assembledSize !== $firstChunk->total_size) {
            unlink($tempPath);
            throw new \Exception('Assembled file size does not match expected size');
        }

        return $tempPath;
    }

    public static function cleanupExpired(): int
    {
        $expiredChunks = static::expired()->get();
        $deletedCount = 0;

        foreach ($expiredChunks as $chunk) {
            if ($chunk->delete()) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    public static function cleanupUpload(string $uploadId): bool
    {
        $chunks = static::byUpload($uploadId)->get();
        $success = true;

        foreach ($chunks as $chunk) {
            if (!$chunk->delete()) {
                $success = false;
            }
        }

        return $success;
    }

    // =============================================================================
    // BOOT METHOD
    // =============================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($chunk) {
            if (!$chunk->expires_at) {
                $chunk->expires_at = now()->addHours(24); // Default 24 hour expiration
            }
        });
    }
}