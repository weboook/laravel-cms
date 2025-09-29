<?php

use Illuminate\Support\Facades\Route;

// CMS web routes (if needed for future features)
Route::group(['prefix' => config('cms.route_prefix'), 'middleware' => config('cms.middleware')], function () {
    // Protected web routes will be added here
});