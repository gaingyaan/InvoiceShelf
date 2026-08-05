<?php

namespace App\Adapters\Accounts;

use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Models\User;

class EloquentMemberReferencesCleaner implements MemberReferencesCleaner
{
    public function clear(User $user): void
    {
        $user->invoices()->update(['creator_id' => null]);
        $user->estimates()->update(['creator_id' => null]);
        $user->customers()->update(['creator_id' => null]);
        $user->recurringInvoices()->update(['creator_id' => null]);
        $user->expenses()->update(['creator_id' => null]);
        $user->payments()->update(['creator_id' => null]);
        $user->items()->update(['creator_id' => null]);
    }
}
