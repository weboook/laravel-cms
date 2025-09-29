<?php

namespace Webook\LaravelCMS\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base Controller for Laravel CMS
 *
 * Provides common functionality for all CMS controllers
 * including authorization and validation traits.
 */
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}