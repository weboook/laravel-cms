<?php

namespace Webook\LaravelCMS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Webook\LaravelCMS\Services\PermissionManager;

class CMSPermissionMiddleware
{
    protected $permissionManager;

    public function __construct(PermissionManager $permissionManager)
    {
        $this->permissionManager = $permissionManager;
    }

    public function handle(Request $request, Closure $next, string $permission = null)
    {
        if (!$this->permissionManager->check($permission)) {
            abort(403, "Insufficient permissions: {$permission}");
        }

        return $next($request);
    }
}