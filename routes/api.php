<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\ToolbarController;
use Webook\LaravelCMS\Http\Controllers\ContentController;

// CMS API routes (no authentication for toolbar functionality)
Route::prefix('cms')->group(function () {
    Route::get('/pages', [ToolbarController::class, 'getPages']);
    Route::get('/languages', [ToolbarController::class, 'getLanguages']);
    Route::get('/settings', [ToolbarController::class, 'getSettings']);
    Route::post('/settings', [ToolbarController::class, 'updateSettings']);
    Route::get('/template-items', [ToolbarController::class, 'getTemplateItems']);

    // Content management routes
    Route::post('/content/save', [ContentController::class, 'save']);
    Route::post('/content/update', [ContentController::class, 'update']);
    Route::post('/content/bulk-update', [ContentController::class, 'updateBulk']);
    Route::get('/content/backups', [ContentController::class, 'backups']);
    Route::post('/content/restore', [ContentController::class, 'restore']);
});