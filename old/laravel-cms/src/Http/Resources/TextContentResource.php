<?php

namespace Webook\LaravelCMS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Text Content Resource
 *
 * API resource for text content with conditional field inclusion.
 */
class TextContentResource extends JsonResource
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
            'key' => $this->key,
            'value' => $this->value,
            'locale' => $this->locale,
            'type' => 'text',
            'metadata' => $this->when($this->metadata, $this->metadata),
            'status' => $this->when(isset($this->status), $this->status ?? 'active'),
            'file_path' => $this->when($this->file_path, $this->file_path),
            'line_number' => $this->when($this->line_number, $this->line_number),
            'selector' => $this->when($this->selector, $this->selector),

            // Statistics
            'stats' => [
                'length' => strlen($this->value ?? ''),
                'words' => str_word_count(strip_tags($this->value ?? '')),
                'lines' => substr_count($this->value ?? '', "\n") + 1,
            ],

            // Relationships
            'translations' => TranslationResource::collection($this->whenLoaded('translations')),
            'history' => HistoryResource::collection($this->whenLoaded('history')),

            // User information
            'created_by' => new UserResource($this->whenLoaded('creator')),
            'updated_by' => new UserResource($this->whenLoaded('updater')),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Conditional fields based on permissions
            'editable' => $this->when(
                $request->user()?->can('edit', $this->resource),
                true
            ),
            'deletable' => $this->when(
                $request->user()?->can('delete', $this->resource),
                true
            ),

            // Links
            'links' => [
                'self' => route('api.content.text.show', ['key' => $this->key]),
                'update' => route('api.content.text.update'),
                'history' => route('api.content.history', [
                    'content_type' => 'text',
                    'content_id' => $this->id
                ]),
            ],
        ];
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