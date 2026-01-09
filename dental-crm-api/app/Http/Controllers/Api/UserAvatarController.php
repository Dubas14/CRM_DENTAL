<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserAvatarController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'], // 10MB
            'remove' => ['nullable', 'boolean'],
        ], [
            'avatar.image' => 'Файл повинен бути зображенням.',
            'avatar.mimes' => 'Файл повинен мати формат: jpeg, jpg, png, gif або webp.',
            'avatar.max' => 'Розмір файлу не повинен перевищувати 10MB.',
        ]);

        // Remove avatar
        if ($request->boolean('remove')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = null;
            $user->save();

            return response()->json([
                'status' => 'ok',
                'avatar_url' => null,
            ]);
        }

        // Upload new avatar
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $path = $request->file('avatar')->store('user-avatars', 'public');
            $user->avatar_path = $path;
            $user->save();

            return response()->json([
                'status' => 'ok',
                'avatar_url' => $user->avatar_url,
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'avatar_url' => $user->avatar_url,
        ]);
    }
}
