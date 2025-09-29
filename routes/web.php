<?php

use Illuminate\Support\Facades\Route;
use Webook\LaravelCMS\Http\Controllers\ToolbarController;

Route::group(['prefix' => config('cms.route_prefix'), 'middleware' => config('cms.middleware')], function () {
    // Toolbar API routes
    Route::get('/api/pages', [ToolbarController::class, 'getPages'])->name('cms.api.pages');
    Route::get('/api/languages', [ToolbarController::class, 'getLanguages'])->name('cms.api.languages');
    Route::get('/api/settings', [ToolbarController::class, 'getSettings'])->name('cms.api.settings');
    Route::post('/api/settings', [ToolbarController::class, 'updateSettings'])->name('cms.api.settings.update');
    Route::get('/api/template-items', [ToolbarController::class, 'getTemplateItems'])->name('cms.api.template-items');
});