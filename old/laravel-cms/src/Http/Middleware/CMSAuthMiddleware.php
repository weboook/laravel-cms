<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webook\LaravelCMS\Services\PermissionManager;

class CMSAuthMiddleware
{
    protected $permissionManager;

    public function __construct(PermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->permissionManager->check()) {
            abort(403, 'Unauthorized to access CMS');
        }

        return $next($request);
    }
}