<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'language' => ['nullable', 'string'],
        ]);

        $user = User::where('username', $request->input('username'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return $this->error('UNAUTHENTICATED', 'Invalid credentials.', status: 401);
        }

        if (! $user->is_active) {
            return $this->error('FORBIDDEN', 'Account is inactive.', status: 403);
        }

        Auth::login($user);

        if ($request->filled('language')) {
            $user->update(['preferred_language_code' => $request->input('language')]);
        }

        $user->update(['last_login_at' => now()]);

        return $this->success([
            'user' => [
                'id'                => $user->id,
                'displayName'       => $user->name,
                'roles'             => $user->roles->pluck('name')->values()->all(),
                'preferredLanguage' => $user->preferred_language_code,
            ],
            'session' => [
                'token'     => $request->session()->getId(),
                'expiresAt' => now()->addHours(12)->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();

        return $this->success();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id'                => $user->id,
            'displayName'       => $user->name,
            'roles'             => $user->roles->pluck('name')->values()->all(),
            'preferredLanguage' => $user->preferred_language_code,
            'permissions'       => $user->permissions->pluck('name')->values()->all(),
        ]);
    }
}
