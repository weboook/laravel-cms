<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Webook\LaravelCMS\Traits\CMSEditable;

/**
 * Example Post model demonstrating Laravel CMS integration
 *
 * This model shows how to make your content editable through the CMS
 * by using the CMSEditable trait and proper configuration.
 */
class ExamplePost extends Model
{
    use HasFactory, SoftDeletes, CMSEditable;

    protected $table = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'featured_image',
        'author_id',
        'status',
        'published_at',
        'tags',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'tags' => 'array',
    ];

    protected $dates = [
        'published_at',
        'deleted_at',
    ];

    /**
     * Define which fields are editable through the CMS
     * This configuration works with config/cms-database.php
     */
    protected $cmsEditableFields = [
        'title' => [
            'type' => 'text',
            'label' => 'Post Title',
            'required' => true,
            'max_length' => 255,
            'editor' => 'plain',
            'validation' => 'required|string|max:255',
        ],
        'content' => [
            'type' => 'longtext',
            'label' => 'Post Content',
            'required' => false,
            'editor' => 'rich',
            'validation' => 'nullable|string',
            'toolbar' => [
                'bold', 'italic', 'underline', 'heading', 'paragraph',
                'bulletList', 'orderedList', 'link', 'image', 'blockquote'
            ],
        ],
        'excerpt' => [
            'type' => 'text',
            'label' => 'Post Excerpt',
            'required' => false,
            'max_length' => 500,
            'editor' => 'plain',
            'validation' => 'nullable|string|max:500',
            'auto_generate' => true,
            'auto_generate_length' => 155,
        ],
        'meta_title' => [
            'type' => 'text',
            'label' => 'SEO Title',
            'required' => false,
            'max_length' => 60,
            'editor' => 'plain',
            'validation' => 'nullable|string|max:60',
            'help_text' => 'Optimal length: 50-60 characters',
        ],
        'meta_description' => [
            'type' => 'text',
            'label' => 'SEO Description',
            'required' => false,
            'max_length' => 160,
            'editor' => 'plain',
            'validation' => 'nullable|string|max:160',
            'help_text' => 'Optimal length: 120-160 characters',
        ],
        'tags' => [
            'type' => 'tags',
            'label' => 'Tags',
            'required' => false,
            'editor' => 'tags',
            'validation' => 'nullable|array',
        ],
    ];

    /**
     * Define CMS permissions for this model
     */
    protected $cmsPermissions = [
        'edit' => 'edit-posts',
        'publish' => 'publish-posts',
        'delete' => 'delete-posts',
    ];

    /**
     * Define workflow settings for this model
     */
    protected $cmsWorkflow = [
        'requires_approval' => false,
        'auto_publish' => true,
        'versioning' => true,
        'status_field' => 'status',
        'published_at_field' => 'published_at',
    ];

    /**
     * Get the author of the post
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get comments for the post
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * Scope for published posts
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope for draft posts
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Get the URL for the post
     */
    public function getUrlAttribute()
    {
        return route('posts.show', $this->slug);
    }

    /**
     * Get the reading time estimate
     */
    public function getReadingTimeAttribute()
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $minutes = ceil($wordCount / 200); // Average reading speed
        return $minutes;
    }

    /**
     * Get the featured image URL
     */
    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }
        return null;
    }

    /**
     * Automatically generate slug from title
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = \Str::slug($post->title);
            }
        });

        static::updating(function ($post) {
            if ($post->isDirty('title') && empty($post->getOriginal('slug'))) {
                $post->slug = \Str::slug($post->title);
            }
        });
    }
}