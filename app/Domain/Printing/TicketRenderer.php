<?php

namespace App\Domain\Printing;

use App\Models\BillingDocument;
use App\Models\OrderItem;
use App\Models\ProductionTicket;
use Illuminate\Support\Carbon;

/**
 * Plain-text 80mm renderer. Tickets are configurable-width payloads suitable
 * for any standard ESC/POS printer or fallback file output.
 */
class TicketRenderer
{
    public function __construct(
        private readonly int $charWidth = 48,
        private readonly int $beginSpace = 0,
        private readonly int $endSpace = 3,
    ) {}

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
        $lines[] = $this->center($ticket->is_void_slip ? '*** '.__('ticket.void').' ***' : strtoupper($ticket->ticket_type));
        if ($ticket->is_reprint) {
            $lines[] = $this->center('** '.__('ticket.reprint').' **');
        }
        $lines[] = str_repeat('=', $this->width());
        $lines[] = __('ticket.group').': '.($ticket->billingGroup?->display_code ?? '-');
        if ($ticket->billingGroup?->name) {
            $lines[] = __('ticket.name').':  '.$ticket->billingGroup->name;
        }

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

        /** @var OrderItem $item */
        foreach ($ticket->items as $item) {
            $name = $item->menuItem?->display_name ?? __('ticket.unknown_item', ['id' => $item->menu_item_id]);
            if ($item->variant_name) {
                $name .= ' - '.$item->variant_name;
            }
            if ($item->modifier_name) {
                $name .= ' ('.$item->modifier_name.')';
            }
            $qty = $item->quantity;
            $left = sprintf('%2dx %s', $qty, $name);
            $lines[] = mb_strimwidth($left, 0, $this->width());
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
        $bill->loadMissing(['billingGroup.orderHeaders.items.menuItem', 'billingGroup.paymentRecords']);

        $lines = [];
        $lines[] = $this->center(__('ticket.internal_bill'));
        if ($bill->is_reprint) {
            $lines[] = $this->center('** '.__('ticket.reprint').' **');
        }
        $lines[] = str_repeat('=', $this->width());
        $lines[] = __('ticket.group').':    '.$bill->billingGroup?->display_code;
        $lines[] = __('ticket.document').': '.($bill->document_number ?: '#'.$bill->id);
        $lines[] = __('ticket.time').':      '.$this->localTime($bill->requested_at);
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

        foreach ($items as $item) {
            $name = $item->menuItem?->display_name ?? __('ticket.unknown_item', ['id' => $item->menu_item_id]);
            if ($item->variant_name) {
                $name .= ' - '.$item->variant_name;
            }
            if ($item->modifier_name) {
                $name .= ' ('.$item->modifier_name.')';
            }
            $left = sprintf('%2dx %s', $item->quantity, mb_strimwidth($name, 0, 28));
            $right = number_format((float) $item->line_subtotal, 2, ',', ' ').' '.__('ticket.currency');
            $lines[] = $this->row($left, $right);
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
