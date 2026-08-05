<?php

namespace App\Platform\Ai\Policies;

use App\Domains\Accounts\Models\User;

class AiAccessPolicy
{
    public function manageConfiguration(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Feature configuration applies the instance and company kill switches.
     */
    public function use(User $user): bool
    {
        return true;
    }
}
