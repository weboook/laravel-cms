<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\EditorController;
use Webook\LaravelCMS\Http\Controllers\PreviewController;

/*
|--------------------------------------------------------------------------
| Laravel CMS Web Routes
|--------------------------------------------------------------------------
|
| This file contains the web routes for the Laravel CMS package.
| These routes handle the main editor interface and preview functionality.
| All routes are automatically prefixed and middleware is applied via
| the service provider configuration.
|
*/

// Editor Interface Routes
// These routes provide the main CMS editor interface for content management
Route::group([
    'middleware' => ['cms.auth', 'cms.permission:cms.edit'],
    'as' => 'cms.',
], function () {

    /*
    |--------------------------------------------------------------------------
    | Main Editor Interface
    |--------------------------------------------------------------------------
    |
    | The main editor interface where users can manage content.
    | Includes configuration and preview functionality.
    |
    */

    // GET /editor - Main editor interface
    Route::get('/editor', [EditorController::class, 'index'])
        ->name('editor.index')
        ->middleware('throttle:60,1');

    // GET /editor/preview/{url} - Preview with edit markers
    Route::get('/editor/preview/{url}', [PreviewController::class, 'show'])
        ->name('editor.preview')
        ->where('url', '.*')  // Allow any URL pattern
        ->middleware('throttle:120,1');

    // GET /editor/config - Get editor configuration
    Route::get('/editor/config', [EditorController::class, 'config'])
        ->name('editor.config')
        ->middleware('cache.headers:public;max_age=3600');
});

/*
|--------------------------------------------------------------------------
| Public Preview Routes
|--------------------------------------------------------------------------
|
| These routes allow viewing content with CMS markers for authorized users
| but remain accessible to the public without edit capabilities.
|
*/

Route::group([
    'as' => 'cms.public.',
    'middleware' => 'throttle:300,1',
], function () {

    // GET /preview/{url} - Public preview (no edit markers unless authenticated)
    Route::get('/preview/{url}', [PreviewController::class, 'public'])
        ->name('preview')
        ->where('url', '.*');
});

/*
|--------------------------------------------------------------------------
| Asset Serving Routes
|--------------------------------------------------------------------------
|
| Routes for serving CMS assets with proper caching headers.
|
*/

Route::group([
    'prefix' => 'assets',
    'as' => 'cms.assets.',
    'middleware' => 'throttle:1000,1',
], function () {

    // Serve CSS files
    Route::get('/css/{file}', function ($file) {
        return response()->file(
            public_path("vendor/cms/css/{$file}"),
            ['Content-Type' => 'text/css']
        );
    })->name('css')->where('file', '.*\.css');

    // Serve JavaScript files
    Route::get('/js/{file}', function ($file) {
        return response()->file(
            public_path("vendor/cms/js/{$file}"),
            ['Content-Type' => 'application/javascript']
        );
    })->name('js')->where('file', '.*\.js');

    // Serve other static assets
    Route::get('/static/{file}', function ($file) {
        $path = public_path("vendor/cms/static/{$file}");

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    })->name('static')->where('file', '.*');
});

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| Simple health check endpoint for monitoring CMS availability.
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('cms.version', '1.0.0'),
    ]);
})->name('cms.health')->middleware('throttle:60,1');