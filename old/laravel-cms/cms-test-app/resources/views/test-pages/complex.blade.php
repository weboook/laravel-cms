@extends('layouts.app')

@section('title', $title)

@section('meta')
    <meta name="description" content="{{ $metadata['meta_description'] }}">
    <meta property="og:title" content="{{ $metadata['page_title'] }}">
    <meta property="og:description" content="{{ $metadata['meta_description'] }}">
    <meta property="og:image" content="{{ $metadata['og_image'] }}">
    <meta property="og:url" content="{{ $metadata['canonical_url'] }}">
    <link rel="canonical" href="{{ $metadata['canonical_url'] }}">
@endsection

@section('content')
<!-- Hero Section -->
<section class="hero-section relative bg-gradient-to-r from-blue-600 to-purple-700 text-white overflow-hidden">
    <div class="absolute inset-0 bg-black opacity-40"></div>
    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $hero['background_image'] }}')"></div>

    <div class="relative container mx-auto px-4 py-20">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-5xl md:text-6xl font-bold mb-6">{{ $hero['heading'] }}</h1>
            <p class="text-xl md:text-2xl mb-8 text-gray-200">{{ $hero['subheading'] }}</p>
            <a href="{{ $hero['cta_url'] }}" class="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold text-lg hover:bg-gray-100 transition-colors">
                {{ $hero['cta_text'] }}
            </a>
        </div>
    </div>
</section>

<div class="container mx-auto px-4 py-12">

    <!-- Media Gallery Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Media Gallery</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($media_gallery as $media)
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    @if($media['type'] === 'image')
                        <img src="{{ $media['src'] }}" alt="{{ $media['alt'] }}" class="w-full h-48 object-cover">
                    @elseif($media['type'] === 'video')
                        <video class="w-full h-48 object-cover" poster="{{ $media['poster'] }}" controls>
                            <source src="{{ $media['src'] }}" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    @endif

                    <div class="p-4">
                        <p class="text-gray-700 text-sm">{{ $media['caption'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Content Blocks Section -->
    <section class="mb-16">
        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Rich Content Blocks</h2>

        <div class="space-y-8">
            @foreach($content_blocks as $block)
                @if($block['type'] === 'text')
                    <article class="bg-gray-50 p-8 rounded-lg">
                        <h3 class="text-2xl font-semibold text-gray-900 mb-4">{{ $block['title'] }}</h3>
                        <div class="prose max-w-none text-gray-700">
                            {!! $block['content'] !!}
                        </div>
                    </article>

                @elseif($block['type'] === 'quote')
                    <blockquote class="bg-white border-l-4 border-blue-500 p-8 shadow-md rounded-r-lg">
                        <p class="text-xl italic text-gray-700 mb-4">{{ $block['content'] }}</p>
                        <footer class="text-gray-600">
                            <cite class="font-semibold">— {{ $block['author'] }}</cite>
                            @if(isset($block['source']))
                                <span class="text-sm text-gray-500">, {{ $block['source'] }}</span>
                            @endif
                        </footer>
                    </blockquote>

                @elseif($block['type'] === 'code')
                    <div class="bg-gray-900 rounded-lg overflow-hidden">
                        <div class="bg-gray-800 px-4 py-2 text-sm text-gray-300 border-b border-gray-700">
                            <span class="font-semibold">{{ $block['title'] }}</span>
                            @if(isset($block['language']))
                                <span class="float-right text-gray-400">{{ $block['language'] }}</span>
                            @endif
                        </div>
                        <pre class="p-4 text-green-400 overflow-x-auto"><code>{{ $block['content'] }}</code></pre>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content Area -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Links Section -->
            <section class="bg-white p-8 rounded-lg shadow-md">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Link Management Testing</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Internal Links -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Internal Links</h3>
                        <nav class="space-y-2">
                            @foreach($links['internal'] as $link)
                                <a href="{{ $link['url'] }}" class="block text-blue-600 hover:text-blue-800 hover:underline transition-colors">
                                    {{ $link['text'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    <!-- External Links -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">External Links</h3>
                        <nav class="space-y-2">
                            @foreach($links['external'] as $link)
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="block text-green-600 hover:text-green-800 hover:underline transition-colors">
                                    {{ $link['text'] }}
                                    <svg class="inline w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-1a1 1 0 10-2 0v1H5V7h1a1 1 0 000-2H5z"/>
                                    </svg>
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </div>
            </section>

            <!-- Forms Section -->
            <section class="bg-white p-8 rounded-lg shadow-md">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Interactive Forms</h2>

                @foreach($forms as $form)
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $form['title'] }}</h3>
                        <p class="text-gray-600 mb-4">Form ID: {{ $form['id'] }}</p>

                        <form action="{{ $form['action'] }}" method="{{ $form['method'] }}" class="space-y-4">
                            @csrf
                            @foreach($form['fields'] as $field)
                                <div class="form-group">
                                    <label for="{{ $field['name'] }}" class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ $field['label'] }}
                                        @if($field['required'])
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </label>

                                    @if($field['type'] === 'textarea')
                                        <textarea id="{{ $field['name'] }}" name="{{ $field['name'] }}"
                                                rows="{{ $field['rows'] ?? 3 }}"
                                                placeholder="{{ $field['placeholder'] ?? '' }}"
                                                {{ $field['required'] ? 'required' : '' }}
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    @elseif($field['type'] === 'checkbox')
                                        <label class="flex items-center">
                                            <input type="checkbox" name="{{ $field['name'] }}" value="{{ $field['value'] ?? '1' }}"
                                                   class="mr-2 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                            <span class="text-sm text-gray-700">{{ $field['label'] }}</span>
                                        </label>
                                    @else
                                        <input type="{{ $field['type'] }}" id="{{ $field['name'] }}" name="{{ $field['name'] }}"
                                               placeholder="{{ $field['placeholder'] ?? '' }}"
                                               {{ $field['required'] ? 'required' : '' }}
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @endif
                                </div>
                            @endforeach

                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                                Submit {{ $form['title'] }}
                            </button>
                        </form>
                    </div>
                @endforeach
            </section>

            <!-- Performance Information -->
            @if($performance_data)
                <section class="bg-yellow-50 border border-yellow-200 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-yellow-800 mb-4">Performance Metrics</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-yellow-700">Memory Usage:</span>
                            <span class="text-yellow-900">{{ number_format($performance_data['memory_usage'] / 1024 / 1024, 2) }} MB</span>
                        </div>
                        <div>
                            <span class="font-medium text-yellow-700">Peak Memory:</span>
                            <span class="text-yellow-900">{{ number_format($performance_data['memory_peak'] / 1024 / 1024, 2) }} MB</span>
                        </div>
                        <div>
                            <span class="font-medium text-yellow-700">Execution Time:</span>
                            <span class="text-yellow-900">{{ number_format($performance_data['execution_time'] * 1000, 2) }} ms</span>
                        </div>
                        <div>
                            <span class="font-medium text-yellow-700">Included Files:</span>
                            <span class="text-yellow-900">{{ $performance_data['included_files'] }}</span>
                        </div>
                    </div>
                </section>
            @endif
        </div>

        <!-- Sidebar -->
        <aside class="lg:col-span-1 space-y-6">
            <!-- Sidebar Widgets -->
            @foreach($sidebar_widgets as $widget)
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $widget['title'] }}</h3>

                    @if($widget['type'] === 'recent_posts')
                        <div class="space-y-3">
                            @foreach($widget['items'] as $item)
                                <article class="border-b border-gray-100 pb-3 last:border-b-0">
                                    <h4 class="font-medium text-gray-900 mb-1">
                                        <a href="{{ $item['url'] }}" class="hover:text-blue-600 transition-colors">{{ $item['title'] }}</a>
                                    </h4>
                                    <time class="text-sm text-gray-500">{{ $item['date'] }}</time>
                                </article>
                            @endforeach
                        </div>

                    @elseif($widget['type'] === 'social_links')
                        <div class="flex space-x-3">
                            @foreach($widget['links'] as $social)
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="text-gray-600 hover:text-gray-900 transition-colors"
                                   title="{{ ucfirst($social['platform']) }}">
                                    <i class="{{ $social['icon'] }} text-xl"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            <!-- Cache Status -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">System Status</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cache:</span>
                        <span class="font-medium {{ $cache_enabled ? 'text-green-600' : 'text-red-600' }}">
                            {{ $cache_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Environment:</span>
                        <span class="font-medium text-gray-900">{{ app()->environment() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Debug Mode:</span>
                        <span class="font-medium {{ config('app.debug') ? 'text-orange-600' : 'text-green-600' }}">
                            {{ config('app.debug') ? 'On' : 'Off' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Test Actions -->
            <div class="bg-blue-50 border border-blue-200 p-6 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 mb-4">Test Actions</h3>
                <div class="space-y-2">
                    <button onclick="testAjaxRequest()" class="w-full bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700 transition-colors">
                        Test AJAX Request
                    </button>
                    <button onclick="testContentUpdate()" class="w-full bg-green-600 text-white px-4 py-2 rounded text-sm hover:bg-green-700 transition-colors">
                        Test Content Update
                    </button>
                    <button onclick="testImageUpload()" class="w-full bg-purple-600 text-white px-4 py-2 rounded text-sm hover:bg-purple-700 transition-colors">
                        Test Image Upload
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Interactive Elements Section -->
    <section class="mt-16 mb-12">
        <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Interactive Elements</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Tabs Example -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="border-b border-gray-200">
                    <nav class="flex">
                        <button onclick="showTab('tab1')" class="tab-button px-4 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-300" data-tab="tab1">
                            Tab 1
                        </button>
                        <button onclick="showTab('tab2')" class="tab-button px-4 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-300" data-tab="tab2">
                            Tab 2
                        </button>
                        <button onclick="showTab('tab3')" class="tab-button px-4 py-2 text-sm font-medium text-gray-700 hover:text-blue-600 border-b-2 border-transparent hover:border-blue-300" data-tab="tab3">
                            Tab 3
                        </button>
                    </nav>
                </div>
                <div class="p-4">
                    <div id="tab1" class="tab-content">
                        <h3 class="font-semibold text-gray-900 mb-2">First Tab Content</h3>
                        <p class="text-gray-700 text-sm">This is the content for the first tab. It can be edited through the CMS.</p>
                    </div>
                    <div id="tab2" class="tab-content hidden">
                        <h3 class="font-semibold text-gray-900 mb-2">Second Tab Content</h3>
                        <p class="text-gray-700 text-sm">Content for the second tab with different information.</p>
                    </div>
                    <div id="tab3" class="tab-content hidden">
                        <h3 class="font-semibold text-gray-900 mb-2">Third Tab Content</h3>
                        <p class="text-gray-700 text-sm">The third tab contains additional test content.</p>
                    </div>
                </div>
            </div>

            <!-- Accordion Example -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="border-b border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900">Accordion Example</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <div class="accordion-item">
                        <button onclick="toggleAccordion('acc1')" class="w-full p-4 text-left hover:bg-gray-50 focus:outline-none">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-900">Accordion Item 1</span>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="acc1-icon" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </button>
                        <div id="acc1" class="hidden p-4 bg-gray-50">
                            <p class="text-gray-700 text-sm">Content for the first accordion item.</p>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <button onclick="toggleAccordion('acc2')" class="w-full p-4 text-left hover:bg-gray-50 focus:outline-none">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-900">Accordion Item 2</span>
                                <svg class="w-5 h-5 text-gray-500 transform transition-transform" id="acc2-icon" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </button>
                        <div id="acc2" class="hidden p-4 bg-gray-50">
                            <p class="text-gray-700 text-sm">Content for the second accordion item.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Example -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Modal Example</h3>
                <button onclick="openModal('test-modal')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                    Open Test Modal
                </button>

                <!-- Modal -->
                <div id="test-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                    <div class="bg-white rounded-lg p-6 max-w-md mx-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Test Modal</h3>
                        <p class="text-gray-700 mb-4">This is a test modal for demonstrating interactive content that can be managed through the CMS.</p>
                        <div class="flex justify-end space-x-2">
                            <button onclick="closeModal('test-modal')" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</button>
                            <button onclick="closeModal('test-modal')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">OK</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection

@section('scripts')
<script>
// Tab functionality
function showTab(tabId) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active state from all buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('border-blue-600', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-700');
    });

    // Show selected tab content
    document.getElementById(tabId).classList.remove('hidden');

    // Add active state to clicked button
    const activeButton = document.querySelector(`[data-tab="${tabId}"]`);
    activeButton.classList.remove('border-transparent', 'text-gray-700');
    activeButton.classList.add('border-blue-600', 'text-blue-600');
}

// Accordion functionality
function toggleAccordion(itemId) {
    const content = document.getElementById(itemId);
    const icon = document.getElementById(itemId + '-icon');

    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }
}

// Modal functionality
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

// Test functions for CMS integration
function testAjaxRequest() {
    fetch('{{ route("test.api.dynamic") }}')
        .then(response => response.json())
        .then(data => {
            alert('AJAX Test Successful!\nTimestamp: ' + data.timestamp + '\nRandom: ' + data.random_number);
            console.log('AJAX Response:', data);
        })
        .catch(error => {
            alert('AJAX Test Failed: ' + error.message);
            console.error('AJAX Error:', error);
        });
}

function testContentUpdate() {
    const data = {
        selector: 'h1',
        content: 'Updated via CMS Test at ' + new Date().toLocaleTimeString(),
        page: 'complex'
    };

    fetch('{{ route("test.api.content.update") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        alert('Content Update Test Successful!\nSelector: ' + result.data.selector);
        console.log('Content Update Response:', result);
    })
    .catch(error => {
        alert('Content Update Test Failed: ' + error.message);
        console.error('Content Update Error:', error);
    });
}

function testImageUpload() {
    // Create a mock file for testing
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'image');

            fetch('{{ route("test.api.upload") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                alert('Image Upload Test Successful!\nFilename: ' + result.data.filename);
                console.log('Upload Response:', result);
            })
            .catch(error => {
                alert('Image Upload Test Failed: ' + error.message);
                console.error('Upload Error:', error);
            });
        }
    };
    input.click();
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set default active tab
    showTab('tab1');

    // Add smooth scrolling to anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Log page load for testing
    console.log('Complex test page loaded', {
        timestamp: new Date().toISOString(),
        performance: @json($performance_data),
        cacheEnabled: {{ $cache_enabled ? 'true' : 'false' }}
    });
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('bg-black') && e.target.classList.contains('bg-opacity-50')) {
        const modal = e.target;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
});
</script>
@endsection