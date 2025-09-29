@extends('layouts.app')

@section('title', $title)

@section('meta')
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="cms, testing, simple, content, laravel">
    <meta name="author" content="CMS Test Suite">
@endsection

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <header class="page-header mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">{{ $heading }}</h1>
        <p class="text-xl text-gray-600 mb-4">{{ $subtitle }}</p>
        <p class="text-lg text-gray-700">{{ $intro_text }}</p>
    </header>

    <!-- Test Mode Indicator -->
    @if($test_mode)
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
            <div class="flex">
                <div class="py-1">
                    <svg class="fill-current h-6 w-6 text-blue-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold">Test Mode Active</p>
                    <p class="text-sm">This page is running in test mode for CMS functionality testing.</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Main Content Area -->
    <main class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Primary Content -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Features List -->
            <section class="features-section">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">CMS Testing Features</h2>
                <p class="text-gray-700 mb-4">This page tests the following CMS features:</p>

                <ul class="list-disc list-inside space-y-2 text-gray-700">
                    @foreach($features as $feature)
                        <li class="hover:text-blue-600 transition-colors">{{ $feature }}</li>
                    @endforeach
                </ul>
            </section>

            <!-- Content Sections -->
            @foreach($sections as $index => $section)
                <section class="content-section bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $section['title'] }}</h3>
                    <p class="text-gray-700 leading-relaxed">{{ $section['content'] }}</p>

                    @if($index === 0)
                        <div class="mt-4 p-4 bg-white border-l-4 border-green-400 rounded">
                            <p class="text-sm text-gray-600">
                                <strong>Editing Tip:</strong> This content can be modified using the CMS editor interface.
                                Look for editable regions marked with dotted borders in edit mode.
                            </p>
                        </div>
                    @endif
                </section>
            @endforeach

            <!-- Text Formatting Examples -->
            <section class="formatting-examples">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">Text Formatting Examples</h2>

                <div class="prose max-w-none">
                    <p class="mb-4">
                        This paragraph contains <strong>bold text</strong>, <em>italic text</em>,
                        and <code class="bg-gray-100 px-2 py-1 rounded">inline code</code>.
                        It also includes a <a href="#test-link" class="text-blue-600 hover:text-blue-800 underline">test link</a>
                        for link editing functionality.
                    </p>

                    <blockquote class="border-l-4 border-gray-400 pl-4 italic text-gray-600 my-6">
                        "This is a blockquote that can be edited through the CMS interface.
                        It demonstrates how quoted content appears and can be modified."
                    </blockquote>

                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Ordered List Example:</h4>
                    <ol class="list-decimal list-inside space-y-1 mb-4">
                        <li>First ordered item</li>
                        <li>Second ordered item with <strong>formatting</strong></li>
                        <li>Third item with a <a href="#" class="text-blue-600 underline">link</a></li>
                    </ol>

                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Unordered List Example:</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>First bullet point</li>
                        <li>Second point with <em>emphasis</em></li>
                        <li>Third point for testing purposes</li>
                    </ul>
                </div>
            </section>

            <!-- Code Block Example -->
            <section class="code-example">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">Code Example</h2>
                <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto"><code>&lt;?php

// This is a code example for testing
echo "Hello, CMS World!";

$content = "This content can be edited";
$features = ['editing', 'translation', 'management'];

foreach ($features as $feature) {
    echo "Feature: " . $feature . "\n";
}

?&gt;</code></pre>
            </section>
        </div>

        <!-- Sidebar -->
        <aside class="lg:col-span-1 space-y-6">

            <!-- Quick Navigation -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Navigation</h3>
                <nav class="space-y-2">
                    <a href="{{ route('test.simple') }}" class="block text-blue-600 hover:text-blue-800 font-medium">Simple Page</a>
                    <a href="{{ route('test.translated') }}" class="block text-blue-600 hover:text-blue-800">Translated Content</a>
                    @auth
                        <a href="{{ route('test.complex') }}" class="block text-blue-600 hover:text-blue-800">Complex Content</a>
                        <a href="{{ route('test.components') }}" class="block text-blue-600 hover:text-blue-800">Components Demo</a>
                        <a href="{{ route('test.dynamic') }}" class="block text-blue-600 hover:text-blue-800">Dynamic Content</a>
                        <a href="{{ route('test.forms') }}" class="block text-blue-600 hover:text-blue-800">Forms Testing</a>
                    @else
                        <a href="{{ route('login') }}" class="block text-gray-500">Complex Content (Login Required)</a>
                        <a href="{{ route('login') }}" class="block text-gray-500">Components Demo (Login Required)</a>
                    @endauth
                </nav>
            </div>

            <!-- Test Information -->
            <div class="bg-yellow-50 p-6 rounded-lg border border-yellow-200">
                <h3 class="text-lg font-semibold text-yellow-800 mb-3">Test Information</h3>
                <div class="space-y-2 text-sm text-yellow-700">
                    <p><strong>Page Type:</strong> Simple Content</p>
                    <p><strong>Last Updated:</strong> {{ $last_updated }}</p>
                    <p><strong>Content Sections:</strong> {{ count($sections) }}</p>
                    <p><strong>Editable Elements:</strong> {{ count($features) + count($sections) + 5 }}</p>
                </div>
            </div>

            <!-- Login Status -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Authentication Status</h3>
                @auth
                    <div class="flex items-center space-x-2 text-green-600">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">Logged In</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">You have access to all test pages</p>
                    <div class="mt-3">
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 underline">Logout</button>
                        </form>
                    </div>
                @else
                    <div class="flex items-center space-x-2 text-orange-600">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">Guest User</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">Limited access to test pages</p>
                    <div class="mt-3">
                        <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:text-blue-800 underline">Login</a>
                        <span class="text-gray-400 mx-2">|</span>
                        <a href="{{ route('register') }}" class="text-sm text-blue-600 hover:text-blue-800 underline">Register</a>
                    </div>
                @endauth
            </div>

            <!-- Debug Information -->
            @if($debug_info)
                <div class="bg-gray-100 p-4 rounded-lg text-xs">
                    <h4 class="font-semibold text-gray-800 mb-2">Debug Information</h4>
                    <dl class="space-y-1">
                        @foreach($debug_info as $key => $value)
                            <div class="flex">
                                <dt class="font-medium text-gray-600 mr-2">{{ ucfirst(str_replace('_', ' ', $key)) }}:</dt>
                                <dd class="text-gray-800 break-all">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </aside>
    </main>

    <!-- Footer Content -->
    <footer class="mt-12 pt-8 border-t border-gray-200">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3">About This Test Page</h3>
                <p class="text-gray-700 text-sm leading-relaxed">{{ $footer_text }}</p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-3">CMS Features Tested</h3>
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
                    <div class="flex items-center space-x-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span>Text Editing</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span>Header Management</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span>List Editing</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span>Link Management</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span>Content Sections</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span>Metadata</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
            <p>&copy; {{ date('Y') }} CMS Test Application. Generated on {{ now()->format('F j, Y \a\t g:i A') }}.</p>
        </div>
    </footer>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add visual indicators for editable content in CMS mode
    if (window.location.search.includes('cms=edit')) {
        const editableElements = document.querySelectorAll('h1, h2, h3, p, li, blockquote');
        editableElements.forEach(element => {
            element.style.border = '2px dashed #3B82F6';
            element.style.padding = '4px';
            element.style.margin = '2px';
            element.title = 'Editable content area';
        });
    }

    // Simple interaction logging for testing
    const links = document.querySelectorAll('a');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            console.log('Link clicked:', {
                text: this.textContent.trim(),
                href: this.href,
                timestamp: new Date().toISOString()
            });
        });
    });

    // Test content interaction
    const contentSections = document.querySelectorAll('.content-section');
    contentSections.forEach((section, index) => {
        section.addEventListener('click', function() {
            console.log('Content section clicked:', {
                index: index,
                title: section.querySelector('h3')?.textContent || 'Unknown',
                timestamp: new Date().toISOString()
            });
        });
    });
});
</script>
@endsection