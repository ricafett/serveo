<?php

namespace Database\Seeders;

use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\TranslationKey;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Users ----
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'                    => 'System Admin',
                'email'                   => 'admin@serveo.local',
                'password'                => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active'               => true,
            ]
        );
        $admin->syncRoles(['ADMIN']);

        $cashier = User::updateOrCreate(
            ['username' => 'cashier1'],
            [
                'name'                    => 'Cashier One',
                'email'                   => 'cashier1@serveo.local',
                'password'                => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active'               => true,
            ]
        );
        $cashier->syncRoles(['CASHIER']);

        $server = User::updateOrCreate(
            ['username' => 'server1'],
            [
                'name'                    => 'Server One',
                'email'                   => 'server1@serveo.local',
                'password'                => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active'               => true,
            ]
        );
        $server->syncRoles(['SERVER']);

        // ---- Venue & service session ----
        $venue = Venue::updateOrCreate(
            ['venue_code' => 'MAIN'],
            ['name' => 'Salão Principal', 'is_active' => true]
        );

        $session = ServiceSession::updateOrCreate(
            ['venue_id' => $venue->id, 'session_label' => now()->toDateString().' DINNER'],
            [
                'session_type' => 'DINNER',
                'starts_at'    => now()->setTime(19, 0),
                'status'       => 'OPEN',
            ]
        );

        // ---- Billing statuses ----
        foreach ([
            ['WAITING',         'À espera',          10],
            ['ACTIVE',          'Ativo',             20],
            ['CHECK_REQUESTED', 'Conta pedida',      30],
            ['PARTIALLY_PAID',  'Parcialmente pago', 40],
            ['CLOSED',          'Fechado',           50],
        ] as [$code, $name, $sort]) {
            BillingStatus::updateOrCreate(
                ['code' => $code],
                ['display_name' => $name, 'sort_order' => $sort, 'is_active' => true]
            );
        }
        $waiting = BillingStatus::where('code', 'WAITING')->first();
        $active  = BillingStatus::where('code', 'ACTIVE')->first();

        // ---- Layout: 2 sections, 2 rows each, 20 seats per row, 10 pairs per row ----
        foreach ([['A', 'Sala A'], ['B', 'Sala B']] as $idx => [$code, $name]) {
            $section = Section::updateOrCreate(
                ['venue_id' => $venue->id, 'section_code' => $code],
                ['name' => $name, 'sort_order' => $idx + 1, 'is_active' => true]
            );

            foreach (['1', '2'] as $rIdx => $rowCode) {
                $row = Row::updateOrCreate(
                    ['section_id' => $section->id, 'row_code' => $rowCode],
                    ['sort_order' => (int) $rowCode, 'is_active' => true]
                );

                // 20 seats
                $seatIds = [];
                for ($n = 1; $n <= 20; $n++) {
                    $seat = Seat::updateOrCreate(
                        ['row_id' => $row->id, 'seat_number' => $n],
                        ['sort_order' => $n, 'is_active' => true]
                    );
                    $seatIds[$n] = $seat->id;
                }
                // 10 pairs (1+2, 3+4, ...)
                for ($p = 1; $p <= 10; $p++) {
                    $a = $seatIds[($p * 2) - 1];
                    $b = $seatIds[$p * 2];
                    SeatPair::updateOrCreate(
                        ['row_id' => $row->id, 'pair_sequence' => $p],
                        ['seat_a_id' => $a, 'seat_b_id' => $b, 'is_active' => true]
                    );
                }
            }
        }

        // ---- Menu ----
        $kitchen = MenuCategory::updateOrCreate(
            ['code' => 'MAIN'],
            ['display_name' => 'Pratos principais', 'route_type' => 'KITCHEN', 'sort_order' => 10, 'is_active' => true]
        );
        $starter = MenuCategory::updateOrCreate(
            ['code' => 'STARTER'],
            ['display_name' => 'Entradas', 'route_type' => 'KITCHEN', 'sort_order' => 5, 'is_active' => true]
        );
        $bar = MenuCategory::updateOrCreate(
            ['code' => 'BAR'],
            ['display_name' => 'Bar', 'route_type' => 'BAR', 'sort_order' => 20, 'is_active' => true]
        );
        $dessert = MenuCategory::updateOrCreate(
            ['code' => 'DESSERT'],
            ['display_name' => 'Sobremesas', 'route_type' => 'KITCHEN', 'sort_order' => 30, 'is_active' => true]
        );

        $menu = [
            [$starter, 'Sopa do dia', 4.50],
            [$starter, 'Salada mista', 6.00],
            [$kitchen, 'Bacalhau à brás', 14.50],
            [$kitchen, 'Bitoque', 12.00],
            [$kitchen, 'Polvo à lagareiro', 18.00],
            [$bar,     'Água 50cl', 1.50],
            [$bar,     'Cerveja imperial', 2.00],
            [$bar,     'Vinho tinto - copo', 2.50],
            [$bar,     'Café', 1.20],
            [$dessert, 'Pudim flan', 3.50],
            [$dessert, 'Arroz doce', 3.00],
        ];
        foreach ($menu as [$cat, $name, $price]) {
            MenuItem::updateOrCreate(
                ['display_name' => $name],
                [
                    'menu_category_id' => $cat->id,
                    'unit_price'       => $price,
                    'is_active'        => true,
                ]
            );
        }

        // ---- Printers ----
        $kitchenPrinter = Printer::updateOrCreate(
            ['name' => 'Cozinha LAN'],
            ['printer_type' => 'KITCHEN', 'connection_type' => 'LAN', 'address' => '192.168.1.50', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );
        $barPrinter = Printer::updateOrCreate(
            ['name' => 'Bar LAN'],
            ['printer_type' => 'BAR', 'connection_type' => 'LAN', 'address' => '192.168.1.51', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );
        $billPrinter = Printer::updateOrCreate(
            ['name' => 'Caixa 1'],
            ['printer_type' => 'BILL', 'connection_type' => 'LAN', 'address' => '192.168.1.60', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );

        // ---- Printer routes ----
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'KITCHEN'],
            ['printer_id' => $kitchenPrinter->id, 'is_active' => true]
        );
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'BAR'],
            ['printer_id' => $barPrinter->id, 'is_active' => true]
        );
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'VOID_SLIP', 'fulfillment_route' => 'KITCHEN'],
            ['printer_id' => $kitchenPrinter->id, 'is_active' => true]
        );
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'VOID_SLIP', 'fulfillment_route' => 'BAR'],
            ['printer_id' => $barPrinter->id, 'is_active' => true]
        );

        // ---- Cashier printer assignment ----
        CashierPrinterAssignment::updateOrCreate(
            ['user_id' => $cashier->id, 'printer_id' => $billPrinter->id],
            ['is_active' => true]
        );

        // ---- Sample open billing group spanning two zones ----
        $rowA1 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '1')->first();
        $rowA2 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '2')->first();

        $group = BillingGroup::updateOrCreate(
            ['service_session_id' => $session->id, 'display_code' => 'G-001'],
            [
                'billing_status_id' => $active->id,
                'cover_count'       => 6,
                'opened_by_user_id' => $server->id,
                'opened_at'         => now(),
                'is_closed'         => false,
                'version_number'    => 1,
            ]
        );

        OccupiedZone::updateOrCreate(
            ['billing_group_id' => $group->id, 'row_id' => $rowA1->id, 'start_seat_pair_sequence' => 1, 'end_seat_pair_sequence' => 2],
            [
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => 'Sala A · linha 1 · centro',
                'opened_at'             => now(),
                'is_open'               => true,
                'created_by_user_id'    => $server->id,
            ]
        );
        OccupiedZone::updateOrCreate(
            ['billing_group_id' => $group->id, 'row_id' => $rowA2->id, 'start_seat_pair_sequence' => 3, 'end_seat_pair_sequence' => 3],
            [
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => 'Sala A · linha 2 · centro',
                'opened_at'             => now(),
                'is_open'               => true,
                'created_by_user_id'    => $server->id,
            ]
        );

        // ---- Translations (essential keys) ----
        $translations = [
            ['pt-PT', 'app',   'name',    'Serveo'],
            ['pt-PT', 'floor', 'title',   'Plano de sala'],
            ['pt-PT', 'floor', 'free',    'Livre'],
            ['pt-PT', 'floor', 'busy',    'Ocupado'],
            ['pt-PT', 'order', 'submit',  'Enviar pedido'],
            ['pt-PT', 'bill',  'print',   'Imprimir conta'],
            ['en-US', 'app',   'name',    'Serveo'],
            ['en-US', 'floor', 'title',   'Floor map'],
            ['en-US', 'floor', 'free',    'Free'],
            ['en-US', 'floor', 'busy',    'Busy'],
            ['en-US', 'order', 'submit',  'Submit order'],
            ['en-US', 'bill',  'print',   'Print bill'],
        ];
        foreach ($translations as [$lang, $ns, $key, $val]) {
            TranslationKey::updateOrCreate(
                ['language_code' => $lang, 'translation_namespace' => $ns, 'translation_key' => $key],
                ['translation_value' => $val, 'is_active' => true]
            );
        }
    }
}
