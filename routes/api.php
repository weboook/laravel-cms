<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\ToolbarController;
use Webook\LaravelCMS\Http\Controllers\ContentController;
use Webook\LaravelCMS\Http\Controllers\MediaController;
use Webook\LaravelCMS\Http\Controllers\SettingsController;

// CMS API routes (no authentication for toolbar functionality)
Route::prefix('cms')->group(function () {
    Route::get('/pages', [ToolbarController::class, 'getPages']);
    Route::get('/languages', [ToolbarController::class, 'getLanguages']);
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings', [SettingsController::class, 'save']);
    Route::get('/settings/exclusions', [SettingsController::class, 'getExclusions']);
    Route::get('/template-items', [ToolbarController::class, 'getTemplateItems']);

    // Content management routes
    Route::post('/content/save', [ContentController::class, 'save']);
    Route::post('/content/update', [ContentController::class, 'update']);
    Route::post('/content/bulk-update', [ContentController::class, 'updateBulk']);
    Route::get('/content/backups', [ContentController::class, 'backups']);
    Route::post('/content/restore', [ContentController::class, 'restore']);

    // Media management routes
    Route::post('/media/upload', [MediaController::class, 'upload']);
    Route::post('/media/upload-multiple', [MediaController::class, 'uploadMultiple']);
    Route::get('/media', [MediaController::class, 'list']);
    Route::delete('/media/{id}', [MediaController::class, 'delete']);

    // Media folder routes
    Route::get('/media/folders', [MediaController::class, 'getFolders']);
    Route::post('/media/folders', [MediaController::class, 'createFolder']);
    Route::delete('/media/folders/{id}', [MediaController::class, 'deleteFolder']);
});