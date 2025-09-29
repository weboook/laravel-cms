<?php

namespace Webook\LaravelCMS\Services;

use Illuminate\Auth\AuthManager;
use Webook\LaravelCMS\Contracts\PermissionManagerInterface;

class PermissionManager implements PermissionManagerInterface
{
    protected $auth;
    protected $config;

    public function __construct(AuthManager $auth, array $config)
    {
        $this->auth = $auth;
        $this->config = $config;
    }

    public function check(string $permission = null): bool
    {
        if (!$this->config['required']) {
            return true;
        }

        $user = $this->auth->guard($this->config['guard'])->user();
        
        if (!$user) {
            return false;
        }

        return $permission ? $this->userCan($user, $permission) : true;
    }

    public function userCan($user, string $permission): bool
    {
        // Implementation would check user permissions
        // This could integrate with Spatie Permission, custom roles, etc.
        return method_exists($user, 'can') ? $user->can($permission) : true;
    }
}