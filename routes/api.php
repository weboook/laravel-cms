<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\ToolbarController;

// CMS API routes (no authentication for toolbar functionality)
Route::prefix('cms')->group(function () {
    Route::get('/pages', [ToolbarController::class, 'getPages']);
    Route::get('/languages', [ToolbarController::class, 'getLanguages']);
    Route::get('/settings', [ToolbarController::class, 'getSettings']);
    Route::post('/settings', [ToolbarController::class, 'updateSettings']);
    Route::get('/template-items', [ToolbarController::class, 'getTemplateItems']);
});