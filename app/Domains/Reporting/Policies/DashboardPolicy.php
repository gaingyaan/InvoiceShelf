<?php

namespace App\Domains\Reporting\Policies;

use App\Domains\Accounts\Models\Company;
use App\Domains\Accounts\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class DashboardPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Company $company): bool
    {
        if (BouncerFacade::can('dashboard') && $user->hasCompany($company->id)) {
            return true;
        }

        return false;
    }
}
