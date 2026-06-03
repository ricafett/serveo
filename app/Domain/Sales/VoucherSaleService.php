<?php

namespace App\Domain\Sales;

use App\Domain\Audit\Audit;
use App\Domain\ChecksPermissions;
use App\Domain\Printing\PrintQueueService;
use App\Models\CashierPrinterAssignment;
use App\Models\DocumentPrintConfig;
use App\Models\MenuItem;
use App\Models\Sale;
use App\Models\SaleDocument;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoucherSaleService
{
    use ChecksPermissions;

    public function __construct(
        private readonly PrintQueueService $printQueue,
    ) {}

    /**
     * @param  array<int, array{menu_item_id:int, quantity:int}>  $lines
     */
    public function complete(ServiceSession $session, User $cashier, array $lines, float $paymentAmount, string $paymentLabel, ?string $paymentNotes = null, bool $printReceipt = false): Sale
    {
        $this->ensureCan($cashier, 'sale.create');
        $this->ensureCan($cashier, 'sale_payment.record');

        if (! $session->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($paymentAmount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        if ($lines === []) {
            throw new RuntimeException('Cart is empty.');
        }

        return DB::transaction(function () use ($session, $cashier, $lines, $paymentAmount, $paymentLabel, $paymentNotes, $printReceipt) {
            $resolvedItems = collect($lines)->map(function (array $line) {
                $item = MenuItem::findOrFail($line['menu_item_id']);

                if (! $item->is_active || ! $item->is_voucher_enabled) {
                    throw new RuntimeException("Item {$item->display_name} is not voucher-enabled.");
                }

                // Sales ignore modifiers and variants — the base item is sold as-is.
                $quantity = max(1, (int) ($line['quantity'] ?? 1));

                return [
                    'item' => $item,
                    'quantity' => $quantity,
                    'subtotal' => round((float) $item->unit_price * $quantity, 2),
                ];
            });

            $subtotal = round((float) $resolvedItems->sum('subtotal'), 2);

            if (round($paymentAmount, 2) < $subtotal) {
                throw new RuntimeException('Payment amount must cover the full sale total before printing vouchers.');
            }

            $sale = Sale::create([
                'service_session_id' => $session->id,
                'display_code' => $this->nextSaleCode(),
                'sold_by_user_id' => $cashier->id,
                'subtotal_amount' => $subtotal,
                'total_amount' => $subtotal,
                'payment_label' => $paymentLabel,
                'sold_at' => now(),
            ]);

            $saleItems = $resolvedItems->map(function (array $resolved) use ($sale) {
                /** @var MenuItem $item */
                $item = $resolved['item'];

                return SaleItem::create([
                    'sale_id' => $sale->id,
                    'menu_item_id' => $item->id,
                    'display_name_snapshot' => $item->display_name,
                    'unit_price' => $item->unit_price,
                    'quantity' => $resolved['quantity'],
                    'line_subtotal' => $resolved['subtotal'],
                ]);
            });

            $payment = SalePayment::create([
                'sale_id' => $sale->id,
                'recorded_by_user_id' => $cashier->id,
                'recorded_at' => now(),
                'amount' => $paymentAmount,
                'payment_label' => $paymentLabel,
                'notes' => $paymentNotes,
                'is_voided' => false,
            ]);

            Audit::record(
                'SALE_COMPLETED',
                "Sale {$sale->display_code} completed",
                ['total' => (float) $sale->total_amount],
                [
                    'service_session_id' => $sale->service_session_id,
                    'sale_id' => $sale->id,
                    'actor_user_id' => $cashier->id,
                ],
            );

            Audit::record(
                'SALE_PAYMENT_RECORDED',
                "Sale payment {$paymentLabel} of {$paymentAmount} € recorded for {$sale->display_code}",
                ['amount' => $paymentAmount, 'label' => $paymentLabel],
                [
                    'service_session_id' => $sale->service_session_id,
                    'sale_id' => $sale->id,
                    'sale_payment_id' => $payment->id,
                    'actor_user_id' => $cashier->id,
                ],
            );

            $printer = $this->resolveCashierPrinter($cashier);
            if (! $printer) {
                throw new RuntimeException('No cashier printer is assigned to this user.');
            }

            $voucherConfig = DocumentPrintConfig::firstOrCreate(
                ['document_type' => DocumentPrintConfig::DOC_SALE_VOUCHER, 'fulfillment_route' => null],
                ['group_items' => false, 'ignore_variants' => true, 'ignore_modifiers' => true, 'is_active' => true],
            );

            foreach ($saleItems as $saleItem) {
                if ($voucherConfig->group_items) {
                    $documents = collect([
                        SaleDocument::create([
                            'sale_id' => $sale->id,
                            'sale_item_id' => $saleItem->id,
                            'printer_id' => $printer->id,
                            'document_type' => SaleDocument::TYPE_VOUCHER,
                            'document_status' => 'GENERATED',
                            'document_number' => $this->nextVoucherDocumentNumber(),
                            'quantity' => $saleItem->quantity,
                            'requested_at' => now(),
                            'is_reprint' => false,
                            'created_by_user_id' => $cashier->id,
                        ]),
                    ]);
                } else {
                    $documents = collect(range(1, $saleItem->quantity))->map(fn () => SaleDocument::create([
                        'sale_id' => $sale->id,
                        'sale_item_id' => $saleItem->id,
                        'printer_id' => $printer->id,
                        'document_type' => SaleDocument::TYPE_VOUCHER,
                        'document_status' => 'GENERATED',
                        'document_number' => $this->nextVoucherDocumentNumber(),
                        'quantity' => 1,
                        'requested_at' => now(),
                        'is_reprint' => false,
                        'created_by_user_id' => $cashier->id,
                    ]));
                }

                foreach ($documents as $document) {
                    $this->printQueue->enqueueSaleVoucher($document, $cashier);

                    Audit::record(
                        'SALE_VOUCHER_QUEUED',
                        "Voucher {$document->document_number} queued for {$sale->display_code}",
                        ['quantity' => $document->quantity],
                        [
                            'service_session_id' => $sale->service_session_id,
                            'sale_id' => $sale->id,
                            'sale_document_id' => $document->id,
                            'actor_user_id' => $cashier->id,
                        ],
                    );
                }
            }

            if ($printReceipt) {
                $receipt = SaleDocument::create([
                    'sale_id' => $sale->id,
                    'printer_id' => $printer->id,
                    'document_type' => SaleDocument::TYPE_RECEIPT,
                    'document_status' => 'GENERATED',
                    'document_number' => $this->nextReceiptDocumentNumber(),
                    'quantity' => 1,
                    'requested_at' => now(),
                    'is_reprint' => false,
                    'created_by_user_id' => $cashier->id,
                ]);

                $this->printQueue->enqueueSaleReceipt($receipt, $cashier);

                Audit::record(
                    'SALE_RECEIPT_QUEUED',
                    "Sale receipt {$receipt->document_number} queued for {$sale->display_code}",
                    [],
                    [
                        'service_session_id' => $sale->service_session_id,
                        'sale_id' => $sale->id,
                        'sale_document_id' => $receipt->id,
                        'actor_user_id' => $cashier->id,
                    ],
                );
            }

            return $sale->load(['items.menuItem', 'payments', 'documents', 'soldBy', 'serviceSession']);
        });
    }

    private function resolveCashierPrinter(User $cashier)
    {
        return CashierPrinterAssignment::with('printer')
            ->where('user_id', $cashier->id)
            ->where('is_active', true)
            ->first()?->printer;
    }

    private function nextSaleCode(): string
    {
        return 'S-'.now()->format('Ymd').'-'.str_pad((string) (Sale::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nextVoucherDocumentNumber(): string
    {
        return 'V-'.now()->format('Ymd').'-'.str_pad((string) (SaleDocument::whereDate('created_at', today())->where('document_type', SaleDocument::TYPE_VOUCHER)->count() + 1), 5, '0', STR_PAD_LEFT);
    }

    private function nextReceiptDocumentNumber(): string
    {
        return 'R-'.now()->format('Ymd').'-'.str_pad((string) (SaleDocument::whereDate('created_at', today())->where('document_type', SaleDocument::TYPE_RECEIPT)->count() + 1), 5, '0', STR_PAD_LEFT);
    }
}
