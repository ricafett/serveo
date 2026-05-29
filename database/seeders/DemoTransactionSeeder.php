<?php

namespace Database\Seeders;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\CashierPrinterAssignment;
use App\Models\FulfillmentRoute;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierSet;
use App\Models\ModifierSetItem;
use App\Models\OccupiedZone;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;
use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::where('venue_code', 'MAIN')->first();
        if (! $venue) {
            $this->command?->warn('Venue not found. Run CoreSeeder first.');

            return;
        }

        // ---- Users ----
        $cashier = User::firstOrCreate(
            ['username' => 'cashier1'],
            [
                'name' => 'Cashier One',
                'email' => 'cashier1@serveo.local',
                'password' => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active' => true,
            ]
        );
        $cashier->syncRoles(['CASHIER']);

        $server = User::firstOrCreate(
            ['username' => 'server1'],
            [
                'name' => 'Server One',
                'email' => 'server1@serveo.local',
                'password' => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active' => true,
            ]
        );
        $server->syncRoles(['SERVER']);

        // ---- Service session ----
        $session = ServiceSession::updateOrCreate(
            ['venue_id' => $venue->id, 'session_label' => 'DEFAULT'],
            [
                'session_type' => 'DINNER',
                'starts_at' => now()->setTime(19, 0),
                'status' => 'OPEN',
            ]
        );

        $active = BillingStatus::where('code', 'ACTIVE')->first();

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

                $seatIds = [];
                for ($n = 1; $n <= 20; $n++) {
                    $seat = Seat::updateOrCreate(
                        ['row_id' => $row->id, 'seat_number' => $n],
                        ['sort_order' => $n, 'is_active' => true]
                    );
                    $seatIds[$n] = $seat->id;
                }
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
                    'unit_price' => $price,
                    'is_active' => true,
                ]
            );
        }

        // ---- Modifier sets ----
        $tempModSet = ModifierSet::updateOrCreate(
            ['display_name' => 'Temperatura'],
            ['selection_mode' => 'single', 'sort_order' => 10, 'is_active' => true]
        );
        foreach (['Fresca', 'Natural'] as $i => $name) {
            ModifierSetItem::updateOrCreate(
                ['modifier_set_id' => $tempModSet->id, 'display_name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }

        $extrasModSet = ModifierSet::updateOrCreate(
            ['display_name' => 'Extras'],
            ['selection_mode' => 'multiple', 'sort_order' => 20, 'is_active' => true]
        );
        foreach (['Queijo extra', 'Bacon extra', 'Molho picante'] as $i => $name) {
            ModifierSetItem::updateOrCreate(
                ['modifier_set_id' => $extrasModSet->id, 'display_name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }

        MenuItem::whereIn('display_name', ['Cerveja imperial', 'Vinho tinto - copo'])->update(['modifier_set_id' => $tempModSet->id]);

        // ---- Variants ----
        $aguaItem = MenuItem::where('display_name', 'Água 50cl')->first();
        if ($aguaItem) {
            foreach (['c/gás', 's/gás'] as $i => $name) {
                MenuItemVariant::updateOrCreate(
                    ['menu_item_id' => $aguaItem->id, 'display_name' => $name],
                    ['sort_order' => $i + 1, 'is_active' => true]
                );
            }
        }
        $vinhoItem = MenuItem::where('display_name', 'Vinho tinto - copo')->first();
        if ($vinhoItem) {
            foreach (['Casa', 'Reserva'] as $i => $name) {
                MenuItemVariant::updateOrCreate(
                    ['menu_item_id' => $vinhoItem->id, 'display_name' => $name],
                    ['sort_order' => $i + 1, 'is_active' => true]
                );
            }
        }

        // ---- Fulfillment routes ----
        FulfillmentRoute::firstOrCreate(
            ['code' => 'KITCHEN'],
            ['display_name' => 'Cozinha', 'sort_order' => 10, 'is_active' => true]
        );
        FulfillmentRoute::firstOrCreate(
            ['code' => 'BAR'],
            ['display_name' => 'Bar', 'sort_order' => 20, 'is_active' => true]
        );

        // ---- Printers (NULL connection type for test-safe demo) ----
        $kitchenPrinter = Printer::firstOrCreate(
            ['name' => 'Cozinha LAN'],
            ['connection_type' => 'NULL', 'address' => null, 'port' => null, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );
        $barPrinter = Printer::firstOrCreate(
            ['name' => 'Bar LAN'],
            ['connection_type' => 'NULL', 'address' => null, 'port' => null, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );
        $billPrinter = Printer::firstOrCreate(
            ['name' => 'Caixa 1'],
            ['connection_type' => 'NULL', 'address' => null, 'port' => null, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );

        // ---- Printer routes ----
        PrinterRoute::firstOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'KITCHEN'],
            ['printer_id' => $kitchenPrinter->id, 'is_active' => true]
        );
        PrinterRoute::firstOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'BAR'],
            ['printer_id' => $barPrinter->id, 'is_active' => true]
        );

        // ---- Cashier printer assignment ----
        CashierPrinterAssignment::firstOrCreate(
            ['user_id' => $cashier->id, 'printer_id' => $billPrinter->id],
            ['is_active' => true]
        );

        // ---- Sample open billing group spanning two zones ----
        $rowA1 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '1')->first();
        $rowA2 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '2')->first();
        $rowB1 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'B'))->where('row_code', '1')->first();

        $group = BillingGroup::updateOrCreate(
            ['service_session_id' => $session->id, 'display_code' => 'G-001'],
            [
                'billing_status_id' => $active->id,
                'cover_count' => 6,
                'opened_by_user_id' => $server->id,
                'opened_at' => now(),
                'is_closed' => false,
                'version_number' => 1,
            ]
        );

        OccupiedZone::updateOrCreate(
            ['billing_group_id' => $group->id, 'row_id' => $rowA1->id, 'start_seat_pair_sequence' => 1, 'end_seat_pair_sequence' => 2],
            [
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => 'Sala A · linha 1 · centro',
                'opened_at' => now(),
                'is_open' => true,
                'created_by_user_id' => $server->id,
                'server_id' => $server->id,
            ]
        );
        OccupiedZone::updateOrCreate(
            ['billing_group_id' => $group->id, 'row_id' => $rowA2->id, 'start_seat_pair_sequence' => 3, 'end_seat_pair_sequence' => 3],
            [
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => 'Sala A · linha 2 · centro',
                'opened_at' => now(),
                'is_open' => true,
                'created_by_user_id' => $server->id,
                'server_id' => $server->id,
            ]
        );

        $group->favoritedBy()->syncWithoutDetaching([$server->id]);

        SeatPair::where('row_id', $rowA1->id)->update(['default_server_id' => $server->id]);
        SeatPair::where('row_id', $rowA2->id)->update(['default_server_id' => $server->id]);

        // ---- Demo transactions ----
        $zone1 = OccupiedZone::where('billing_group_id', $group->id)->where('row_id', $rowA1->id)->first();
        $zone2 = OccupiedZone::where('billing_group_id', $group->id)->where('row_id', $rowA2->id)->first();

        $bacalhau = MenuItem::where('display_name', 'Bacalhau à brás')->first();
        $bitoque = MenuItem::where('display_name', 'Bitoque')->first();
        $vinho = MenuItem::where('display_name', 'Vinho tinto - copo')->first();
        $cerveja = MenuItem::where('display_name', 'Cerveja imperial')->first();
        $sopa = MenuItem::where('display_name', 'Sopa do dia')->first();
        $pudim = MenuItem::where('display_name', 'Pudim flan')->first();

        $pair2 = SeatPair::where('row_id', $rowA1->id)->where('pair_sequence', 2)->first();
        app(OrderService::class)->submit($group, $server, [
            ['menu_item_id' => $bacalhau->id, 'quantity' => 2, 'delivery_seat_pair_id' => $pair2?->id],
            ['menu_item_id' => $vinho->id, 'quantity' => 4, 'variant_name' => 'Casa'],
            ['menu_item_id' => $sopa->id, 'quantity' => 2],
        ], $zone1);

        app(OrderService::class)->submit($group, $server, [
            ['menu_item_id' => $bitoque->id, 'quantity' => 1],
            ['menu_item_id' => $pudim->id, 'quantity' => 2],
        ], $zone1);

        app(OrderService::class)->submit($group, $server, [
            ['menu_item_id' => $cerveja->id, 'quantity' => 6],
            ['menu_item_id' => $vinho->id, 'quantity' => 2, 'variant_name' => 'Reserva'],
        ], $zone2);

        ProductionTicket::where('billing_group_id', $group->id)
            ->inRandomOrder()
            ->limit(2)
            ->update(['ticket_status' => 'PRINTED', 'printed_at' => now()]);

        if ($group->billingDocuments()->count() === 0) {
            $bill = app(BillingService::class)->generateInternalBill($group->refresh(), $cashier);
            app(BillingService::class)->reprintBill($bill, $cashier);
            app(BillingService::class)->recordPayment($group->refresh(), $cashier, 20.00, 'Numerário');
        }

        // ---- Second group: closed with full payment ----
        $group2 = BillingGroup::where('notes', 'like', '%demonstração%')->first();
        if (! $group2) {
            $targetRow = null;
            foreach ([$rowB1, $rowA1, $rowA2] as $candidate) {
                if ($candidate && ! OccupiedZone::where('row_id', $candidate->id)->where('is_open', true)->exists()) {
                    $targetRow = $candidate;
                    break;
                }
            }

            if ($targetRow) {
                $group2 = app(BillingGroupService::class)->open($session, $server, 4, 'Conta fechada de demonstração');
                $zone2a = app(OccupancyService::class)->assignZone($group2, $targetRow, 1, 2, $server);

                app(OrderService::class)->submit($group2, $server, [
                    ['menu_item_id' => $bitoque->id, 'quantity' => 2],
                    ['menu_item_id' => $cerveja->id, 'quantity' => 2],
                    ['menu_item_id' => $pudim->id, 'quantity' => 1],
                ], $zone2a);

                $total = $group2->refresh()->chargesTotal();
                app(BillingService::class)->recordPayment($group2, $cashier, $total, 'MBWay');
            } else {
                $this->command?->warn('No available row for demo group (all rows have open zones). Skipping second group.');
            }
        }

        $this->command?->info('Demo transactions seeded successfully.');
        $this->command?->info('  - G-001: 3 orders, 1 bill + reprint, 1 partial payment (20 EUR)');
        if ($group2 ?? null) {
            $this->command?->info("  - {$group2->display_code}: 1 order, full payment, closed");
        }
    }
}
