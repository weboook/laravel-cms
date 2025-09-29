<?php

namespace Webook\LaravelCMS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * History Resource
 *
 * API resource for content history and versioning information.
 */
class HistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'content_type' => $this->content_type,
            'content_id' => $this->content_id,
            'action' => $this->action,

            // Change information
            'changes' => [
                'old_data' => $this->when($this->old_data, $this->old_data),
                'new_data' => $this->when($this->new_data, $this->new_data),
                'summary' => $this->getChangeSummary(),
                'field_changes' => $this->getFieldChanges(),
            ],

            // Content preview
            'content_preview' => [
                'before' => $this->getContentPreview($this->old_data),
                'after' => $this->getContentPreview($this->new_data),
            ],

            // Metadata
            'metadata' => $this->when($this->metadata, array_merge($this->metadata ?? [], [
                'ip_address' => $this->metadata['ip_address'] ?? null,
                'user_agent' => $this->getUserAgentInfo(),
                'session_id' => $this->metadata['session_id'] ?? null,
                'request_id' => $this->metadata['request_id'] ?? null,
            ])),

            // Version information
            'version' => [
                'number' => $this->version_number ?? null,
                'is_major' => $this->when(isset($this->is_major_version), (bool) $this->is_major_version),
                'changelog' => $this->when($this->changelog, $this->changelog),
                'tags' => $this->when($this->version_tags, $this->version_tags),
            ],

            // Statistics
            'stats' => [
                'size_change' => $this->calculateSizeChange(),
                'content_length_before' => $this->getContentLength($this->old_data),
                'content_length_after' => $this->getContentLength($this->new_data),
                'words_changed' => $this->calculateWordsChanged(),
            ],

            // User information
            'user' => new UserResource($this->whenLoaded('user')),
            'user_id' => $this->user_id,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'revision_date' => $this->created_at?->format('Y-m-d H:i:s'),

            // Restoration information
            'can_restore' => $this->when(
                $request->user()?->can('restore', $this->resource),
                $this->canBeRestored()
            ),
            'restore_complexity' => $this->getRestoreComplexity(),

            // Diff information
            'diff' => $this->when($request->get('include_diff'), function () {
                return $this->generateDiff();
            }),

            // Permissions
            'permissions' => [
                'view' => true,
                'restore' => $this->when(
                    $request->user()?->can('restore', $this->resource),
                    true
                ),
                'compare' => true,
                'download' => $this->when(
                    $request->user()?->can('download-revision', $this->resource),
                    true
                ),
            ],

            // Links
            'links' => [
                'self' => route('api.content.history.show', ['id' => $this->id]),
                'restore' => $this->when(
                    $request->user()?->can('restore', $this->resource),
                    route('api.content.history.restore', ['id' => $this->id])
                ),
                'compare' => route('api.content.history.compare', [
                    'revision_a' => $this->id,
                    'revision_b' => '__REVISION_B__'
                ]),
                'content' => $this->getContentLink(),
            ],
        ];
    }

    /**
     * Get a summary of changes made.
     */
    private function getChangeSummary(): string
    {
        $action = ucfirst($this->action);

        switch ($this->action) {
            case 'created':
                return "{$action} new content";
            case 'updated':
                return $this->getUpdateSummary();
            case 'deleted':
                return "{$action} content";
            case 'restored':
                return "{$action} from revision";
            default:
                return $action;
        }
    }

    /**
     * Get detailed update summary.
     */
    private function getUpdateSummary(): string
    {
        $changes = $this->getFieldChanges();
        $fieldCount = count($changes);

        if ($fieldCount === 0) {
            return 'Updated content';
        }

        if ($fieldCount === 1) {
            $field = array_keys($changes)[0];
            return "Updated {$field}";
        }

        return "Updated {$fieldCount} fields";
    }

    /**
     * Get field-level changes.
     */
    private function getFieldChanges(): array
    {
        if (!$this->old_data || !$this->new_data) {
            return [];
        }

        $changes = [];
        $oldData = $this->old_data;
        $newData = $this->new_data;

        foreach ($newData as $field => $newValue) {
            $oldValue = $oldData[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'type' => $this->getChangeType($oldValue, $newValue),
                ];
            }
        }

        // Check for removed fields
        foreach ($oldData as $field => $oldValue) {
            if (!array_key_exists($field, $newData)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => null,
                    'type' => 'removed',
                ];
            }
        }

        return $changes;
    }

    /**
     * Determine the type of change.
     */
    private function getChangeType($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) {
            return 'added';
        }

        if ($oldValue !== null && $newValue === null) {
            return 'removed';
        }

        if (is_string($oldValue) && is_string($newValue)) {
            $oldLength = strlen($oldValue);
            $newLength = strlen($newValue);

            if ($newLength > $oldLength * 1.5) {
                return 'expanded';
            } elseif ($newLength < $oldLength * 0.5) {
                return 'reduced';
            }
        }

        return 'modified';
    }

    /**
     * Get content preview from data.
     */
    private function getContentPreview($data): ?string
    {
        if (!$data) {
            return null;
        }

        // Extract main content field
        $content = $data['value'] ?? $data['content'] ?? $data['text'] ?? null;

        if (!$content) {
            return null;
        }

        // Truncate for preview
        $preview = strip_tags($content);
        if (strlen($preview) > 200) {
            $preview = substr($preview, 0, 200) . '...';
        }

        return $preview;
    }

    /**
     * Get user agent information.
     */
    private function getUserAgentInfo(): ?array
    {
        $userAgent = $this->metadata['user_agent'] ?? null;

        if (!$userAgent) {
            return null;
        }

        // Parse user agent (simplified)
        $info = [
            'full' => $userAgent,
            'browser' => $this->extractBrowser($userAgent),
            'platform' => $this->extractPlatform($userAgent),
            'is_mobile' => $this->isMobileUserAgent($userAgent),
        ];

        return $info;
    }

    /**
     * Extract browser from user agent.
     */
    private function extractBrowser(string $userAgent): string
    {
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        if (strpos($userAgent, 'Opera') !== false) return 'Opera';

        return 'Unknown';
    }

    /**
     * Extract platform from user agent.
     */
    private function extractPlatform(string $userAgent): string
    {
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'macOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';

        return 'Unknown';
    }

    /**
     * Check if user agent indicates mobile device.
     */
    private function isMobileUserAgent(string $userAgent): bool
    {
        $mobileKeywords = ['Mobile', 'Android', 'iPhone', 'iPad', 'Windows Phone'];

        foreach ($mobileKeywords as $keyword) {
            if (strpos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate size change in bytes.
     */
    private function calculateSizeChange(): int
    {
        $oldSize = $this->getContentLength($this->old_data);
        $newSize = $this->getContentLength($this->new_data);

        return $newSize - $oldSize;
    }

    /**
     * Get content length from data.
     */
    private function getContentLength($data): int
    {
        if (!$data) {
            return 0;
        }

        $content = $data['value'] ?? $data['content'] ?? $data['text'] ?? '';
        return strlen($content);
    }

    /**
     * Calculate words changed between versions.
     */
    private function calculateWordsChanged(): int
    {
        if (!$this->old_data || !$this->new_data) {
            return 0;
        }

        $oldContent = $this->old_data['value'] ?? $this->old_data['content'] ?? '';
        $newContent = $this->new_data['value'] ?? $this->new_data['content'] ?? '';

        $oldWords = str_word_count(strip_tags($oldContent));
        $newWords = str_word_count(strip_tags($newContent));

        return abs($newWords - $oldWords);
    }

    /**
     * Check if this revision can be restored.
     */
    private function canBeRestored(): bool
    {
        // Check age limit
        $maxAge = config('cms.history.max_restore_age_days', 30);
        if ($this->created_at->diffInDays(now()) > $maxAge) {
            return false;
        }

        // Check if content still exists
        $contentExists = $this->checkContentExists();
        if (!$contentExists) {
            return false;
        }

        return true;
    }

    /**
     * Check if the original content still exists.
     */
    private function checkContentExists(): bool
    {
        try {
            $modelClass = $this->content_type;
            $model = $modelClass::find($this->content_id);

            return $model !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get restore complexity level.
     */
    private function getRestoreComplexity(): string
    {
        $complexity = 'simple';

        // Check age
        $ageDays = $this->created_at->diffInDays(now());
        if ($ageDays > 7) {
            $complexity = 'moderate';
        }

        if ($ageDays > 30) {
            $complexity = 'complex';
        }

        // Check for significant changes since this revision
        $recentChanges = $this->getRecentChangesCount();
        if ($recentChanges > 10) {
            $complexity = $complexity === 'simple' ? 'moderate' : 'complex';
        }

        return $complexity;
    }

    /**
     * Get count of changes since this revision.
     */
    private function getRecentChangesCount(): int
    {
        return \Webook\LaravelCMS\Models\ContentHistory::where([
            'content_type' => $this->content_type,
            'content_id' => $this->content_id,
        ])
        ->where('created_at', '>', $this->created_at)
        ->count();
    }

    /**
     * Generate diff between old and new data.
     */
    private function generateDiff(): array
    {
        if (!$this->old_data || !$this->new_data) {
            return [];
        }

        // This is a simplified diff - in production you might want to use
        // a more sophisticated diff library
        $diff = [];

        foreach ($this->new_data as $field => $newValue) {
            $oldValue = $this->old_data[$field] ?? null;

            if ($oldValue !== $newValue) {
                $diff[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'diff_lines' => $this->generateLineDiff($oldValue, $newValue),
                ];
            }
        }

        return $diff;
    }

    /**
     * Generate line-by-line diff.
     */
    private function generateLineDiff($old, $new): array
    {
        if (!is_string($old) || !is_string($new)) {
            return [];
        }

        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);

        // Simple line diff implementation
        $diff = [];
        $maxLines = max(count($oldLines), count($newLines));

        for ($i = 0; $i < $maxLines; $i++) {
            $oldLine = $oldLines[$i] ?? null;
            $newLine = $newLines[$i] ?? null;

            if ($oldLine !== $newLine) {
                if ($oldLine !== null && $newLine !== null) {
                    $diff[] = ['type' => 'changed', 'old' => $oldLine, 'new' => $newLine];
                } elseif ($oldLine === null) {
                    $diff[] = ['type' => 'added', 'line' => $newLine];
                } else {
                    $diff[] = ['type' => 'removed', 'line' => $oldLine];
                }
            }
        }

        return $diff;
    }

    /**
     * Get link to the original content.
     */
    private function getContentLink(): ?string
    {
        $type = strtolower(class_basename($this->content_type));

        $routes = [
            'textcontent' => 'api.content.text.show',
            'image' => 'api.images.show',
            'link' => 'api.links.show',
            'translation' => 'api.translations.show',
        ];

        $route = $routes[$type] ?? null;

        if (!$route) {
            return null;
        }

        try {
            return route($route, ['id' => $this->content_id]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
                'history_retention_days' => config('cms.history.retention_days', 90),
            ],
        ];
    }
}