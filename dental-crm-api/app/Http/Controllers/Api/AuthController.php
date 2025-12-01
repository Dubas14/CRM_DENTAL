<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Валідація даних
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Шукаємо користувача
        $user = User::where('email', $request->email)->first();

        // 3. Перевіряємо пароль
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Невірний логін або пароль.'],
            ]);
        }

        // 4. Створюємо токен (це ключ доступу)
        // 'web-app' — це просто назва токена, може бути будь-яка
        $token = $user->createToken('web-app')->plainTextToken;

        // 5. Віддаємо відповідь фронтенду
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        // Видаляємо токен при виході
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request)
    {
        return $request->user();
    }
}
