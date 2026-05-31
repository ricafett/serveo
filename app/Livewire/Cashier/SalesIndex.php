<?php

namespace App\Livewire\Cashier;

use App\Domain\Sales\VoucherSaleService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Sale;
use App\Models\ServiceSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SalesIndex extends Component
{
    /** @var array<int, array{id:int,display_name:string,unit_price:float,category_id:int}> */
    public array $menuItemsData = [];

    /** @var array<int, array{id:int,display_name:string}> */
    public array $menuCategoriesData = [];

    public ?int $defaultCategoryId = null;

    public ?float $paymentAmount = null;

    public string $paymentLabel = 'Cash';

    public ?string $paymentNotes = null;

    public bool $printReceipt = false;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public bool $isSubmitting = false;

    public function mount(): void
    {
        $this->defaultCategoryId = MenuCategory::whereHas('items', function ($query) {
            $query->where('is_active', true)
                ->where('is_voucher_enabled', true)
                ->whereNull('modifier_set_id')
                ->whereDoesntHave('activeVariants');
        })->where('is_active', true)->orderBy('sort_order')->value('id');

        $this->menuCategoriesData = MenuCategory::where('is_active', true)
            ->whereHas('items', function ($query) {
                $query->where('is_active', true)
                    ->where('is_voucher_enabled', true)
                    ->whereNull('modifier_set_id')
                    ->whereDoesntHave('activeVariants');
            })
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'display_name' => $category->display_name,
            ])
            ->all();

        $this->menuItemsData = MenuItem::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_voucher_enabled', true)
            ->whereNull('modifier_set_id')
            ->whereDoesntHave('activeVariants')
            ->orderBy('display_name')
            ->get()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'display_name' => $item->display_name,
                'unit_price' => (float) $item->unit_price,
                'category_id' => $item->menu_category_id,
            ])
            ->all();
    }

    public function completeSale(array $cart = []): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        $session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();
        if (! $session) {
            $this->errorMessage = __('sales.no_session');

            return;
        }

        if ($cart === []) {
            $this->errorMessage = __('sales.empty_cart');

            return;
        }

        if ($this->paymentAmount === null) {
            $this->errorMessage = __('sales.payment_amount_required');

            return;
        }

        try {
            $this->isSubmitting = true;

            /** @var Sale $sale */
            $sale = app(VoucherSaleService::class)->complete(
                $session,
                Auth::user(),
                collect($cart)->map(fn ($item) => [
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                ])->all(),
                (float) $this->paymentAmount,
                $this->paymentLabel,
                $this->paymentNotes,
                $this->printReceipt,
            );

            $this->successMessage = __('sales.sale_completed', ['code' => $sale->display_code]);
            $this->paymentAmount = null;
            $this->paymentNotes = null;
            $this->printReceipt = false;

            $this->dispatch('sale-completed');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function render()
    {
        return view('livewire.cashier.sales-index')
            ->layout('layouts.operational');
    }
}
