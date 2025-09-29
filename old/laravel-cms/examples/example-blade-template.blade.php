{{--
Example Blade Template demonstrating Laravel CMS integration
This template shows how to use CMS directives and components in your views
--}}

@extends('layouts.app')

@section('title', 'Example Post - ' . $post->title)

@section('meta')
    <meta name="description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <meta name="keywords" content="{{ implode(', ', $post->tags ?? []) }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $post->meta_title ?: $post->title }}">
    <meta property="og:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <meta property="og:image" content="{{ $post->featured_image_url }}">
    <meta property="og:url" content="{{ $post->url }}">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $post->meta_title ?: $post->title }}">
    <meta name="twitter:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <meta name="twitter:image" content="{{ $post->featured_image_url }}">
@endsection

@section('content')
<article class="max-w-4xl mx-auto px-4 py-8">
    {{-- Header Section with CMS Editing --}}
    <header class="mb-8">
        {{--
        CMS Model Directive: Makes this entire model editable
        This enables inline editing for all configured fields
        --}}
        @cmsModel($post)

        {{-- Post Title with CMS Editing --}}
        <h1 class="text-4xl font-bold mb-4 leading-tight">
            {{-- CMS Field Directive: Makes specific field editable --}}
            @cmsField($post, 'title')
                {{ $post->title }}
            @endCmsField
        </h1>

        {{-- Post Meta Information --}}
        <div class="flex items-center space-x-4 text-gray-600 mb-6">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                </svg>
                <time datetime="{{ $post->published_at->toISOString() }}">
                    {{ $post->published_at->format('F j, Y') }}
                </time>
            </div>

            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <span>{{ $post->reading_time }} min read</span>
            </div>

            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                </svg>
                <span>by {{ $post->author->name }}</span>
            </div>
        </div>

        {{-- Featured Image with Asset Library Integration --}}
        @if($post->featured_image)
            <div class="mb-8">
                {{-- CMS Asset Directive: Enables asset selection and editing --}}
                @cmsAsset($post, 'featured_image', [
                    'type' => 'image',
                    'alt_field' => 'featured_image_alt',
                    'crop' => true,
                    'sizes' => ['small', 'medium', 'large']
                ])
                    <img
                        src="{{ $post->featured_image_url }}"
                        alt="{{ $post->featured_image_alt ?? $post->title }}"
                        class="w-full h-64 object-cover rounded-lg shadow-lg"
                    >
                @endCmsAsset
            </div>
        @endif

        {{-- Post Excerpt with CMS Editing --}}
        @if($post->excerpt)
            <div class="text-xl text-gray-700 mb-8 leading-relaxed">
                @cmsField($post, 'excerpt', ['editor' => 'plain'])
                    {{ $post->excerpt }}
                @endCmsField
            </div>
        @endif
    </header>

    {{-- Main Content with Rich Text Editing --}}
    <main class="prose prose-lg max-w-none">
        @cmsField($post, 'content', [
            'editor' => 'rich',
            'toolbar' => ['bold', 'italic', 'heading', 'link', 'image', 'blockquote', 'list'],
            'placeholder' => 'Start writing your post content...'
        ])
            {!! $post->content !!}
        @endCmsField
    </main>

    {{-- Tags Section --}}
    @if($post->tags && count($post->tags) > 0)
        <div class="mt-8 pt-8 border-t border-gray-200">
            <h3 class="text-lg font-semibold mb-4">Tags</h3>
            <div class="flex flex-wrap gap-2">
                @cmsField($post, 'tags', ['editor' => 'tags'])
                    @foreach($post->tags as $tag)
                        <span class="inline-block bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full">
                            #{{ $tag }}
                        </span>
                    @endforeach
                @endCmsField
            </div>
        </div>
    @endif

    {{-- CMS Toolbar (only visible to editors) --}}
    @canEdit($post)
        <div class="fixed bottom-4 right-4 z-50">
            @cmsToolbar($post, [
                'buttons' => ['edit', 'save', 'cancel', 'history', 'preview'],
                'theme' => 'dark',
                'position' => 'bottom-right'
            ])
        </div>
    @endCanEdit

    {{-- CMS Asset Library Modal --}}
    @cmsAssetLibrary([
        'multiple' => false,
        'types' => ['image'],
        'upload' => true,
        'folders' => true
    ])

    {{-- Related Posts Section --}}
    @if($relatedPosts && $relatedPosts->count() > 0)
        <section class="mt-12 pt-8 border-t border-gray-200">
            <h2 class="text-2xl font-bold mb-6">Related Posts</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($relatedPosts as $relatedPost)
                    <article class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                        @if($relatedPost->featured_image)
                            <img
                                src="{{ $relatedPost->featured_image_url }}"
                                alt="{{ $relatedPost->title }}"
                                class="w-full h-32 object-cover"
                            >
                        @endif
                        <div class="p-4">
                            <h3 class="font-semibold mb-2">
                                <a href="{{ $relatedPost->url }}" class="text-gray-900 hover:text-blue-600">
                                    {{ $relatedPost->title }}
                                </a>
                            </h3>
                            @if($relatedPost->excerpt)
                                <p class="text-gray-600 text-sm">
                                    {{ Str::limit($relatedPost->excerpt, 100) }}
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</article>

{{-- CMS Editor Styles and Scripts --}}
@cmsAssets
@endsection

{{-- JavaScript for CMS Integration --}}
@push('scripts')
<script>
    // Initialize CMS editing for this page
    window.addEventListener('DOMContentLoaded', function() {
        // Auto-save functionality
        if (window.CMS && window.CMS.Editor) {
            window.CMS.Editor.initialize({
                model: 'ExamplePost',
                id: {{ $post->id }},
                autoSave: true,
                autoSaveInterval: 30000, // 30 seconds
                onSave: function(data) {
                    console.log('Post auto-saved:', data);
                },
                onError: function(error) {
                    console.error('CMS Error:', error);
                    // Show user-friendly error message
                    alert('Failed to save changes. Please try again.');
                }
            });
        }

        // Asset library integration
        if (window.CMS && window.CMS.AssetLibrary) {
            window.CMS.AssetLibrary.initialize({
                uploadUrl: '{{ route("cms.assets.upload") }}',
                browseUrl: '{{ route("cms.assets.browse") }}',
                onAssetSelected: function(asset) {
                    console.log('Asset selected:', asset);
                },
                onUploadComplete: function(asset) {
                    console.log('Asset uploaded:', asset);
                }
            });
        }
    });
</script>
@endpush

{{-- Styles for CMS Integration --}}
@push('styles')
<style>
    /* Custom styles for CMS editing interface */
    .cms-field-editing {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
        border-radius: 4px;
    }

    .cms-field-hover {
        background-color: rgba(59, 130, 246, 0.1);
        cursor: pointer;
    }

    .cms-toolbar {
        background: rgba(0, 0, 0, 0.8);
        border-radius: 8px;
        padding: 8px;
        backdrop-filter: blur(8px);
    }

    .cms-toolbar button {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        margin: 0 2px;
        transition: all 0.2s;
    }

    .cms-toolbar button:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.3);
    }
</style>
@endpush