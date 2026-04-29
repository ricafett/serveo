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
        'view_floor', 'create_billing_group', 'submit_order', 'reopen_billing_group',
        'view_audit', 'generate_internal_bill', 'reprint_bill', 'record_payment', 'retry_print_job',
    ] as $perm) {
        Permission::findOrCreate($perm);
    }
    foreach (['ADMIN', 'SERVER', 'CASHIER', 'KITCHEN_OUTPUT', 'BAR_OUTPUT'] as $r) {
        Role::findOrCreate($r);
    }
    Role::findByName('ADMIN')->syncPermissions(Permission::all());
    Role::findByName('SERVER')->syncPermissions(['view_floor', 'create_billing_group', 'submit_order', 'reopen_billing_group', 'view_audit']);
    Role::findByName('CASHIER')->syncPermissions(['view_floor', 'generate_internal_bill', 'reprint_bill', 'record_payment', 'retry_print_job', 'view_audit']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        ['WAITING', 'À espera', 10],
        ['ACTIVE', 'Ativo', 20],
        ['CHECK_REQUESTED', 'Conta pedida', 30],
        ['PARTIALLY_PAID', 'Parcialmente pago', 40],
        ['CLOSED', 'Fechado', 50],
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
