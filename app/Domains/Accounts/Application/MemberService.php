<?php

namespace App\Domains\Accounts\Application;

use App\Domains\Accounts\Contracts\MemberReferencesCleaner;
use App\Domains\Accounts\Models\User;
use Silber\Bouncer\BouncerFacade;

class MemberService
{
    public function __construct(
        private readonly MemberReferencesCleaner $memberReferencesCleaner,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  iterable<int, array{id: int, role: string}>  $companies
     */
    public function create(array $attributes, iterable $companies): User
    {
        $user = User::create($attributes);

        $user->setSettings([
            'language' => 'default',
        ]);

        $companies = collect($companies);
        $user->companies()->sync($companies->pluck('id'));

        foreach ($companies as $company) {
            BouncerFacade::scope()->to($company['id']);

            BouncerFacade::sync($user)->roles([$company['role']]);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  iterable<int, array{id: int, role: string}>  $companies
     */
    public function update(User $user, array $attributes, iterable $companies): User
    {
        $user->update($attributes);

        $companies = collect($companies);
        $user->companies()->sync($companies->pluck('id'));

        foreach ($companies as $company) {
            BouncerFacade::scope()->to($company['id']);

            BouncerFacade::sync($user)->roles([$company['role']]);
        }

        return $user;
    }

    public function delete(array $ids): bool
    {
        foreach ($ids as $id) {
            $user = User::find($id);

            if (! $user) {
                continue;
            }

            $this->memberReferencesCleaner->clear($user);

            if ($user->settings()->exists()) {
                $user->settings()->delete();
            }

            $user->delete();
        }

        return true;
    }
}
