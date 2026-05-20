<?php

namespace Database\Seeders;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\OrderItem;
use App\Models\ProductionTicket;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $session = ServiceSession::where('status', 'OPEN')->first();
        if (! $session) {
            $this->command->warn('No open service session found. Run CoreSeeder first.');
            return;
        }

        $server  = User::where('username', 'server1')->first();
        $cashier = User::where('username', 'cashier1')->first();

        if (! $server || ! $cashier) {
            $this->command->warn('Required users not found. Run CoreSeeder first.');
            return;
        }

        $rowA1 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '1')->first();
        $rowA2 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '2')->first();
        $rowB1 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'B'))->where('row_code', '1')->first();

        // ---- G-001 group (already created by CoreSeeder) ----
        $group = BillingGroup::where('display_code', 'G-001')->first();

        if (! $group) {
            $this->command->warn('G-001 billing group not found. Run CoreSeeder first.');
            return;
        }

        $zone1 = OccupiedZone::where('billing_group_id', $group->id)->where('row_id', $rowA1->id)->first();
        $zone2 = OccupiedZone::where('billing_group_id', $group->id)->where('row_id', $rowA2->id)->first();

        // Fetch menu items
        $bacalhau  = MenuItem::where('display_name', 'Bacalhau à brás')->first();
        $bitoque   = MenuItem::where('display_name', 'Bitoque')->first();
        $vinho     = MenuItem::where('display_name', 'Vinho tinto - copo')->first();
        $cerveja   = MenuItem::where('display_name', 'Cerveja imperial')->first();
        $sopa      = MenuItem::where('display_name', 'Sopa do dia')->first();
        $pudim     = MenuItem::where('display_name', 'Pudim flan')->first();

        // Order 1: mixed kitchen + bar for G-001
        $pair2 = SeatPair::where('row_id', $rowA1->id)->where('pair_sequence', 2)->first();
        app(OrderService::class)->submit($group, $server, [
            ['menu_item_id' => $bacalhau->id, 'quantity' => 2, 'delivery_seat_pair_id' => $pair2?->id],
            ['menu_item_id' => $vinho->id, 'quantity' => 4],
            ['menu_item_id' => $sopa->id, 'quantity' => 2],
        ], $zone1);

        // Order 2: only kitchen items for zone 1
        app(OrderService::class)->submit($group, $server, [
            ['menu_item_id' => $bitoque->id, 'quantity' => 1],
            ['menu_item_id' => $pudim->id, 'quantity' => 2],
        ], $zone1);

        // Order 3: only bar items for zone 2
        app(OrderService::class)->submit($group, $server, [
            ['menu_item_id' => $cerveja->id, 'quantity' => 6],
            ['menu_item_id' => $vinho->id, 'quantity' => 2],
        ], $zone2);

        // Mark some production tickets as PRINTED
        ProductionTicket::where('billing_group_id', $group->id)
            ->inRandomOrder()
            ->limit(2)
            ->update(['ticket_status' => 'PRINTED', 'printed_at' => now()]);

        // Generate internal bill for G-001 (skip if already generated)
        if ($group->billingDocuments()->count() === 0) {
            $bill = app(BillingService::class)->generateInternalBill($group->refresh(), $cashier);

            // Generate bill reprint
            app(BillingService::class)->reprintBill($bill, $cashier);

            // Partial payment for G-001
            app(BillingService::class)->recordPayment($group->refresh(), $cashier, 20.00, 'Numerário');
        }

        // ---- Second group: closed with full payment ----
        // Idempotent: skip if any billing group with demo notes already exists.
        $group2 = BillingGroup::where('notes', 'like', '%demonstração%')->first();
        if (! $group2) {
            // Find a row with no open zones to avoid overlap.
            // Prefer row B1 (unassigned by CoreSeeder), fall back to any available row.
            $targetRow = null;
            foreach ([$rowB1, $rowA1, $rowA2] as $candidate) {
                if ($candidate && ! OccupiedZone::where('row_id', $candidate->id)->where('is_open', true)->exists()) {
                    $targetRow = $candidate;
                    break;
                }
            }

            if ($targetRow) {
                $group2 = app(BillingGroupService::class)->open($session, $server, 4, 'Grupo fechado de demonstração');
                $zone2a = app(OccupancyService::class)->assignZone($group2, $targetRow, 1, 2, $server);

                app(OrderService::class)->submit($group2, $server, [
                    ['menu_item_id' => $bitoque->id, 'quantity' => 2],
                    ['menu_item_id' => $cerveja->id, 'quantity' => 2],
                    ['menu_item_id' => $pudim->id, 'quantity' => 1],
                ], $zone2a);

                $total = $group2->refresh()->chargesTotal();
                app(BillingService::class)->recordPayment($group2, $cashier, $total, 'MBWay');
            } else {
                $this->command->warn('No available row for demo group (all rows have open zones). Skipping second group.');
            }
        }

        $this->command->info('Demo transactions seeded successfully.');
        $this->command->info("  - G-001: 3 orders, 1 bill + reprint, 1 partial payment (20 EUR)");
        $this->command->info("  - {$group2->display_code}: 1 order, full payment, closed");
    }
}
