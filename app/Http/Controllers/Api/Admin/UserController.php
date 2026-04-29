<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiController
{
    public function index(): JsonResponse
    {
        $users = User::with('roles')->orderBy('name')->get();

        return $this->success($users->map(fn ($u) => [
            'userId'            => $u->id,
            'username'          => $u->username,
            'displayName'       => $u->name,
            'email'             => $u->email,
            'preferredLanguage' => $u->preferred_language_code,
            'isActive'          => $u->is_active,
            'roles'             => $u->roles->pluck('name')->values()->all(),
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username'          => ['required', 'string', 'max:50', 'unique:users,username'],
            'displayName'       => ['required', 'string', 'max:100'],
            'email'             => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'          => ['required', 'string', 'min:6'],
            'preferredLanguage' => ['nullable', 'string', 'max:10'],
        ]);

        $user = User::create([
            'username'                => $validated['username'],
            'name'                    => $validated['displayName'],
            'email'                   => $validated['email'],
            'password'                => Hash::make($validated['password']),
            'preferred_language_code' => $validated['preferredLanguage'] ?? 'pt-PT',
            'is_active'               => true,
        ]);

        return $this->success([
            'userId'      => $user->id,
            'username'    => $user->username,
            'displayName' => $user->name,
        ], status: 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'displayName'       => ['nullable', 'string', 'max:100'],
            'email'             => ['nullable', 'email', 'max:255'],
            'preferredLanguage' => ['nullable', 'string', 'max:10'],
            'isActive'          => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('displayName', $validated))       $update['name'] = $validated['displayName'];
        if (array_key_exists('email', $validated))             $update['email'] = $validated['email'];
        if (array_key_exists('preferredLanguage', $validated)) $update['preferred_language_code'] = $validated['preferredLanguage'];
        if (array_key_exists('isActive', $validated))          $update['is_active'] = $validated['isActive'];

        $user->update($update);

        return $this->success([
            'userId'      => $user->id,
            'username'    => $user->username,
            'displayName' => $user->name,
            'isActive'    => $user->is_active,
        ]);
    }
}
