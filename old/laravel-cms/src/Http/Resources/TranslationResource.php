<?php

namespace Webook\LaravelCMS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Translation Resource
 *
 * API resource for translation content with language metadata.
 */
class TranslationResource extends JsonResource
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
            'locale' => $this->locale,
            'group' => $this->group,
            'key' => $this->key,
            'value' => $this->value,

            // Status and workflow
            'status' => $this->status ?? 'active',
            'auto_generated' => $this->when(isset($this->auto_generated), (bool) $this->auto_generated),
            'requires_review' => $this->when(isset($this->requires_review), (bool) $this->requires_review),

            // Translation metadata
            'source_locale' => $this->when($this->source_locale, $this->source_locale),
            'context' => $this->when($this->context, $this->context),
            'metadata' => $this->when($this->metadata, $this->metadata),

            // Language information
            'language_info' => [
                'name' => $this->getLanguageName(),
                'native_name' => $this->getNativeLanguageName(),
                'direction' => $this->getTextDirection(),
                'script' => $this->getScript(),
            ],

            // Translation quality metrics
            'quality' => [
                'length' => strlen($this->value ?? ''),
                'words' => str_word_count(strip_tags($this->value ?? '')),
                'characters' => mb_strlen($this->value ?? '', 'UTF-8'),
                'complexity_score' => $this->calculateComplexityScore(),
                'placeholder_count' => $this->countPlaceholders(),
            ],

            // Comparison with source
            'comparison' => $this->when($this->source_locale, function () {
                return $this->getSourceComparison();
            }),

            // Translation history
            'history' => $this->when($this->relationLoaded('history'), function () {
                return [
                    'revision_count' => $this->history->count(),
                    'last_revision' => $this->history->first()?->created_at?->toISOString(),
                ];
            }),

            // Usage information
            'usage' => $this->when($this->usage_stats, [
                'file_references' => $this->usage_stats['files'] ?? [],
                'template_usage' => $this->usage_stats['templates'] ?? [],
                'last_used' => $this->usage_stats['last_used'] ?? null,
                'frequency' => $this->usage_stats['frequency'] ?? 0,
            ]),

            // Validation and warnings
            'validation' => [
                'warnings' => $this->getValidationWarnings(),
                'suggestions' => $this->when($this->suggestions, $this->suggestions),
                'is_valid' => $this->isValid(),
            ],

            // User information
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),
            'reviewed_by' => new UserResource($this->whenLoaded('reviewer')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),

            // Permissions
            'permissions' => [
                'view' => true,
                'edit' => $this->when(
                    $request->user()?->can('update', $this->resource),
                    true
                ),
                'delete' => $this->when(
                    $request->user()?->can('delete', $this->resource),
                    true
                ),
                'review' => $this->when(
                    $request->user()?->can('review', $this->resource),
                    true
                ),
                'publish' => $this->when(
                    $request->user()?->can('publish', $this->resource),
                    true
                ),
            ],

            // Links
            'links' => [
                'self' => route('api.translations.show', [
                    'locale' => $this->locale,
                    'group' => $this->group,
                    'key' => $this->key
                ]),
                'update' => route('api.translations.update'),
                'delete' => route('api.translations.destroy', ['id' => $this->id]),
                'history' => route('api.content.history', [
                    'content_type' => 'translation',
                    'content_id' => $this->id
                ]),
            ],
        ];
    }

    /**
     * Get language name in English.
     */
    private function getLanguageName(): string
    {
        $languages = config('cms.languages', []);
        return $languages[$this->locale]['name'] ?? $this->locale;
    }

    /**
     * Get language name in native script.
     */
    private function getNativeLanguageName(): string
    {
        $languages = config('cms.languages', []);
        return $languages[$this->locale]['native_name'] ?? $this->getLanguageName();
    }

    /**
     * Get text direction for the locale.
     */
    private function getTextDirection(): string
    {
        $rtlLanguages = ['ar', 'he', 'fa', 'ur', 'ps', 'sd'];
        return in_array($this->locale, $rtlLanguages) ? 'rtl' : 'ltr';
    }

    /**
     * Get script type for the locale.
     */
    private function getScript(): string
    {
        $scripts = [
            'ar' => 'Arabic',
            'he' => 'Hebrew',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'th' => 'Thai',
            'hi' => 'Devanagari',
            'ru' => 'Cyrillic',
            'el' => 'Greek',
        ];

        return $scripts[$this->locale] ?? 'Latin';
    }

    /**
     * Calculate complexity score for the translation.
     */
    private function calculateComplexityScore(): int
    {
        if (!$this->value) {
            return 0;
        }

        $score = 0;

        // Length factor
        $length = strlen($this->value);
        $score += min(floor($length / 50), 10);

        // Special characters
        $specialChars = preg_match_all('/[^\w\s]/u', $this->value);
        $score += min($specialChars, 5);

        // HTML tags
        $htmlTags = preg_match_all('/<[^>]+>/', $this->value);
        $score += $htmlTags * 2;

        // Placeholders
        $placeholders = $this->countPlaceholders();
        $score += $placeholders;

        return min($score, 20);
    }

    /**
     * Count placeholders in the translation.
     */
    private function countPlaceholders(): int
    {
        if (!$this->value) {
            return 0;
        }

        $patterns = [
            '/:\w+/',        // :placeholder
            '/{\w+}/',       // {placeholder}
            '/\[\w+\]/',     // [placeholder]
            '/%\w+%/',       // %placeholder%
            '/\$\w+/',       // $placeholder
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            $count += preg_match_all($pattern, $this->value);
        }

        return $count;
    }

    /**
     * Get comparison with source translation.
     */
    private function getSourceComparison(): ?array
    {
        if (!$this->source_locale) {
            return null;
        }

        $sourceTranslation = \Webook\LaravelCMS\Models\Translation::where([
            'locale' => $this->source_locale,
            'group' => $this->group,
            'key' => $this->key,
        ])->first();

        if (!$sourceTranslation) {
            return ['status' => 'source_missing'];
        }

        $sourceLength = strlen($sourceTranslation->value ?? '');
        $targetLength = strlen($this->value ?? '');

        return [
            'source_value' => $sourceTranslation->value,
            'source_updated_at' => $sourceTranslation->updated_at?->toISOString(),
            'length_difference' => $targetLength - $sourceLength,
            'length_ratio' => $sourceLength > 0 ? round($targetLength / $sourceLength, 2) : 0,
            'is_outdated' => $sourceTranslation->updated_at > $this->updated_at,
        ];
    }

    /**
     * Get validation warnings for the translation.
     */
    private function getValidationWarnings(): array
    {
        $warnings = [];

        if (!$this->value) {
            return ['Translation value is empty'];
        }

        // Check for untranslated content
        if ($this->source_locale && $this->value === $this->getSourceValue()) {
            $warnings[] = 'Translation appears to be identical to source';
        }

        // Check for placeholder mismatches
        if ($this->source_locale) {
            $sourcePlaceholders = $this->getSourcePlaceholders();
            $targetPlaceholders = $this->getTargetPlaceholders();

            $missing = array_diff($sourcePlaceholders, $targetPlaceholders);
            $extra = array_diff($targetPlaceholders, $sourcePlaceholders);

            if (!empty($missing)) {
                $warnings[] = 'Missing placeholders: ' . implode(', ', $missing);
            }

            if (!empty($extra)) {
                $warnings[] = 'Extra placeholders: ' . implode(', ', $extra);
            }
        }

        // Check for HTML tag mismatches
        if (strip_tags($this->value) !== $this->value) {
            $openTags = preg_match_all('/<[^\/][^>]*>/', $this->value);
            $closeTags = preg_match_all('/<\/[^>]*>/', $this->value);

            if ($openTags !== $closeTags) {
                $warnings[] = 'Mismatched HTML tags detected';
            }
        }

        // Check for length issues
        if ($this->source_locale) {
            $sourceLength = strlen($this->getSourceValue() ?? '');
            $targetLength = strlen($this->value);

            if ($sourceLength > 0) {
                $ratio = $targetLength / $sourceLength;

                if ($ratio > 2.5) {
                    $warnings[] = 'Translation is significantly longer than source';
                } elseif ($ratio < 0.4) {
                    $warnings[] = 'Translation is significantly shorter than source';
                }
            }
        }

        return $warnings;
    }

    /**
     * Check if translation is valid.
     */
    private function isValid(): bool
    {
        return empty($this->getValidationWarnings());
    }

    /**
     * Get source value for comparison.
     */
    private function getSourceValue(): ?string
    {
        if (!$this->source_locale) {
            return null;
        }

        $source = \Webook\LaravelCMS\Models\Translation::where([
            'locale' => $this->source_locale,
            'group' => $this->group,
            'key' => $this->key,
        ])->first();

        return $source?->value;
    }

    /**
     * Get placeholders from source translation.
     */
    private function getSourcePlaceholders(): array
    {
        $sourceValue = $this->getSourceValue();
        if (!$sourceValue) {
            return [];
        }

        preg_match_all('/:\w+|{\w+}|\[\w+\]/', $sourceValue, $matches);
        return $matches[0];
    }

    /**
     * Get placeholders from target translation.
     */
    private function getTargetPlaceholders(): array
    {
        if (!$this->value) {
            return [];
        }

        preg_match_all('/:\w+|{\w+}|\[\w+\]/', $this->value, $matches);
        return $matches[0];
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
                'supported_locales' => array_keys(config('cms.locales', [])),
            ],
        ];
    }
}