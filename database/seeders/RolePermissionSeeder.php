<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Permission catalog - grouped by domain.
        $permissions = [
            // Floor and occupancy
            'floor.view',
            'floor.open_billing_group',
            'floor.assign_zone',
            'floor.release_zone',
            // Billing-group lifecycle
            'billing_group.view',
            'billing_group.set_status',
            'billing_group.reopen',
            // Orders
            'order.create',
            'order.void_item',
            // Production tickets
            'production_ticket.view',
            'production_ticket.reprint',
            // Billing documents
            'billing_document.create',
            'billing_document.reprint',
            // Payments
            'payment.record',
            'payment.void',
            // Print jobs
            'print_job.view',
            'print_job.retry',
            // Configuration
            'config.users',
            'config.layout',
            'config.menu',
            'config.printers',
            'config.billing_statuses',
            'config.translations',
            // Audit & export
            'audit.view',
            'export.create',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm);
        }

        $admin = Role::findOrCreate('ADMIN');
        $admin->syncPermissions(Permission::all());

        $server = Role::findOrCreate('SERVER');
        $server->syncPermissions([
            'floor.view', 'floor.open_billing_group', 'floor.assign_zone', 'floor.release_zone',
            'billing_group.view',
            'order.create', 'order.void_item',
            'production_ticket.view',
            'audit.view',
        ]);

        $cashier = Role::findOrCreate('CASHIER');
        $cashier->syncPermissions([
            'billing_group.view', 'billing_group.set_status', 'billing_group.reopen',
            'billing_document.create', 'billing_document.reprint',
            'payment.record', 'payment.void',
            'print_job.view', 'print_job.retry',
            'audit.view',
        ]);

        // Non-interactive output roles - exist for audit/routing semantics.
        Role::findOrCreate('KITCHEN_OUTPUT');
        Role::findOrCreate('BAR_OUTPUT');
    }
}
