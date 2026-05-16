<?php

use App\Models\BillingStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\Row;
use App\Models\Section;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Boots roles, permissions and a baseline venue/session/menu/printer setup.
 * Returns the active ServiceSession.
 */
function bootScenario(): ServiceSession
{
    foreach ([
        'floor.view', 'floor.open_billing_group', 'floor.assign_zone', 'floor.release_zone',
        'billing_group.view', 'billing_group.set_status', 'billing_group.reopen',
        'order.create', 'order.void_item',
        'production_ticket.view', 'production_ticket.reprint',
        'billing_document.create', 'billing_document.reprint',
        'payment.record', 'payment.void',
        'print_job.view', 'print_job.retry',
        'printer.configure', 'printer.test', 'printer.route_change',
        'venue.configure', 'menu.manage', 'status.configure',
        'user.manage', 'role.manage', 'translation.manage',
        'audit.view', 'event_log.view_limited', 'event_log.view_full',
        'accounting_export.generate',
        'config.users', 'config.layout', 'config.menu', 'config.printers',
        'config.billing_statuses', 'config.translations', 'export.create',
    ] as $perm) {
        Permission::findOrCreate($perm);
    }
    foreach (['ADMIN', 'SERVER', 'CASHIER', 'KITCHEN_OUTPUT', 'BAR_OUTPUT'] as $r) {
        Role::findOrCreate($r);
    }
    Role::findByName('ADMIN')->syncPermissions(Permission::all());
    Role::findByName('SERVER')->syncPermissions([
        'floor.view', 'floor.open_billing_group', 'floor.assign_zone', 'floor.release_zone',
        'billing_group.view', 'billing_group.reopen',
        'order.create', 'order.void_item',
        'production_ticket.view',
        'audit.view',
    ]);
    Role::findByName('CASHIER')->syncPermissions([
        'billing_group.view', 'billing_group.set_status', 'billing_group.reopen',
        'billing_document.create', 'billing_document.reprint',
        'payment.record', 'payment.void',
        'print_job.view', 'print_job.retry',
        'audit.view',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // MVP: only ACTIVE and CLOSED statuses. Admin may add more later.
    foreach ([
        ['ACTIVE', 'Ativo',   10],
        ['CLOSED', 'Fechado', 20],
    ] as [$c, $d, $o]) {
        BillingStatus::firstOrCreate(['code' => $c], ['display_name' => $d, 'sort_order' => $o, 'is_active' => true]);
    }

    $venue = Venue::firstOrCreate(['venue_code' => 'MAIN'], ['name' => 'Salão Principal', 'is_active' => true]);

    $section = Section::firstOrCreate(
        ['venue_id' => $venue->id, 'section_code' => 'A'],
        ['name' => 'Sala A', 'sort_order' => 1, 'is_active' => true],
    );
    $row = Row::firstOrCreate(
        ['section_id' => $section->id, 'row_code' => '1'],
        ['sort_order' => 1, 'is_active' => true],
    );

    $seatIds = [];
    for ($n = 1; $n <= 20; $n++) {
        $seatIds[$n] = Seat::firstOrCreate(
            ['row_id' => $row->id, 'seat_number' => $n],
            ['sort_order' => $n, 'is_active' => true]
        )->id;
    }
    for ($p = 1; $p <= 10; $p++) {
        SeatPair::firstOrCreate(
            ['row_id' => $row->id, 'pair_sequence' => $p],
            ['seat_a_id' => $seatIds[$p * 2 - 1], 'seat_b_id' => $seatIds[$p * 2], 'is_active' => true],
        );
    }

    $kitchen = MenuCategory::firstOrCreate(['code' => 'MAIN'], [
        'display_name' => 'Pratos principais', 'route_type' => 'KITCHEN', 'sort_order' => 10, 'is_active' => true,
    ]);
    $bar = MenuCategory::firstOrCreate(['code' => 'BAR'], [
        'display_name' => 'Bar', 'route_type' => 'BAR', 'sort_order' => 20, 'is_active' => true,
    ]);
    MenuItem::firstOrCreate(['display_name' => 'Bacalhau'], [
        'menu_category_id' => $kitchen->id, 'unit_price' => 18.00, 'is_active' => true,
    ]);
    MenuItem::firstOrCreate(['display_name' => 'Vinho copo'], [
        'menu_category_id' => $bar->id, 'unit_price' => 5.00, 'is_active' => true,
    ]);

    $kPrinter = Printer::firstOrCreate(['name' => 'Cozinha LAN'], [
        'printer_type' => 'KITCHEN', 'connection_type' => 'NULL',
        'address' => '127.0.0.1', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN',
    ]);
    $bPrinter = Printer::firstOrCreate(['name' => 'Bar LAN'], [
        'printer_type' => 'BAR', 'connection_type' => 'NULL',
        'address' => '127.0.0.1', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN',
    ]);
    $cPrinter = Printer::firstOrCreate(['name' => 'Caixa 1'], [
        'printer_type' => 'BILL', 'connection_type' => 'NULL',
        'address' => '127.0.0.1', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN',
    ]);

    PrinterRoute::firstOrCreate([
        'venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'KITCHEN',
    ], ['printer_id' => $kPrinter->id, 'is_active' => true]);
    PrinterRoute::firstOrCreate([
        'venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'BAR',
    ], ['printer_id' => $bPrinter->id, 'is_active' => true]);
    PrinterRoute::firstOrCreate([
        'venue_id' => $venue->id, 'document_type' => 'VOID_SLIP', 'fulfillment_route' => 'KITCHEN',
    ], ['printer_id' => $kPrinter->id, 'is_active' => true]);
    PrinterRoute::firstOrCreate([
        'venue_id' => $venue->id, 'document_type' => 'VOID_SLIP', 'fulfillment_route' => 'BAR',
    ], ['printer_id' => $bPrinter->id, 'is_active' => true]);

    return ServiceSession::firstOrCreate(
        ['venue_id' => $venue->id, 'session_label' => 'Test session'],
        ['session_type' => 'DINNER', 'starts_at' => now(), 'status' => 'OPEN'],
    );
}

function makeUser(string $role, ?string $username = null): User
{
    $username ??= strtolower($role).'-'.bin2hex(random_bytes(3));
    $user = User::create([
        'username'  => $username,
        'name'      => ucfirst(strtolower($role)).' User',
        'email'     => $username.'@example.test',
        'password'  => Hash::make('secret'),
        'is_active' => true,
        'preferred_language_code' => 'pt-PT',
    ]);
    $user->assignRole($role);
    return $user;
}

function createBillingGroup(ServiceSession $session, User $server, array $overrides = []): \App\Models\BillingGroup
{
    $status = \App\Models\BillingStatus::where('code', 'ACTIVE')->first();

    $group = \App\Models\BillingGroup::create(array_merge([
        'service_session_id' => $session->id,
        'opened_by_user_id' => $server->id,
        'display_code' => 'BG-' . bin2hex(random_bytes(2)),
        'billing_status_id' => $status?->id,
        'opened_at' => now(),
    ], $overrides));

    return $group;
}
