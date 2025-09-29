<?php

namespace Webook\LaravelCMS\Contracts;

/**
 * Permission Manager Interface
 */
interface PermissionManagerInterface
{
    public function check(string $permission = null): bool;
    public function userCan($user, string $permission): bool;
}