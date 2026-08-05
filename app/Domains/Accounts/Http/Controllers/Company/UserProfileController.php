<?php

namespace App\Domains\Accounts\Http\Controllers\Company;

use App\Domains\Accounts\Contracts\UserAvatarManager;
use App\Domains\Accounts\Http\Requests\AvatarRequest;
use App\Domains\Accounts\Http\Requests\GetSettingsRequest;
use App\Domains\Accounts\Http\Requests\ProfileRequest;
use App\Domains\Accounts\Http\Requests\UpdateSettingsRequest;
use App\Domains\Accounts\Http\Resources\UserResource;
use App\Platform\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function __construct(
        private readonly UserAvatarManager $userAvatarManager,
    ) {}

    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(ProfileRequest $request)
    {
        $user = $request->user();

        $user->update($request->validated());

        return new UserResource($user);
    }

    public function uploadAvatar(AvatarRequest $request)
    {
        $user = auth()->user();

        if (isset($request->is_admin_avatar_removed) && (bool) $request->is_admin_avatar_removed) {
            $this->userAvatarManager->clear($user);
        }
        if ($user && $request->hasFile('admin_avatar')) {
            $file = $request->file('admin_avatar');
            $this->userAvatarManager->replaceFile(
                $user,
                $file->getRealPath(),
                $file->getClientOriginalName(),
            );
        }

        if ($user && $request->has('avatar')) {
            $data = json_decode($request->avatar);
            $this->userAvatarManager->replaceBase64($user, $data->data, $data->name);
        }

        return new UserResource($user);
    }

    public function showSettings(GetSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user->getSettings((array) $request->settings));
    }

    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->setSettings($request->settings);

        return response()->json([
            'success' => true,
        ]);
    }
}
