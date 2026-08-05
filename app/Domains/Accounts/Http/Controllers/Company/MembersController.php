<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Application\MemberService;
use App\Domains\Accounts\Http\Requests\DeleteMemberRequest;
use App\Domains\Accounts\Http\Requests\MemberRequest;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Domains\Accounts\Models\User;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembersController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $user = $request->user();

        $users = User::whereCompany()
            ->applyFilters($request->all())
            ->where('id', '<>', $user->id)
            ->latest()
            ->paginate($limit);

        return UserResource::collection($users)
            ->additional(['meta' => [
                'user_total_count' => User::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(MemberRequest $request)
    {
        $this->authorize('create', User::class);

        $user = $this->memberService->create(
            $request->getUserPayload(),
            $request->validated('companies'),
        );

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(User $member)
    {
        $this->authorize('view', $member);

        return new UserResource($member);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(MemberRequest $request, User $member)
    {
        $this->authorize('update', $member);

        $this->memberService->update(
            $member,
            $request->getUserPayload(),
            $request->validated('companies'),
        );

        return new UserResource($member);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteMemberRequest $request)
    {
        $this->authorize('delete multiple users', User::class);

        if ($request->users) {
            // Scope the candidate ids to members of the acting company so a user
            // from one company cannot delete accounts belonging to another.
            $ids = User::whereCompany()
                ->whereIn('id', $request->users)
                ->pluck('id')
                ->toArray();

            if ($ids) {
                $this->memberService->delete($ids);
            }
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
