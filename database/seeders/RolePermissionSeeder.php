<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Permission catalog — aligned with docs/spec/role-permissions.md.
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
            'payment.view',
            // Sales
            'sale.create',
            'sale.view',
            'sale.print',
            'sale.receipt',
            'sale_payment.record',
            // Print jobs
            'print_job.view',
            'print_job.retry',
            // Printer configuration
            'printer.configure',
            'printer.test',
            'printer.route_change',
            // Venue and layout
            'venue.configure',
            // Menu
            'menu.manage',
            // Billing statuses
            'status.configure',
            // Users and roles
            'user.manage',
            'role.manage',
            // Translations
            'translation.manage',
            // Audit and export
            'audit.view',
            'event_log.view_limited',
            'event_log.view_full',
            'accounting_export.generate',
            // Legacy config aliases (kept for backward compatibility)
            'config.users',
            'config.layout',
            'config.menu',
            'config.printers',
            'config.billing_statuses',
            'config.translations',
            'export.create',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm);
        }

        $admin = Role::findOrCreate('ADMIN');
        $admin->syncPermissions(Permission::all());

        $server = Role::findOrCreate('SERVER');
        $server->syncPermissions([
            'floor.view',
            'floor.open_billing_group',
            'floor.assign_zone',
            'floor.release_zone',
            'billing_group.view',
            'billing_group.reopen',
            'order.create',
            'order.void_item',
            'production_ticket.view',
            'audit.view',
            'event_log.view_limited',
        ]);

        $cashier = Role::findOrCreate('CASHIER');
        $cashier->syncPermissions([
            'floor.view',
            'floor.open_billing_group',
            'floor.assign_zone',
            'billing_group.view',
            'billing_group.set_status',
            'billing_group.reopen',
            'order.create',
            'order.void_item',
            'billing_document.create',
            'billing_document.reprint',
            'floor.release_zone',
            'payment.record',
            'payment.void',
            'sale.create',
            'sale.view',
            'sale.print',
            'sale.receipt',
            'sale_payment.record',
            'print_job.view',
            'print_job.retry',
            'audit.view',
            'event_log.view_limited',
        ]);

        // Non-interactive output roles — exist for audit/routing semantics.
        Role::findOrCreate('KITCHEN_OUTPUT');
        Role::findOrCreate('BAR_OUTPUT');
    }
}
