<?php

use App\Http\Controllers\TestPagesController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes for CMS Test Application
|--------------------------------------------------------------------------
|
| Here are the routes for testing the Laravel CMS package functionality.
| These routes cover various content types, authentication scenarios,
| and localization features.
|
*/

// Home route - redirect to simple test page
Route::get('/', function () {
    return redirect()->route('test.simple');
})->name('home');

// Locale switching route
Route::get('/locale/{locale}', function (Request $request, $locale) {
    // Validate locale
    $supportedLocales = config('cms-test.supported_locales', ['en', 'es', 'fr']);

    if (!in_array($locale, $supportedLocales)) {
        abort(404, 'Locale not supported');
    }

    // Set application locale
    App::setLocale($locale);
    Session::put('locale', $locale);

    // Redirect back to previous page or default
    $redirectUrl = $request->get('redirect', route('test.simple'));

    // Ensure redirect URL is safe (same domain)
    if (!filter_var($redirectUrl, FILTER_VALIDATE_URL) ||
        parse_url($redirectUrl, PHP_URL_HOST) !== $request->getHost()) {
        $redirectUrl = route('test.simple');
    }

    return redirect($redirectUrl)->with('locale_changed', [
        'old' => Session::get('previous_locale', 'en'),
        'new' => $locale
    ]);
})->name('locale.switch');

// Test Pages Routes
Route::prefix('test')->name('test.')->group(function () {

    // Simple content test page (no auth required)
    Route::get('/simple', [TestPagesController::class, 'simple'])
        ->name('simple');

    // Translated content test page (no auth required)
    Route::get('/translated', [TestPagesController::class, 'translated'])
        ->name('translated');

    // Complex content test page (auth required)
    Route::middleware(['auth'])->group(function () {
        Route::get('/complex', [TestPagesController::class, 'complex'])
            ->name('complex');

        Route::get('/components', [TestPagesController::class, 'components'])
            ->name('components');

        Route::get('/dynamic', [TestPagesController::class, 'dynamic'])
            ->name('dynamic');

        Route::match(['GET', 'POST'], '/forms', [TestPagesController::class, 'forms'])
            ->name('forms');
    });

    // API test routes for AJAX testing
    Route::prefix('api')->name('api.')->middleware(['auth'])->group(function () {

        // Get dynamic content for AJAX updates
        Route::get('/dynamic-content', function (Request $request) {
            return response()->json([
                'timestamp' => now()->toISOString(),
                'random_number' => random_int(1000, 9999),
                'server_time' => now()->format('H:i:s'),
                'visitor_count' => random_int(100, 1000),
                'cache_status' => 'active'
            ]);
        })->name('dynamic');

        // Test content update endpoint
        Route::post('/content/update', function (Request $request) {
            $request->validate([
                'selector' => 'required|string',
                'content' => 'required|string',
                'page' => 'required|string'
            ]);

            // Mock content update
            return response()->json([
                'success' => true,
                'message' => 'Content updated successfully',
                'data' => [
                    'selector' => $request->selector,
                    'old_content' => 'Previous content',
                    'new_content' => $request->content,
                    'updated_at' => now()->toISOString()
                ]
            ]);
        })->name('content.update');

        // Test translation update endpoint
        Route::post('/translation/update', function (Request $request) {
            $request->validate([
                'key' => 'required|string',
                'value' => 'required|string',
                'locale' => 'required|string|in:en,es,fr'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Translation updated successfully',
                'data' => [
                    'key' => $request->key,
                    'value' => $request->value,
                    'locale' => $request->locale,
                    'updated_at' => now()->toISOString()
                ]
            ]);
        })->name('translation.update');

        // Test file upload endpoint
        Route::post('/upload', function (Request $request) {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'type' => 'required|in:image,document,media'
            ]);

            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Mock file upload (don't actually store in test)
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'url' => '/uploads/test/' . $filename
                ]
            ]);
        })->name('upload');

        // Test search endpoint
        Route::get('/search', function (Request $request) {
            $query = $request->get('q', '');
            $type = $request->get('type', 'all');

            // Mock search results
            $results = [
                'pages' => [
                    ['title' => 'Simple Test Page', 'url' => route('test.simple'), 'type' => 'page'],
                    ['title' => 'Complex Content', 'url' => route('test.complex'), 'type' => 'page']
                ],
                'content' => [
                    ['title' => 'Test Content Block 1', 'excerpt' => 'Content excerpt...', 'type' => 'content'],
                    ['title' => 'Test Content Block 2', 'excerpt' => 'Another excerpt...', 'type' => 'content']
                ]
            ];

            if ($type !== 'all') {
                $results = isset($results[$type]) ? [$type => $results[$type]] : [];
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'type' => $type,
                'results' => $results,
                'total' => collect($results)->flatten(1)->count()
            ]);
        })->name('search');
    });
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    // Login form
    Route::get('/login', function () {
        return view('auth.login', [
            'title' => 'Login - CMS Test App',
            'redirect_to' => request()->get('redirect', route('test.complex'))
        ]);
    })->name('login');

    // Handle login
    Route::post('/login', function (Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        // Mock authentication - in real app, use Auth::attempt()
        $credentials = $request->only('email', 'password');

        // Test credentials
        $testUsers = [
            'admin@test.com' => 'admin123',
            'editor@test.com' => 'editor123',
            'user@test.com' => 'user123'
        ];

        if (isset($testUsers[$credentials['email']]) &&
            $testUsers[$credentials['email']] === $credentials['password']) {

            // Create mock user session
            Session::put('auth.user', [
                'id' => array_search($credentials['email'], array_keys($testUsers)) + 1,
                'name' => ucfirst(explode('@', $credentials['email'])[0]),
                'email' => $credentials['email'],
                'role' => explode('@', $credentials['email'])[0]
            ]);

            $redirectTo = $request->get('redirect', route('test.complex'));
            return redirect($redirectTo)->with('login_success', 'Logged in successfully!');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials. Try: admin@test.com / admin123'
        ])->withInput();
    })->name('login.post');

    // Registration form
    Route::get('/register', function () {
        return view('auth.register', [
            'title' => 'Register - CMS Test App'
        ]);
    })->name('register');

    // Handle registration
    Route::post('/register', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed'
        ]);

        // Mock registration
        Session::put('auth.user', [
            'id' => random_int(100, 999),
            'name' => $request->name,
            'email' => $request->email,
            'role' => 'user'
        ]);

        return redirect()->route('test.complex')
            ->with('registration_success', 'Account created successfully!');
    })->name('register.post');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', function () {
        Session::forget('auth.user');
        return redirect()->route('test.simple')
            ->with('logout_success', 'Logged out successfully!');
    })->name('logout');

    // User profile
    Route::get('/profile', function () {
        return view('auth.profile', [
            'title' => 'Profile - CMS Test App',
            'user' => Session::get('auth.user')
        ]);
    })->name('profile');

    // Update profile
    Route::put('/profile', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email'
        ]);

        $user = Session::get('auth.user');
        $user['name'] = $request->name;
        $user['email'] = $request->email;
        Session::put('auth.user', $user);

        return back()->with('profile_updated', 'Profile updated successfully!');
    })->name('profile.update');
});

// CMS Admin Routes (for testing CMS interface)
Route::prefix('cms')->name('cms.')->middleware(['auth'])->group(function () {

    // CMS Dashboard
    Route::get('/', function () {
        return view('cms.dashboard', [
            'title' => 'CMS Dashboard',
            'stats' => [
                'total_pages' => 6,
                'total_translations' => 45,
                'active_users' => 3,
                'recent_changes' => 12
            ]
        ]);
    })->name('dashboard');

    // Content management
    Route::get('/content', function () {
        return view('cms.content.index', [
            'title' => 'Content Management',
            'pages' => [
                ['id' => 1, 'title' => 'Simple Test Page', 'type' => 'page', 'status' => 'published'],
                ['id' => 2, 'title' => 'Translated Content', 'type' => 'page', 'status' => 'published'],
                ['id' => 3, 'title' => 'Complex Content', 'type' => 'page', 'status' => 'draft'],
                ['id' => 4, 'title' => 'Components Demo', 'type' => 'page', 'status' => 'published']
            ]
        ]);
    })->name('content.index');

    // Translation management
    Route::get('/translations', function () {
        return view('cms.translations.index', [
            'title' => 'Translation Management',
            'locales' => ['en', 'es', 'fr'],
            'groups' => ['test', 'auth', 'validation', 'messages']
        ]);
    })->name('translations.index');

    // File management
    Route::get('/files', function () {
        return view('cms.files.index', [
            'title' => 'File Management',
            'directories' => [
                'resources/views/test-pages',
                'resources/lang',
                'public/images',
                'public/css',
                'public/js'
            ]
        ]);
    })->name('files.index');

    // Settings
    Route::get('/settings', function () {
        return view('cms.settings.index', [
            'title' => 'CMS Settings',
            'config' => [
                'app_name' => 'CMS Test Application',
                'default_locale' => 'en',
                'supported_locales' => ['en', 'es', 'fr'],
                'cache_enabled' => true,
                'debug_mode' => true
            ]
        ]);
    })->name('settings.index');
});

// Development and Testing Routes
Route::prefix('dev')->name('dev.')->group(function () {

    // PHPInfo (only in development)
    Route::get('/phpinfo', function () {
        if (!app()->environment('local', 'testing')) {
            abort(404);
        }
        return response(phpinfo(), 200, ['Content-Type' => 'text/html']);
    })->name('phpinfo');

    // Test database connection
    Route::get('/db-test', function () {
        try {
            \DB::connection()->getPdo();
            return response()->json([
                'status' => 'success',
                'message' => 'Database connection successful',
                'driver' => config('database.default'),
                'database' => config('database.connections.' . config('database.default') . '.database')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    })->name('db.test');

    // Test cache
    Route::get('/cache-test', function () {
        $key = 'test_cache_' . time();
        $value = 'Cache test value: ' . now()->toDateTimeString();

        \Cache::put($key, $value, 60);
        $retrieved = \Cache::get($key);

        return response()->json([
            'status' => $retrieved === $value ? 'success' : 'error',
            'driver' => config('cache.default'),
            'set_value' => $value,
            'retrieved_value' => $retrieved,
            'match' => $retrieved === $value
        ]);
    })->name('cache.test');

    // Clear all caches
    Route::post('/clear-cache', function () {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');

        return response()->json([
            'status' => 'success',
            'message' => 'All caches cleared successfully'
        ]);
    })->name('cache.clear');

    // Generate test data
    Route::post('/generate-test-data', function () {
        \Artisan::call('db:seed', ['--class' => 'CMSTestSeeder']);

        return response()->json([
            'status' => 'success',
            'message' => 'Test data generated successfully'
        ]);
    })->name('test-data.generate');
});

// Error testing routes
Route::prefix('errors')->name('errors.')->group(function () {
    Route::get('/404', function () { abort(404); })->name('404');
    Route::get('/500', function () { abort(500); })->name('500');
    Route::get('/403', function () { abort(403); })->name('403');
});

// API Routes for external testing
Route::prefix('api/v1')->name('api.v1.')->group(function () {

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'environment' => app()->environment()
        ]);
    })->name('health');

    // System status
    Route::get('/status', function () {
        return response()->json([
            'database' => 'connected',
            'cache' => 'active',
            'memory_usage' => memory_get_usage(true),
            'uptime' => 'N/A', // Would be calculated in real app
            'load_average' => sys_getloadavg()
        ]);
    })->name('status');
});

// Fallback route for undefined routes
Route::fallback(function () {
    return view('errors.404', [
        'title' => 'Page Not Found - CMS Test App',
        'message' => 'The page you are looking for could not be found.',
        'suggestions' => [
            ['text' => 'Go to Home Page', 'url' => route('home')],
            ['text' => 'Simple Test Page', 'url' => route('test.simple')],
            ['text' => 'Translated Content', 'url' => route('test.translated')],
            ['text' => 'Login', 'url' => route('login')]
        ]
    ]);
});