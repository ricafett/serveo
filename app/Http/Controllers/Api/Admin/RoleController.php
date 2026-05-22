<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends ApiController
{
    public function index(): JsonResponse
    {
        $roles = Role::all();

        return $this->success($roles->map(fn ($r) => [
            'roleId' => $r->id,
            'name' => $r->name,
        ])->all());
    }

    public function assign(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $role = Role::findByName($validated['role']);
        $user->assignRole($role);

        return $this->success([
            'userId' => $user->id,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ]);
    }

    public function updateAssignment(Request $request, User $user, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user->removeRole($role);
        $user->assignRole($validated['role']);

        return $this->success([
            'userId' => $user->id,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ]);
    }
}
