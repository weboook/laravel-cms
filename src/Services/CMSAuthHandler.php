<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Http\Request;

class CMSAuthHandler
{
    /**
     * Determine if the current user can access the CMS editor
     *
     * @param Request $request
     * @return bool
     */
    public function canAccessCMS(Request $request): bool
    {
        // Check for custom authorization handler
        $customHandler = config('cms.auth.handler');

        if ($customHandler && is_callable($customHandler)) {
            return call_user_func($customHandler, $request);
        }

        // Check for specific auth guards
        $guards = config('cms.auth.guards', []);
        if (!empty($guards)) {
            foreach ($guards as $guard) {
                if (auth()->guard($guard)->check()) {
                    return $this->checkUserPermissions($request, auth()->guard($guard)->user());
                }
            }
            return false;
        }

        // Check for default auth
        if (config('cms.auth.enabled', false)) {
            if (!auth()->check()) {
                return false;
            }

            return $this->checkUserPermissions($request, auth()->user());
        }

        // By default, allow access if no auth is configured
        return true;
    }

    /**
     * Check if a specific user has the required permissions
     *
     * @param Request $request
     * @param mixed $user
     * @return bool
     */
    protected function checkUserPermissions(Request $request, $user): bool
    {
        // Check for required roles
        $requiredRoles = config('cms.auth.roles', []);
        if (!empty($requiredRoles)) {
            if (method_exists($user, 'hasRole')) {
                foreach ($requiredRoles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
                return false;
            }

            // Check for role property
            if (property_exists($user, 'role') || method_exists($user, 'getRole')) {
                $userRole = property_exists($user, 'role') ? $user->role : $user->getRole();
                return in_array($userRole, $requiredRoles);
            }
        }

        // Check for required permissions
        $requiredPermissions = config('cms.auth.permissions', []);
        if (!empty($requiredPermissions)) {
            if (method_exists($user, 'can')) {
                foreach ($requiredPermissions as $permission) {
                    if (!$user->can($permission)) {
                        return false;
                    }
                }
                return true;
            }
        }

        // Check for user IDs whitelist
        $allowedUserIds = config('cms.auth.user_ids', []);
        if (!empty($allowedUserIds)) {
            return in_array($user->id, $allowedUserIds);
        }

        // Check for email domains
        $allowedDomains = config('cms.auth.email_domains', []);
        if (!empty($allowedDomains) && !empty($user->email)) {
            $emailDomain = substr(strrchr($user->email, "@"), 1);
            return in_array($emailDomain, $allowedDomains);
        }

        // Check for custom user check
        $customUserCheck = config('cms.auth.user_check');
        if ($customUserCheck && is_callable($customUserCheck)) {
            return call_user_func($customUserCheck, $user);
        }

        // If auth is enabled but no specific rules, allow any authenticated user
        return true;
    }

    /**
     * Check if CMS should be visible on the current route
     *
     * @param Request $request
     * @return bool
     */
    public function shouldShowOnRoute(Request $request): bool
    {
        // First check if user can access CMS at all
        if (!$this->canAccessCMS($request)) {
            return false;
        }

        // Check for route-specific restrictions
        $currentRoute = $request->route();
        if (!$currentRoute) {
            return true;
        }

        // Check excluded routes
        $excludedRoutes = config('cms.auth.excluded_routes', []);
        if (in_array($currentRoute->getName(), $excludedRoutes)) {
            return false;
        }

        // Check excluded paths
        $excludedPaths = config('cms.auth.excluded_paths', []);
        $currentPath = $request->path();
        foreach ($excludedPaths as $pattern) {
            if (fnmatch($pattern, $currentPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the current user's CMS permissions level
     *
     * @param Request $request
     * @return string
     */
    public function getPermissionLevel(Request $request): string
    {
        if (!$this->canAccessCMS($request)) {
            return 'none';
        }

        // Check for custom permission level handler
        $customLevelHandler = config('cms.auth.permission_level_handler');
        if ($customLevelHandler && is_callable($customLevelHandler)) {
            return call_user_func($customLevelHandler, $request);
        }

        // Check for admin users
        $adminUserIds = config('cms.auth.admin_user_ids', []);
        if (auth()->check() && in_array(auth()->id(), $adminUserIds)) {
            return 'admin';
        }

        // Check for editor role
        if (auth()->check()) {
            $user = auth()->user();

            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
                    return 'admin';
                }
                if ($user->hasRole('editor')) {
                    return 'editor';
                }
            }
        }

        // Default to editor level for authenticated users
        return 'editor';
    }
}