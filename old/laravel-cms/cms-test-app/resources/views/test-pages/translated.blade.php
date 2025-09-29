@extends('layouts.app')

@section('title', __('test.page_titles.translated'))

@section('meta')
    <meta name="description" content="{{ $metadata['meta_description'] }}">
    <meta name="keywords" content="{{ $metadata['meta_keywords'] }}">
    <meta name="author" content="CMS Translation Test">
    <link rel="alternate" hreflang="en" href="{{ route('test.translated', ['locale' => 'en']) }}">
    <link rel="alternate" hreflang="es" href="{{ route('test.translated', ['locale' => 'es']) }}">
    <link rel="alternate" hreflang="fr" href="{{ route('test.translated', ['locale' => 'fr']) }}">
@endsection

@section('content')
<div class="container mx-auto px-4 py-8">

    <!-- Language Switcher -->
    <div class="mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex flex-wrap items-center justify-between">
                <div class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7 2a1 1 0 011 1v1h3a1 1 0 110 2H9.578a18.87 18.87 0 01-1.724 4.78c.29.354.596.696.914 1.026a1 1 0 11-1.44 1.389c-.188-.196-.373-.396-.554-.6a19.098 19.098 0 01-3.107 3.567 1 1 0 01-1.334-1.49 17.087 17.087 0 003.13-3.733 18.992 18.992 0 01-1.487-2.494 1 1 0 111.79-.89c.234.47.489.928.764 1.372.417-.934.752-1.913.997-2.927H3a1 1 0 110-2h3V3a1 1 0 011-1zm6 6a1 1 0 01.894.553l2.991 5.982a.869.869 0 01.02.037l.99 1.98a1 1 0 11-1.79.895L15.383 16h-4.764l-.724 1.447a1 1 0 11-1.788-.894l.99-1.98.019-.038 2.99-5.982A1 1 0 0113 8zm-1.382 6h2.764L13 11.236 11.618 14z" clip-rule="evenodd"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-blue-800">@lang('test.language_selector.title')</h3>
                </div>
                <div class="flex space-x-2 mt-2 sm:mt-0">
                    @foreach($available_locales as $locale)
                        <a href="{{ route('test.translated', ['locale' => $locale]) }}"
                           class="px-3 py-1 rounded-md text-sm font-medium transition-colors
                                  {{ $current_locale === $locale
                                     ? 'bg-blue-600 text-white'
                                     : 'bg-white text-blue-600 border border-blue-300 hover:bg-blue-50' }}">
                            {{ $locale_names[$locale] ?? strtoupper($locale) }}
                            @if($current_locale === $locale)
                                <span class="ml-1">✓</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Current Locale Information -->
    <div class="mb-8 bg-gray-50 p-6 rounded-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">
            @lang('test.current_locale.title')
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="font-semibold text-gray-700">@lang('test.current_locale.active'):</span>
                <span class="text-blue-600 font-mono">{{ $current_locale }}</span>
            </div>
            <div>
                <span class="font-semibold text-gray-700">@lang('test.current_locale.name'):</span>
                <span class="text-gray-900">{{ $locale_names[$current_locale] }}</span>
            </div>
            <div>
                <span class="font-semibold text-gray-700">@lang('test.current_locale.direction'):</span>
                <span class="text-gray-900">@lang('test.current_locale.ltr')</span>
            </div>
        </div>
    </div>

    <!-- Page Header -->
    <header class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
            @lang('test.page_header.title')
        </h1>
        <p class="text-xl text-gray-600 mb-6">
            @lang('test.page_header.subtitle')
        </p>
        <div class="max-w-3xl mx-auto">
            <p class="text-lg text-gray-700 leading-relaxed">
                @lang('test.page_header.description')
            </p>
        </div>
    </header>

    <!-- Translation Examples -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.examples.title')
        </h2>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Simple Translations -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    @lang('test.examples.simple.title')
                </h3>
                <div class="space-y-3">
                    @foreach($test_translations as $key => $translation)
                        <div class="border-l-4 border-blue-400 pl-4">
                            <div class="text-sm text-gray-500 font-mono">{{ $key }}</div>
                            <div class="text-gray-900">{{ $translation }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Helper Functions -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    @lang('test.examples.helpers.title')
                </h3>
                <div class="space-y-4">
                    <div>
                        <h4 class="font-medium text-gray-800">@lang Helper:</h4>
                        <code class="text-sm bg-gray-100 p-2 rounded block">@lang('test.helpers.welcome')</code>
                        <p class="text-gray-700 mt-1">@lang('test.helpers.welcome')</p>
                    </div>

                    <div>
                        <h4 class="font-medium text-gray-800">__() Helper:</h4>
                        <code class="text-sm bg-gray-100 p-2 rounded block">{{ '__("test.helpers.goodbye")' }}</code>
                        <p class="text-gray-700 mt-1">{{ __('test.helpers.goodbye') }}</p>
                    </div>

                    <div>
                        <h4 class="font-medium text-gray-800">trans_choice Helper:</h4>
                        <code class="text-sm bg-gray-100 p-2 rounded block">trans_choice('test.plurals.items', 5)</code>
                        <p class="text-gray-700 mt-1">{{ trans_choice('test.plurals.items', 5, ['count' => 5]) }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Sections with Translations -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.content_sections.title')
        </h2>

        <div class="space-y-6">
            @foreach($content_sections as $index => $section)
                <article class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">
                        @lang($section['title_key'])
                    </h3>
                    <div class="prose max-w-none text-gray-700">
                        @lang($section['content_key'])
                    </div>

                    @if($index === 0)
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded">
                            <h4 class="font-medium text-blue-800 mb-2">@lang('test.translation_info.title')</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>@lang('test.translation_info.key_format')</li>
                                <li>@lang('test.translation_info.file_location')</li>
                                <li>@lang('test.translation_info.editing_note')</li>
                            </ul>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <!-- Navigation with Translations -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.navigation.title')
        </h2>

        <nav class="bg-white p-6 rounded-lg shadow-md">
            <ul class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach($navigation_items as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                           class="block p-4 text-center bg-gray-50 hover:bg-blue-50 rounded-lg transition-colors group">
                            <span class="text-lg font-medium text-gray-900 group-hover:text-blue-600">
                                @lang($item['key'])
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </section>

    <!-- Nested Translation Examples -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.nested.title')
        </h2>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        @lang('test.nested.examples.title')
                    </h3>
                    <div class="space-y-2">
                        <div class="text-sm">
                            <span class="font-mono text-gray-500">test.nested.level1.value:</span>
                            <span class="ml-2 text-gray-900">@lang('test.nested.level1.value')</span>
                        </div>
                        <div class="text-sm">
                            <span class="font-mono text-gray-500">test.nested.level1.level2.value:</span>
                            <span class="ml-2 text-gray-900">@lang('test.nested.level1.level2.value')</span>
                        </div>
                        <div class="text-sm">
                            <span class="font-mono text-gray-500">test.nested.level1.level2.level3.deep:</span>
                            <span class="ml-2 text-gray-900">@lang('test.nested.level1.level2.level3.deep')</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        @lang('test.nested.structure.title')
                    </h3>
                    <pre class="text-xs bg-gray-100 p-3 rounded overflow-x-auto"><code>test.php
├── nested
│   ├── level1
│   │   ├── value
│   │   └── level2
│   │       ├── value
│   │       └── level3
│   │           └── deep</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Parameterized Translations -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.parameters.title')
        </h2>

        <div class="space-y-4">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">
                    @lang('test.parameters.examples.title')
                </h3>

                <div class="space-y-4">
                    <div class="border-l-4 border-green-400 pl-4">
                        <div class="text-sm font-mono text-gray-500 mb-1">
                            __('test.parameters.welcome_user', ['name' => 'John Doe'])
                        </div>
                        <div class="text-gray-900">
                            {{ __('test.parameters.welcome_user', ['name' => 'John Doe']) }}
                        </div>
                    </div>

                    <div class="border-l-4 border-blue-400 pl-4">
                        <div class="text-sm font-mono text-gray-500 mb-1">
                            __('test.parameters.items_found', ['count' => 42, 'type' => 'products'])
                        </div>
                        <div class="text-gray-900">
                            {{ __('test.parameters.items_found', ['count' => 42, 'type' => 'products']) }}
                        </div>
                    </div>

                    <div class="border-l-4 border-purple-400 pl-4">
                        <div class="text-sm font-mono text-gray-500 mb-1">
                            __('test.parameters.last_updated', ['date' => now()->format('M j, Y'), 'time' => now()->format('g:i A')])
                        </div>
                        <div class="text-gray-900">
                            {{ __('test.parameters.last_updated', ['date' => now()->format('M j, Y'), 'time' => now()->format('g:i A')]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pluralization Examples -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.pluralization.title')
        </h2>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        @lang('test.pluralization.examples.title')
                    </h3>
                    <div class="space-y-2">
                        @foreach([0, 1, 2, 5, 10] as $count)
                            <div class="text-sm">
                                <span class="font-mono text-gray-500">{{ $count }} items:</span>
                                <span class="ml-2 text-gray-900">{{ trans_choice('test.plurals.items', $count, ['count' => $count]) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">
                        @lang('test.pluralization.syntax.title')
                    </h3>
                    <pre class="text-xs bg-gray-100 p-3 rounded overflow-x-auto"><code>'items' => '{0} No items|{1} One item|[2,*] :count items'</code></pre>
                    <p class="text-sm text-gray-600 mt-2">
                        @lang('test.pluralization.syntax.description')
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Translation File Information -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            @lang('test.file_info.title')
        </h2>

        <div class="bg-yellow-50 border border-yellow-200 p-6 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800 mb-3">
                        @lang('test.file_info.location.title')
                    </h3>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li><code>resources/lang/{{ $current_locale }}/test.php</code></li>
                        <li><code>resources/lang/{{ $current_locale }}/messages.php</code></li>
                        <li><code>resources/lang/{{ $current_locale }}/validation.php</code></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-yellow-800 mb-3">
                        @lang('test.file_info.cms_features.title')
                    </h3>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>@lang('test.file_info.cms_features.inline_editing')</li>
                        <li>@lang('test.file_info.cms_features.bulk_import')</li>
                        <li>@lang('test.file_info.cms_features.export_formats')</li>
                        <li>@lang('test.file_info.cms_features.auto_translate')</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center pt-8 border-t border-gray-200">
        <p class="text-gray-600 mb-2">
            @lang('test.footer.generated_on', ['date' => now()->format('F j, Y'), 'time' => now()->format('g:i A')])
        </p>
        <p class="text-sm text-gray-500">
            @lang('test.footer.locale_note', ['locale' => $current_locale])
        </p>
    </footer>

</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Log translation key usage for debugging
    const translationKeys = [
        @foreach($test_translations as $key => $value)
            '{{ $key }}',
        @endforeach
    ];

    console.log('Translation Test Page Loaded', {
        locale: '{{ $current_locale }}',
        availableLocales: @json($available_locales),
        translationKeys: translationKeys,
        timestamp: new Date().toISOString()
    });

    // Add click handlers for language switcher
    const languageLinks = document.querySelectorAll('a[href*="locale="]');
    languageLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const locale = new URL(this.href).searchParams.get('locale');
            console.log('Language switch clicked:', {
                from: '{{ $current_locale }}',
                to: locale,
                url: this.href
            });
        });
    });

    // Highlight translation examples on hover
    const translationExamples = document.querySelectorAll('.border-l-4');
    translationExamples.forEach(example => {
        example.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f3f4f6';
        });

        example.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
        });
    });

    // Test translation function availability
    if (window.trans || window.__) {
        console.log('Translation functions available in browser');
    } else {
        console.log('No client-side translation functions detected');
    }
});
</script>
@endsection