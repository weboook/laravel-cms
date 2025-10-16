<?php

use Illuminate\Support\Facades\Route;

// CMS web routes (if needed for future features)
Route::group(['prefix' => config('cms.route_prefix'), 'middleware' => config('cms.middleware')], function () {
    // Protected web routes will be added here
});

// Test routes for CMS features (only when features are enabled)
if (config('cms.features.component_source_mapping')) {
    Route::get('/cms-test/component-mapping', function () {
        return view('examples.component-test');
    });
}

Route::get('/cms-test/translation-conversion', function () {
    return view('examples.translation-conversion-test');
});