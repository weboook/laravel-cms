<?php

namespace Webook\LaravelCMS\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

class CMSPolicy
{
    use HandlesAuthorization;

    public function manage($user)
    {
        // Implementation would check if user can manage CMS
        return method_exists($user, 'can') ? $user->can('manage-cms') : false;
    }
}