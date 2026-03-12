<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
        ]);

        $user->fill($validated);
        $user->save();

        return response()->json($user);
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $this->deleteAvatarFileIfPresent($user->avatar);

        $path = $validated['avatar']->storePublicly('avatars', 'public');
        $user->avatar = '/storage/'.$path;
        $user->save();

        return response()->json($user);
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        $this->deleteAvatarFileIfPresent($user->avatar);

        $user->avatar = null;
        $user->save();

        return response()->json($user);
    }

    private function deleteAvatarFileIfPresent(?string $avatar): void
    {
        if (!$avatar) {
            return;
        }

        if (str_starts_with($avatar, '/storage/')) {
            $relative = substr($avatar, strlen('/storage/'));
            Storage::disk('public')->delete($relative);
        }
    }
}

