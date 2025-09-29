<?php

namespace Webook\LaravelCMS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Resource
 *
 * API resource for user information with privacy controls.
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $currentUser = $request->user();
        $isOwnProfile = $currentUser && $currentUser->id === $this->id;
        $canViewDetails = $isOwnProfile || $currentUser?->can('view-user-details');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($canViewDetails, $this->email),
            'avatar' => $this->avatar_url ?? $this->getGravatarUrl(),

            // Public profile information
            'display_name' => $this->display_name ?? $this->name,
            'bio' => $this->when($this->bio, $this->bio),
            'location' => $this->when($this->location, $this->location),
            'website' => $this->when($this->website, $this->website),

            // Role and permissions (if viewable)
            'role' => $this->when($canViewDetails, $this->role),
            'permissions' => $this->when($canViewDetails, function () {
                return $this->getAllPermissions()->pluck('name');
            }),

            // Activity information
            'activity' => [
                'last_seen' => $this->last_seen_at?->toISOString(),
                'is_online' => $this->isOnline(),
                'status' => $this->status ?? 'active',
            ],

            // CMS-specific information
            'cms_stats' => $this->when($canViewDetails, [
                'content_created' => $this->content_created_count ?? 0,
                'content_updated' => $this->content_updated_count ?? 0,
                'translations_made' => $this->translations_count ?? 0,
                'images_uploaded' => $this->images_uploaded_count ?? 0,
            ]),

            // Preferences (own profile only)
            'preferences' => $this->when($isOwnProfile, [
                'locale' => $this->preferred_locale ?? 'en',
                'timezone' => $this->timezone ?? 'UTC',
                'theme' => $this->preferred_theme ?? 'light',
                'notifications' => $this->notification_settings ?? [],
            ]),

            // Timestamps
            'joined_at' => $this->created_at?->toISOString(),
            'profile_updated_at' => $this->updated_at?->toISOString(),

            // Links (if applicable)
            'links' => $this->when($canViewDetails, [
                'profile' => route('users.show', ['id' => $this->id]),
                'edit' => $this->when($isOwnProfile, route('users.edit', ['id' => $this->id])),
            ]),
        ];
    }

    /**
     * Check if user is currently online.
     */
    private function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->diffInMinutes(now()) <= 5;
    }

    /**
     * Get Gravatar URL for user.
     */
    private function getGravatarUrl(): string
    {
        $hash = md5(strtolower(trim($this->email ?? '')));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
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
            ],
        ];
    }
}