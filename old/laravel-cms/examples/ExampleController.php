<?php

namespace App\Http\Controllers;

use App\Models\ExamplePost;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Example Controller demonstrating Laravel CMS integration
 *
 * This controller shows how to work with CMS-enabled models
 * and integrate with the CMS editing interface.
 */
class ExampleController extends Controller
{
    /**
     * Display a listing of posts with CMS editing capabilities
     */
    public function index(Request $request): View
    {
        $posts = ExamplePost::published()
            ->with('author')
            ->latest('published_at')
            ->paginate(10);

        return view('posts.index', compact('posts'));
    }

    /**
     * Show a single post with CMS editing capabilities
     */
    public function show(ExamplePost $post): View
    {
        // Check if user can edit this post
        $canEdit = auth()->check() && auth()->user()->can('edit-posts');

        // Get related posts
        $relatedPosts = ExamplePost::published()
            ->where('id', '!=', $post->id)
            ->whereJsonContains('tags', $post->tags)
            ->limit(3)
            ->get();

        return view('posts.show', compact('post', 'canEdit', 'relatedPosts'));
    }

    /**
     * Show the post creation form
     */
    public function create(): View
    {
        $this->authorize('create', ExamplePost::class);

        return view('posts.create');
    }

    /**
     * Store a new post
     */
    public function store(Request $request)
    {
        $this->authorize('create', ExamplePost::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'featured_image' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ]);

        $validated['author_id'] = auth()->id();
        $validated['slug'] = \Str::slug($validated['title']);

        $post = ExamplePost::create($validated);

        return redirect()->route('posts.show', $post)
            ->with('success', 'Post created successfully!');
    }

    /**
     * Show the post editing form
     */
    public function edit(ExamplePost $post): View
    {
        $this->authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    /**
     * Update a post
     */
    public function update(Request $request, ExamplePost $post)
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'featured_image' => 'nullable|string',
            'tags' => 'nullable|array',
            'status' => 'required|in:draft,published',
            'published_at' => 'nullable|date',
        ]);

        $post->update($validated);

        return redirect()->route('posts.show', $post)
            ->with('success', 'Post updated successfully!');
    }

    /**
     * Delete a post
     */
    public function destroy(ExamplePost $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('posts.index')
            ->with('success', 'Post deleted successfully!');
    }

    /**
     * Example of inline editing endpoint
     * This demonstrates how to handle CMS inline edits
     */
    public function inlineUpdate(Request $request, ExamplePost $post)
    {
        $this->authorize('update', $post);

        $field = $request->input('field');
        $value = $request->input('value');

        // Validate the field is editable
        if (!in_array($field, ['title', 'content', 'excerpt', 'meta_title', 'meta_description'])) {
            return response()->json(['error' => 'Field not editable'], 400);
        }

        // Validate the value based on field type
        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
        ];

        $validator = \Validator::make([$field => $value], [$field => $rules[$field]]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update the field
        $post->update([$field => $value]);

        // Log the change for audit trail
        \Log::info('Post field updated via CMS', [
            'post_id' => $post->id,
            'field' => $field,
            'old_value' => $post->getOriginal($field),
            'new_value' => $value,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Field updated successfully',
            'data' => [
                'field' => $field,
                'value' => $value,
                'formatted_value' => $this->formatFieldValue($field, $value),
            ]
        ]);
    }

    /**
     * Format field values for display
     */
    private function formatFieldValue(string $field, $value): string
    {
        switch ($field) {
            case 'content':
                return \Str::limit(strip_tags($value), 100);
            case 'excerpt':
                return \Str::limit($value, 50);
            case 'meta_title':
            case 'meta_description':
                return \Str::limit($value, 50);
            default:
                return (string) $value;
        }
    }

    /**
     * Get post history for versioning
     */
    public function history(ExamplePost $post)
    {
        $this->authorize('view', $post);

        // This would integrate with the CMS versioning system
        $versions = $post->cmsVersions()
            ->with('user')
            ->latest('created_at')
            ->paginate(20);

        return response()->json($versions);
    }

    /**
     * Restore a specific version
     */
    public function restore(ExamplePost $post, $versionId)
    {
        $this->authorize('update', $post);

        $version = $post->cmsVersions()->findOrFail($versionId);
        $post->restoreFromVersion($version);

        return response()->json([
            'success' => true,
            'message' => 'Post restored to previous version',
        ]);
    }

    /**
     * Preview a post (useful for draft posts)
     */
    public function preview(ExamplePost $post)
    {
        $this->authorize('view', $post);

        return view('posts.preview', compact('post'));
    }

    /**
     * Bulk operations on posts
     */
    public function bulk(Request $request)
    {
        $this->authorize('edit-posts');

        $action = $request->input('action');
        $postIds = $request->input('post_ids', []);

        $posts = ExamplePost::whereIn('id', $postIds)->get();

        switch ($action) {
            case 'publish':
                foreach ($posts as $post) {
                    if (auth()->user()->can('publish-posts')) {
                        $post->update([
                            'status' => 'published',
                            'published_at' => now()
                        ]);
                    }
                }
                break;

            case 'draft':
                foreach ($posts as $post) {
                    if (auth()->user()->can('update', $post)) {
                        $post->update(['status' => 'draft']);
                    }
                }
                break;

            case 'delete':
                foreach ($posts as $post) {
                    if (auth()->user()->can('delete', $post)) {
                        $post->delete();
                    }
                }
                break;
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk {$action} completed successfully",
        ]);
    }
}