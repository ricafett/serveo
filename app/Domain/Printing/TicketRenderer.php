<?php

namespace App\Domain\Printing;

use App\Models\BillingDocument;
use App\Models\DocumentPrintConfig;
use App\Models\OrderItem;
use App\Models\ProductionTicket;
use App\Models\SaleDocument;
use Illuminate\Support\Carbon;

/**
 * Plain-text 80mm renderer. Tickets are configurable-width payloads suitable
 * for any standard ESC/POS printer or fallback file output.
 */
class TicketRenderer
{
    public function __construct(
        private int $charWidth = 48,
        private int $beginSpace = 0,
        private int $endSpace = 0,
        private readonly ?DocumentPrintConfig $documentConfig = null,
    ) {
        // Document config overrides printer defaults for begin/end feed lines.
        if ($this->documentConfig) {
            $this->beginSpace = $this->documentConfig->print_begin_space ?? $this->beginSpace;
            $this->endSpace = $this->documentConfig->print_end_space ?? $this->endSpace;
        }
    }

    private function width(): int
    {
        return $this->charWidth;
    }

    public function renderProductionTicket(ProductionTicket $ticket): string
    {
        $ticket->loadMissing([
            'billingGroup.occupiedZones.row.section',
            'billingGroup.occupiedZones.server',
            'items.menuItem',
            'items.header',
            'createdBy',
        ]);

        $lines = [];

        // --- Branding header ---
        $this->appendBrandingHeader($lines);

        $firstItem = $ticket->items->first();
        $voidRoute = $firstItem?->fulfillment_route ?? $ticket->ticket_type;

        if ($ticket->is_void_slip) {
            $isFullVoid = $firstItem?->header?->submission_status === 'VOIDED';
            $prefix = $isFullVoid ? __('ticket.void_full').' - ' : __('ticket.void_partial').' - ';
            $lines[] = $this->center('*** '.$prefix.strtoupper($voidRoute).' ***');
        } else {
            $lines[] = $this->center(strtoupper($ticket->ticket_type));
        }

        if ($ticket->is_reprint) {
            $lines[] = $this->center('** '.__('ticket.reprint').' **');
        }
        $lines[] = str_repeat('=', $this->width());
        $groupLabel = $ticket->billingGroup?->display_code ?? '-';
        if ($ticket->billingGroup?->name) {
            $groupLabel .= ' '.$ticket->billingGroup->name;
        }
        $lines[] = __('ticket.group').': '.$groupLabel;

        $zones = $ticket->billingGroup?->occupiedZones ?? collect();
        if ($zones->isNotEmpty()) {
            $lines[] = __('ticket.zones').': '.$zones->map->rangeLabel()->join(', ');
        }
        if ($ticket->delivery_reference_label) {
            $lines[] = __('ticket.delivery').': '.$ticket->delivery_reference_label;
        }

        $lines[] = __('ticket.time').':  '.$this->localTime($ticket->requested_at);

        if ($ticket->createdBy) {
            $lines[] = __('ticket.server').': '.$ticket->createdBy->name;
        }

        $assigned = $ticket->billingGroup?->assignedServers() ?? collect();
        $creatorId = $ticket->created_by_user_id;
        $assignedOthers = $assigned->where('id', '!=', $creatorId);
        if ($assignedOthers->isNotEmpty()) {
            $lines[] = __('ticket.assigned').': '.$assignedOthers->pluck('name')->join(', ');
        }

        $lines[] = str_repeat('-', $this->width());

        $shouldGroup = $this->documentConfig?->group_items ?? true;

        /** @var OrderItem $item */
        foreach ($ticket->items as $item) {
            $name = $this->buildItemName($item);
            if ($shouldGroup) {
                $qty = $item->quantity;
                $left = sprintf('%2dx %s', $qty, $name);
                $lines[] = mb_strimwidth($left, 0, $this->width());
            } else {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $left = sprintf(' 1x %s', $name);
                    $lines[] = mb_strimwidth($left, 0, $this->width());
                }
            }
        }

        $orderNotes = $ticket->items
            ->map(fn ($item) => $item->header?->notes)
            ->filter()
            ->unique()
            ->values();
        if ($orderNotes->isNotEmpty()) {
            $lines[] = str_repeat('-', $this->width());
            $lines[] = $this->center(strtoupper(__('ticket.notes')));
            foreach ($orderNotes as $note) {
                $lines[] = $note;
            }
        }

        if ($ticket->is_void_slip) {
            $voidReasons = $ticket->items
                ->map(fn ($item) => $item->void_reason)
                ->filter()
                ->unique()
                ->values();
            if ($voidReasons->isNotEmpty()) {
                $lines[] = str_repeat('-', $this->width());
                foreach ($voidReasons as $reason) {
                    $lines[] = __('ticket.void_reason').': '.$reason;
                }
            }
        }

        $lines[] = str_repeat('=', $this->width());

        $orderIds = $ticket->items
            ->map(fn ($item) => $item->header?->id)
            ->filter()
            ->unique()
            ->values();
        $footerParts = [];
        $footerParts[] = __('ticket.ticket_num').' #'.$ticket->id;
        if ($orderIds->isNotEmpty()) {
            $footerParts[] = __('ticket.order_num').' #'.$orderIds->join(', ');
        }
        $lines[] = implode('  ', $footerParts);

        return $this->wrap(implode("\n", $lines)."\n");
    }

    public function renderBill(BillingDocument $bill): string
    {
        $bill->loadMissing(['billingGroup.orderHeaders.items.menuItem', 'billingGroup.paymentRecords', 'billingGroup.occupiedZones.row.section', 'createdBy']);

        $lines = [];

        // --- Branding header ---
        $this->appendBrandingHeader($lines);

        $lines[] = $this->center(__('ticket.internal_bill'));
        if ($bill->is_reprint) {
            $lines[] = $this->center('** '.__('ticket.reprint').' **');
        }
        $lines[] = str_repeat('=', $this->width());
        $groupLabel = $bill->billingGroup?->display_code ?? '-';
        if ($bill->billingGroup?->name) {
            $groupLabel .= ' '.$bill->billingGroup->name;
        }
        $lines[] = __('ticket.group').': '.$groupLabel;

        $zones = $bill->billingGroup?->occupiedZones ?? collect();
        if ($zones->isNotEmpty()) {
            $lines[] = __('ticket.zones').': '.$zones->map->rangeLabel()->join(', ');
        }

        $lines[] = __('ticket.document').': '.($bill->document_number ?: '#'.$bill->id);
        $lines[] = __('ticket.time').':      '.$this->localTime($bill->requested_at);

        if ($bill->createdBy) {
            $lines[] = __('ticket.server').': '.$bill->createdBy->name;
        }

        $lines[] = str_repeat('-', $this->width());

        $items = collect();
        foreach ($bill->billingGroup?->orderHeaders ?? [] as $header) {
            foreach ($header->items as $item) {
                if ($item->voided_at) {
                    continue;
                }
                $items->push($item);
            }
        }

        $shouldGroup = $this->documentConfig?->group_items ?? false;

        if ($shouldGroup) {
            $grouped = [];
            foreach ($items as $item) {
                $key = (string) $item->menu_item_id;
                if (! ($this->documentConfig?->ignore_variants ?? false) && $item->variant_name) {
                    $key .= '|v:'.$item->variant_name;
                }
                if (! ($this->documentConfig?->ignore_modifiers ?? false) && $item->modifier_name) {
                    $key .= '|m:'.$item->modifier_name;
                }

                $grouped[$key] ??= ['qty' => 0, 'subtotal' => 0.0, 'item' => $item];
                $grouped[$key]['qty'] += $item->quantity;
                $grouped[$key]['subtotal'] += (float) $item->line_subtotal;
            }

            foreach ($grouped as $group) {
                $name = $this->buildItemName($group['item']);
                $left = sprintf('%2dx %s', $group['qty'], mb_strimwidth($name, 0, 28));
                $right = number_format($group['subtotal'], 2, ',', ' ').' '.__('ticket.currency');
                $lines[] = $this->row($left, $right);
            }
        } else {
            foreach ($items as $item) {
                $name = $this->buildItemName($item);
                $left = sprintf('%2dx %s', $item->quantity, mb_strimwidth($name, 0, 28));
                $right = number_format((float) $item->line_subtotal, 2, ',', ' ').' '.__('ticket.currency');
                $lines[] = $this->row($left, $right);
            }
        }

        $lines[] = str_repeat('-', $this->width());
        $lines[] = $this->row(__('ticket.subtotal'), number_format((float) $bill->subtotal_amount, 2, ',', ' ').' '.__('ticket.currency'));
        $lines[] = $this->row(__('ticket.total'), number_format((float) $bill->total_amount, 2, ',', ' ').' '.__('ticket.currency'));

        $paid = (float) $bill->billingGroup?->paymentsTotal();
        if ($paid > 0) {
            $lines[] = $this->row(__('ticket.paid'), number_format($paid, 2, ',', ' ').' '.__('ticket.currency'));
            $lines[] = $this->row(__('ticket.due'), number_format((float) $bill->total_amount - $paid, 2, ',', ' ').' '.__('ticket.currency'));
        }

        $lines[] = str_repeat('=', $this->width());
        $lines[] = $this->center(__('ticket.no_fiscal'));

        return $this->wrap(implode("\n", $lines)."\n");
    }

    public function renderSaleDocument(SaleDocument $document): string
    {
        $document->loadMissing(['sale.items.menuItem', 'sale.payments', 'sale.soldBy', 'saleItem.menuItem']);

        $lines = [];

        // --- Branding header ---
        $this->appendBrandingHeader($lines);

        if ($document->document_type === SaleDocument::TYPE_RECEIPT) {
            $lines[] = $this->center(__('ticket.sale_receipt'));
            if ($document->is_reprint) {
                $lines[] = $this->center('** '.__('ticket.reprint').' **');
            }
            $lines[] = str_repeat('=', $this->width());
            $lines[] = __('ticket.sale').': '.($document->sale?->display_code ?? '-');
            $lines[] = __('ticket.document').': '.($document->document_number ?: '#'.$document->id);
            $lines[] = __('ticket.time').':      '.$this->localTime($document->requested_at);
            if ($document->sale?->soldBy) {
                $lines[] = __('ticket.server').': '.$document->sale->soldBy->name;
            }
            $lines[] = str_repeat('-', $this->width());

            foreach ($document->sale?->items ?? [] as $item) {
                $left = sprintf('%2dx %s', $item->quantity, mb_strimwidth($item->display_name_snapshot, 0, 28));
                $right = number_format((float) $item->line_subtotal, 2, ',', ' ').' '.__('ticket.currency');
                $lines[] = $this->row($left, $right);
            }

            $lines[] = str_repeat('-', $this->width());
            $lines[] = $this->row(__('ticket.total'), number_format((float) $document->sale?->total_amount, 2, ',', ' ').' '.__('ticket.currency'));
            $paid = (float) ($document->sale?->payments->where('is_voided', false)->sum('amount') ?? 0);
            $lines[] = $this->row(__('ticket.paid'), number_format($paid, 2, ',', ' ').' '.__('ticket.currency'));
            $lines[] = str_repeat('=', $this->width());
            $lines[] = $this->center(__('ticket.no_fiscal'));

            return $this->wrap(implode("\n", $lines)."\n");
        }

        $itemName = $document->saleItem?->display_name_snapshot ?? __('ticket.unknown_item', ['id' => $document->sale_item_id]);
        $quantity = max(1, (int) $document->quantity);

        if ($document->is_reprint) {
            $lines[] = $this->center('** '.__('ticket.reprint').' **');
        }
        $lines[] = __('ticket.document').': '.($document->document_number ?: '#'.$document->id);
        $lines[] = __('ticket.time').':  '.$this->localTime($document->requested_at);
        $lines[] = str_repeat('-', $this->width());
        $left = sprintf('%2dx %s', $quantity, mb_strimwidth($itemName, 0, 28));
        $lines[] = mb_strimwidth($left, 0, $this->width());

        return $this->wrap(implode("\n", $lines)."\n");
    }

    /**
     * Build the display name for an order item, respecting
     * DocumentPrintConfig ignore_variants / ignore_modifiers settings.
     */
    private function buildItemName(OrderItem $item): string
    {
        $name = $item->menuItem?->display_name ?? __('ticket.unknown_item', ['id' => $item->menu_item_id]);

        $showVariant = ! ($this->documentConfig?->ignore_variants ?? false);
        $showModifier = ! ($this->documentConfig?->ignore_modifiers ?? false);

        if ($showVariant && $item->variant_name) {
            $name .= ' - '.$item->variant_name;
        }
        if ($showModifier && $item->modifier_name) {
            $name .= ' ('.$item->modifier_name.')';
        }

        return $name;
    }

    private function appendBrandingHeader(array &$lines): void
    {
        $header = $this->documentConfig?->branding_header;

        if (blank($header)) {
            return;
        }

        foreach (explode("\n", $header) as $headerLine) {
            $lines[] = $this->bold($this->center(trim($headerLine)));
        }
    }

    /**
     * Wrap text in ESC/POS bold on/off commands.
     */
    private function bold(string $text): string
    {
        return "\x1B\x45\x01".$text."\x1B\x45\x00";
    }

    private function wrap(string $payload): string
    {
        $result = '';
        if ($this->beginSpace > 0) {
            $result .= str_repeat("\n", $this->beginSpace);
        }
        $result .= $payload;
        if ($this->endSpace > 0) {
            $result .= str_repeat("\n", $this->endSpace);
        }

        return $result;
    }

    private function localTime(?Carbon $carbon): string
    {
        return $carbon?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '-';
    }

    private function center(string $text): string
    {
        $len = mb_strlen($text);
        if ($len >= $this->width()) {
            return $text;
        }
        $pad = (int) floor(($this->width() - $len) / 2);

        return str_repeat(' ', $pad).$text;
    }

    private function row(string $left, string $right): string
    {
        $space = $this->width() - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            return $left.' '.$right;
        }

        return $left.str_repeat(' ', $space).$right;
    }
}
