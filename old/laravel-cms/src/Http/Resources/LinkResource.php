<?php

namespace Webook\LaravelCMS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Link Resource
 *
 * API resource for link content with validation status and metadata.
 */
class LinkResource extends JsonResource
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
            'identifier' => $this->identifier,
            'url' => $this->url,
            'text' => $this->text,
            'title' => $this->when($this->title, $this->title),

            // Link attributes
            'target' => $this->target ?? '_self',
            'rel' => $this->when($this->rel, $this->rel),
            'type' => $this->when($this->type, $this->type),

            // Status and validation
            'is_active' => (bool) ($this->is_active ?? true),
            'is_valid' => $this->when(isset($this->is_valid), (bool) $this->is_valid),
            'validation' => $this->when($this->validation_data, [
                'status' => $this->validation_data['status'] ?? 'unknown',
                'status_code' => $this->validation_data['status_code'] ?? null,
                'response_time' => $this->validation_data['response_time'] ?? null,
                'last_checked' => $this->last_validated_at?->toISOString(),
                'redirect_url' => $this->validation_data['redirect_url'] ?? null,
                'error_message' => $this->validation_data['error_message'] ?? null,
            ]),

            // Categorization
            'category' => $this->when($this->category, $this->category),
            'tags' => $this->when($this->tags, $this->tags),

            // Analytics and usage
            'analytics' => $this->when($this->analytics, [
                'clicks' => $this->analytics['clicks'] ?? 0,
                'unique_clicks' => $this->analytics['unique_clicks'] ?? 0,
                'last_clicked' => $this->analytics['last_clicked'] ?? null,
                'usage_count' => $this->analytics['usage_count'] ?? 0,
            ]),

            // Metadata
            'metadata' => $this->when($this->metadata, $this->metadata),

            // URL analysis
            'url_analysis' => [
                'domain' => $this->getDomain(),
                'is_external' => $this->isExternal(),
                'is_secure' => $this->isSecure(),
                'protocol' => $this->getProtocol(),
                'path_depth' => $this->getPathDepth(),
            ],

            // SEO information
            'seo' => $this->when($this->seo_data, [
                'page_title' => $this->seo_data['title'] ?? null,
                'meta_description' => $this->seo_data['description'] ?? null,
                'open_graph' => $this->seo_data['og'] ?? null,
                'twitter_card' => $this->seo_data['twitter'] ?? null,
            ]),

            // User information
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'last_validated_at' => $this->last_validated_at?->toISOString(),

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
                'validate' => $this->when(
                    $request->user()?->can('validate', $this->resource),
                    true
                ),
            ],

            // Links
            'links' => [
                'self' => route('api.links.show', ['id' => $this->id]),
                'update' => route('api.links.update'),
                'delete' => route('api.links.destroy', ['id' => $this->id]),
                'validate' => route('api.links.validate'),
                'visit' => $this->url,
            ],

            // Security warnings
            'security' => [
                'warnings' => $this->getSecurityWarnings(),
                'risk_level' => $this->calculateRiskLevel(),
            ],
        ];
    }

    /**
     * Get domain from URL.
     */
    private function getDomain(): ?string
    {
        $parsed = parse_url($this->url);
        return $parsed['host'] ?? null;
    }

    /**
     * Check if link is external.
     */
    private function isExternal(): bool
    {
        $domain = $this->getDomain();
        if (!$domain) {
            return false;
        }

        $currentDomain = parse_url(config('app.url'), PHP_URL_HOST);
        return $domain !== $currentDomain;
    }

    /**
     * Check if link uses secure protocol.
     */
    private function isSecure(): bool
    {
        return str_starts_with($this->url, 'https://');
    }

    /**
     * Get URL protocol.
     */
    private function getProtocol(): string
    {
        $parsed = parse_url($this->url);
        return $parsed['scheme'] ?? 'unknown';
    }

    /**
     * Get URL path depth.
     */
    private function getPathDepth(): int
    {
        $parsed = parse_url($this->url);
        $path = $parsed['path'] ?? '/';

        return substr_count(trim($path, '/'), '/') + (strlen(trim($path, '/')) > 0 ? 1 : 0);
    }

    /**
     * Get security warnings for the link.
     */
    private function getSecurityWarnings(): array
    {
        $warnings = [];

        // Check for insecure protocol
        if (!$this->isSecure() && $this->isExternal()) {
            $warnings[] = 'External link uses insecure HTTP protocol';
        }

        // Check for missing security attributes
        if ($this->target === '_blank' && !str_contains($this->rel ?? '', 'noopener')) {
            $warnings[] = 'Target="_blank" without rel="noopener" security attribute';
        }

        // Check for suspicious domains
        $suspiciousDomains = config('cms.links.suspicious_domains', []);
        $domain = $this->getDomain();

        if ($domain) {
            foreach ($suspiciousDomains as $suspicious) {
                if (str_contains($domain, $suspicious)) {
                    $warnings[] = 'Link points to potentially suspicious domain';
                    break;
                }
            }
        }

        // Check for URL shorteners
        $shorteners = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl'];
        if ($domain) {
            foreach ($shorteners as $shortener) {
                if (str_contains($domain, $shortener)) {
                    $warnings[] = 'Link uses URL shortener service';
                    break;
                }
            }
        }

        return $warnings;
    }

    /**
     * Calculate risk level based on various factors.
     */
    private function calculateRiskLevel(): string
    {
        $score = 0;
        $warnings = $this->getSecurityWarnings();

        // Add points for each warning
        $score += count($warnings);

        // Add points for external links
        if ($this->isExternal()) {
            $score += 1;
        }

        // Add points for validation failures
        if (isset($this->is_valid) && !$this->is_valid) {
            $score += 2;
        }

        // Determine risk level
        if ($score >= 4) {
            return 'high';
        } elseif ($score >= 2) {
            return 'medium';
        } else {
            return 'low';
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
                'validation_enabled' => config('cms.links.auto_validation', true),
            ],
        ];
    }
}