<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => config('cms.route_prefix'), 'middleware' => config('cms.middleware')], function () {
    // CMS routes will be defined here
});