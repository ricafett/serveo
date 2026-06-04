<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permissions are seeded once and remain for backward compatibility.
        // No destructive down() is needed; removing permissions would break
        // role assignments for existing users.
    }
};
