@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container mx-auto px-4 py-8" id="dynamic-content-container">

    <!-- Page Header with Real-time Clock -->
    <header class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ $title }}</h1>
        <p class="text-xl text-gray-600 mb-6">Real-time content updates, dynamic data, and conditional rendering for CMS testing</p>

        <!-- Live Clock -->
        <div class="inline-flex items-center space-x-2 bg-blue-100 px-4 py-2 rounded-lg">
            <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
            </svg>
            <span class="text-blue-800 font-medium">Current Time: </span>
            <span id="live-clock" class="font-mono text-blue-900">{{ $server_time }}</span>
        </div>
    </header>

    <!-- Server Information -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">Server Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($user_info as $key => $value)
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">
                        {{ str_replace('_', ' ', $key) }}
                    </h3>
                    <p class="text-lg font-semibold text-gray-900 break-all">{{ $value }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Dynamic Content Widgets -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">Dynamic Content</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- Random Number Widget -->
            <div class="bg-gradient-to-br from-purple-400 to-purple-600 text-white p-6 rounded-lg shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Random Number</h3>
                    <button onclick="updateRandomNumber()" class="text-purple-200 hover:text-white">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <p id="random-number" class="text-3xl font-bold">{{ $dynamic_content['random_number'] }}</p>
                <p class="text-purple-200 text-sm mt-2">Updates every refresh</p>
            </div>

            <!-- Quote of the Day Widget -->
            <div class="bg-gradient-to-br from-green-400 to-green-600 text-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">Quote of the Day</h3>
                <blockquote id="daily-quote" class="text-sm italic">
                    "{{ $dynamic_content['quote_of_the_day'] }}"
                </blockquote>
                <button onclick="getNewQuote()" class="mt-4 text-green-200 hover:text-white text-sm underline">
                    Get New Quote
                </button>
            </div>

            <!-- Visitor Count Widget -->
            <div class="bg-gradient-to-br from-blue-400 to-blue-600 text-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">Visitor Count</h3>
                <p id="visitor-count" class="text-3xl font-bold">{{ number_format($dynamic_content['visitor_count']) }}</p>
                <p class="text-blue-200 text-sm mt-2">Total visitors today</p>
            </div>
        </div>
    </section>

    <!-- Weather Widget -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">Weather Information</h2>

        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                @foreach($dynamic_content['weather_widget'] as $key => $value)
                    <div class="text-center">
                        <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">
                            {{ str_replace('_', ' ', $key) }}
                        </h3>
                        <p id="weather-{{ $key }}" class="text-2xl font-bold text-gray-900">{{ $value }}</p>
                    </div>
                @endforeach
            </div>
            <button onclick="updateWeather()" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                Update Weather
            </button>
        </div>
    </section>

    <!-- Latest News -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">Latest News</h2>

        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div id="news-list" class="divide-y divide-gray-200">
                @foreach($dynamic_content['latest_news'] as $news)
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <h3 class="text-lg font-medium text-gray-900">{{ $news['title'] }}</h3>
                            <span class="text-sm text-gray-500">{{ $news['time'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="bg-gray-50 p-4">
                <button onclick="loadMoreNews()" class="text-blue-600 hover:text-blue-800 font-medium">
                    Load More News
                </button>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Main Content Area -->
        <div class="lg:col-span-2 space-y-8">

            <!-- Conditional Content -->
            <section>
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Conditional Content</h2>

                <!-- Promotion Banner -->
                @if($conditional_content['show_promotion'])
                    <div class="bg-orange-100 border-l-4 border-orange-400 p-6 mb-6">
                        <div class="flex items-start">
                            <div class="py-1">
                                <svg class="w-6 h-6 text-orange-600 mr-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-orange-800">Special Promotion!</h3>
                                <p class="text-orange-700">This promotional content is shown randomly to 30% of visitors.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-gray-100 border-l-4 border-gray-400 p-6 mb-6">
                        <p class="text-gray-700">No promotion currently active. Refresh to try again (30% chance).</p>
                    </div>
                @endif

                <!-- User Type Content -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">User-Specific Content</h3>

                    @if($conditional_content['user_type'] === 'authenticated')
                        <div class="flex items-center space-x-2 text-green-600 mb-4">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium">Welcome back, authenticated user!</span>
                        </div>
                        <p class="text-gray-700">You have access to premium content and features.</p>
                    @else
                        <div class="flex items-center space-x-2 text-orange-600 mb-4">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium">Guest User</span>
                        </div>
                        <p class="text-gray-700">Sign up to access premium content and personalized features.</p>
                        <a href="{{ route('login') }}" class="inline-block mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                            Sign In
                        </a>
                    @endif
                </div>

                <!-- Feature Flags -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Feature Flags</h3>

                    <div class="space-y-3">
                        @foreach($conditional_content['feature_flags'] as $feature => $enabled)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $feature)) }}</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- Real-time Data -->
            <section>
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Real-time System Data</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @foreach($real_time_data['system_load'] as $metric => $value)
                        <div class="bg-white p-6 rounded-lg shadow-md">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-2">{{ $metric }}</h3>
                            <div class="flex items-end space-x-2">
                                <span id="system-{{ $metric }}" class="text-2xl font-bold text-gray-900">{{ $value }}</span>
                                <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                         style="width: {{ $value }}"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Memory Usage -->
                <div class="mt-6 bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Memory Usage</h3>
                    <div class="grid grid-cols-2 gap-6">
                        @foreach($real_time_data['memory_usage'] as $type => $usage)
                            <div>
                                <span class="text-sm font-medium text-gray-500">{{ ucfirst($type) }}:</span>
                                <span id="memory-{{ $type }}" class="text-lg font-semibold text-gray-900 ml-2">{{ $usage }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <!-- API Data -->
            <section>
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">External API Data</h2>

                <!-- Placeholder Posts -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Sample Posts</h3>
                    </div>
                    <div id="api-posts" class="divide-y divide-gray-200">
                        @foreach($api_data['placeholder_posts'] as $post)
                            <div class="p-6">
                                <h4 class="font-medium text-gray-900 mb-2">{{ $post['title'] }}</h4>
                                <p class="text-gray-600 text-sm">{{ $post['excerpt'] }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="bg-gray-50 px-6 py-3">
                        <button onclick="loadApiData('posts')" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                            Refresh Posts
                        </button>
                    </div>
                </div>

                <!-- Sample Users -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Sample Users</h3>
                    </div>
                    <div id="api-users" class="divide-y divide-gray-200">
                        @foreach($api_data['sample_users'] as $user)
                            <div class="p-6 flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                    <span class="text-gray-600 font-medium">{{ substr($user['name'], 0, 1) }}</span>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $user['name'] }}</h4>
                                    <p class="text-gray-600 text-sm">{{ $user['email'] }} • {{ $user['role'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        <!-- Sidebar -->
        <aside class="lg:col-span-1 space-y-6">

            <!-- User Personalization -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Personalization</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Theme Preference</label>
                        <select id="theme-selector" onchange="updateTheme(this.value)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="light" {{ $user_personalization['preferred_theme'] === 'light' ? 'selected' : '' }}>Light</option>
                            <option value="dark" {{ $user_personalization['preferred_theme'] === 'dark' ? 'selected' : '' }}>Dark</option>
                            <option value="auto" {{ $user_personalization['preferred_theme'] === 'auto' ? 'selected' : '' }}>Auto</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Language</label>
                        <select id="language-selector" onchange="updateLanguage(this.value)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="en" {{ $user_personalization['language_preference'] === 'en' ? 'selected' : '' }}>English</option>
                            <option value="es" {{ $user_personalization['language_preference'] === 'es' ? 'selected' : '' }}>Español</option>
                            <option value="fr" {{ $user_personalization['language_preference'] === 'fr' ? 'selected' : '' }}>Français</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Recent Pages -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Pages</h3>

                @if(count($user_personalization['recent_pages']) > 0)
                    <div id="recent-pages" class="space-y-2">
                        @foreach($user_personalization['recent_pages'] as $page)
                            <a href="{{ $page['url'] }}" class="block text-blue-600 hover:text-blue-800 text-sm">
                                {{ $page['title'] }}
                                <span class="text-gray-500 text-xs block">{{ \Carbon\Carbon::parse($page['timestamp'])->diffForHumans() }}</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-sm">No recent pages yet.</p>
                @endif
            </div>

            <!-- Cache Statistics -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Cache Statistics</h3>

                <div class="space-y-3">
                    @foreach($real_time_data['cache_stats'] as $key => $value)
                        <div class="flex justify-between">
                            <span class="text-gray-600 text-sm">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                            <span id="cache-{{ $key }}" class="font-medium text-gray-900 text-sm">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>

                <button onclick="refreshCacheStats()" class="mt-4 w-full bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition-colors text-sm">
                    Refresh Stats
                </button>
            </div>

            <!-- Auto-refresh Controls -->
            <div class="bg-blue-50 border border-blue-200 p-6 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 mb-4">Auto-refresh</h3>

                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="auto-refresh-toggle" class="mr-2">
                        <span class="text-blue-700 text-sm">Enable auto-refresh</span>
                    </label>

                    <div>
                        <label class="block text-sm font-medium text-blue-700 mb-1">Interval (seconds)</label>
                        <input type="number" id="refresh-interval" value="30" min="5" max="300"
                               class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>

                    <div id="refresh-status" class="text-xs text-blue-600"></div>
                </div>
            </div>
        </aside>
    </div>

</div>
@endsection

@section('scripts')
<script>
let autoRefreshInterval = null;
let refreshCounter = 0;

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // Setup auto-refresh controls
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    const refreshIntervalInput = document.getElementById('refresh-interval');

    autoRefreshToggle.addEventListener('change', function() {
        if (this.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    refreshIntervalInput.addEventListener('change', function() {
        if (autoRefreshToggle.checked) {
            stopAutoRefresh();
            startAutoRefresh();
        }
    });

    console.log('Dynamic page loaded', {
        timestamp: new Date().toISOString(),
        userType: '{{ $conditional_content["user_type"] }}',
        features: @json($conditional_content['feature_flags'])
    });
});

// Live clock update
function updateLiveClock() {
    const clockElement = document.getElementById('live-clock');
    const now = new Date();
    clockElement.textContent = now.toLocaleTimeString();
}

// Auto-refresh functionality
function startAutoRefresh() {
    const interval = parseInt(document.getElementById('refresh-interval').value) * 1000;
    const statusElement = document.getElementById('refresh-status');

    autoRefreshInterval = setInterval(() => {
        refreshCounter++;
        updateDynamicContent();
        statusElement.textContent = `Auto-refreshed ${refreshCounter} times`;
    }, interval);

    statusElement.textContent = 'Auto-refresh enabled';
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        document.getElementById('refresh-status').textContent = 'Auto-refresh disabled';
    }
}

// Update dynamic content
function updateDynamicContent() {
    fetch('{{ route("test.api.dynamic") }}')
        .then(response => response.json())
        .then(data => {
            // Update random number
            document.getElementById('random-number').textContent = data.random_number;

            // Update visitor count
            document.getElementById('visitor-count').textContent = data.visitor_count.toLocaleString();

            // Update server time
            document.getElementById('live-clock').textContent = data.server_time;

            console.log('Dynamic content updated', data);
        })
        .catch(error => {
            console.error('Failed to update dynamic content:', error);
        });
}

// Individual widget updates
function updateRandomNumber() {
    const randomNumber = Math.floor(Math.random() * 9000) + 1000;
    document.getElementById('random-number').textContent = randomNumber;
}

function getNewQuote() {
    const quotes = [
        "The best time to plant a tree was 20 years ago. The second best time is now.",
        "Code is like humor. When you have to explain it, it's bad.",
        "First, solve the problem. Then, write the code.",
        "Experience is the name everyone gives to their mistakes.",
        "In order to be irreplaceable, one must always be different."
    ];

    const randomQuote = quotes[Math.floor(Math.random() * quotes.length)];
    document.getElementById('daily-quote').textContent = `"${randomQuote}"`;
}

function updateWeather() {
    const conditions = ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy', 'Windy'];
    const condition = conditions[Math.floor(Math.random() * conditions.length)];
    const temperature = Math.floor(Math.random() * 16) + 15; // 15-30°C
    const humidity = Math.floor(Math.random() * 51) + 30; // 30-80%
    const windSpeed = Math.floor(Math.random() * 21) + 5; // 5-25 km/h

    document.getElementById('weather-condition').textContent = condition;
    document.getElementById('weather-temperature').textContent = `${temperature}°C`;
    document.getElementById('weather-humidity').textContent = `${humidity}%`;
    document.getElementById('weather-wind_speed').textContent = `${windSpeed} km/h`;
}

function loadMoreNews() {
    const newsList = document.getElementById('news-list');
    const newNews = [
        { title: 'Breaking: New Feature Released', time: 'Just now' },
        { title: 'System Maintenance Complete', time: '5 minutes ago' },
        { title: 'Performance Improvements Deployed', time: '10 minutes ago' }
    ];

    newNews.forEach(news => {
        const newsItem = document.createElement('div');
        newsItem.className = 'p-6 hover:bg-gray-50 transition-colors';
        newsItem.innerHTML = `
            <div class="flex justify-between items-start">
                <h3 class="text-lg font-medium text-gray-900">${news.title}</h3>
                <span class="text-sm text-gray-500">${news.time}</span>
            </div>
        `;
        newsList.appendChild(newsItem);
    });
}

function loadApiData(type) {
    const container = document.getElementById(`api-${type}`);
    container.innerHTML = '<div class="p-6 text-center text-gray-500">Loading...</div>';

    // Simulate API call
    setTimeout(() => {
        if (type === 'posts') {
            const mockPosts = [
                { id: Date.now(), title: 'Newly Loaded Post 1', excerpt: 'Fresh content from API...' },
                { id: Date.now() + 1, title: 'Newly Loaded Post 2', excerpt: 'Another fresh excerpt...' },
                { id: Date.now() + 2, title: 'Newly Loaded Post 3', excerpt: 'More updated content...' }
            ];

            container.innerHTML = mockPosts.map(post => `
                <div class="p-6">
                    <h4 class="font-medium text-gray-900 mb-2">${post.title}</h4>
                    <p class="text-gray-600 text-sm">${post.excerpt}</p>
                </div>
            `).join('<div class="border-t border-gray-200"></div>');
        }
    }, 1000);
}

function refreshCacheStats() {
    const stats = ['driver', 'enabled', 'items'];
    stats.forEach(stat => {
        const element = document.getElementById(`cache-${stat}`);
        if (stat === 'items') {
            element.textContent = Math.floor(Math.random() * 200) + 50;
        } else if (stat === 'enabled') {
            element.textContent = Math.random() > 0.5 ? 'true' : 'false';
        }
    });
}

// Personalization functions
function updateTheme(theme) {
    console.log('Theme updated to:', theme);
    // Store in session/localStorage
    localStorage.setItem('preferred_theme', theme);

    // Apply theme (in real app, this would update CSS)
    document.body.setAttribute('data-theme', theme);
}

function updateLanguage(language) {
    console.log('Language updated to:', language);
    // In real app, this would redirect to change locale
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('locale', language);
    // window.location.href = currentUrl.toString();
}

// System monitoring
function updateSystemLoad() {
    const metrics = ['cpu', 'memory', 'disk'];
    metrics.forEach(metric => {
        const value = Math.floor(Math.random() * 80) + 10;
        const element = document.getElementById(`system-${metric}`);
        if (element) {
            element.textContent = `${value}%`;
            element.nextElementSibling.firstElementChild.style.width = `${value}%`;
        }
    });
}

// Memory usage monitoring
function updateMemoryUsage() {
    const current = (Math.random() * 50 + 20).toFixed(2);
    const peak = (current * 1.2).toFixed(2);

    document.getElementById('memory-current').textContent = `${current} MB`;
    document.getElementById('memory-peak').textContent = `${peak} MB`;
}

// Performance monitoring
function logPerformanceMetrics() {
    const metrics = {
        timestamp: new Date().toISOString(),
        memory: {
            used: window.performance.memory?.usedJSHeapSize || 0,
            total: window.performance.memory?.totalJSHeapSize || 0
        },
        timing: {
            domContentLoaded: window.performance.timing.domContentLoadedEventEnd - window.performance.timing.navigationStart,
            loadComplete: window.performance.timing.loadEventEnd - window.performance.timing.navigationStart
        },
        entries: window.performance.getEntriesByType('navigation')[0]
    };

    console.log('Performance metrics:', metrics);
    return metrics;
}

// WebSocket simulation for real-time updates
function simulateWebSocketUpdates() {
    setInterval(() => {
        const event = {
            type: 'update',
            data: {
                timestamp: new Date().toISOString(),
                randomValue: Math.random(),
                userCount: Math.floor(Math.random() * 100) + 50
            }
        };

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('websocket-update', { detail: event }));
    }, 5000); // Every 5 seconds
}

// Listen for WebSocket simulation updates
window.addEventListener('websocket-update', function(event) {
    console.log('WebSocket update received:', event.detail);
    // Update UI based on WebSocket data
});

// Initialize WebSocket simulation
simulateWebSocketUpdates();

// Performance monitoring
setInterval(() => {
    logPerformanceMetrics();
    updateSystemLoad();
    updateMemoryUsage();
}, 10000); // Every 10 seconds
</script>
@endsection