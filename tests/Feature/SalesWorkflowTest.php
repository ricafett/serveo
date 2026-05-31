<?php

use App\Livewire\Cashier\SalesIndex;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->session = bootScenario();
    $this->cashier = makeUser('CASHIER');
    $this->printer = Printer::firstOrFail();

    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $this->printer->id],
        ['is_active' => true],
    );

    $this->voucherItem = MenuItem::where('display_name', 'Bacalhau')->firstOrFail();
    $this->voucherItem->update(['is_voucher_enabled' => true]);

    Queue::fake();
    $this->actingAs($this->cashier);
});

it('renders the sales screen for cashier', function () {
    $this->get('/sales')
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Pay and print vouchers');
});

it('completes a sale from the livewire sales screen', function () {
    Livewire::test(SalesIndex::class)
        ->set('paymentAmount', 18.00)
        ->set('paymentLabel', 'Cash')
        ->set('printReceipt', true)
        ->call('completeSale', [['menu_item_id' => $this->voucherItem->id, 'quantity' => 1]])
        ->assertSet('errorMessage', null)
        ->assertDispatched('sale-completed');

    expect(Sale::count())->toBe(1)
        ->and(SalePayment::count())->toBe(1);
});

it('prevents duplicate submission on completeSale', function () {
    Livewire::test(SalesIndex::class)
        ->set('paymentAmount', 18.00)
        ->set('paymentLabel', 'Cash')
        ->set('isSubmitting', true)
        ->call('completeSale', [['menu_item_id' => $this->voucherItem->id, 'quantity' => 1]]);

    expect(Sale::count())->toBe(0);
});
