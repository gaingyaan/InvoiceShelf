<?php

namespace App\Domains\Reporting\Http\Controllers\Company;

use App\Domains\Accounts\Models\User;
use App\Domains\Contacts\Models\Customer;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SearchController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $customers = Customer::applyFilters($request->only(['search']))
            ->whereCompany()
            ->latest()
            ->paginate(10);

        if ($user->isOwner()) {
            $users = User::whereCompany()
                ->applyFilters($request->only(['search']))
                ->latest()
                ->paginate(10);
        }

        return response()->json([
            'customers' => $customers,
            'users' => $users ?? [],
        ]);
    }

    public function users(Request $request)
    {
        $this->authorize('create', User::class);

        $users = User::whereEmail($request->email)
            ->latest()
            ->paginate(10);

        return response()->json(['users' => $users]);
    }
}
