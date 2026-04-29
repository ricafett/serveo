<?php

namespace App\Domain\Printing;

use App\Models\BillingDocument;
use App\Models\OrderItem;
use App\Models\ProductionTicket;

/**
 * Plain-text 80mm renderer. Tickets are 42-char wide ASCII payloads suitable
 * for any standard ESC/POS printer or fallback file output.
 */
class TicketRenderer
{
    private const WIDTH = 42;

    public function renderProductionTicket(ProductionTicket $ticket): string
    {
        $ticket->loadMissing(['billingGroup.status', 'occupiedZone.row.section', 'items.menuItem']);

        $lines = [];
        $lines[] = $this->center($ticket->is_void_slip ? '*** ANULAÇÃO ***' : strtoupper($ticket->ticket_type));
        if ($ticket->is_reprint) {
            $lines[] = $this->center('** REIMPRESSÃO **');
        }
        $lines[] = str_repeat('=', self::WIDTH);
        $lines[] = 'Grupo: '.($ticket->billingGroup?->display_code ?? '-');
        if ($ticket->occupiedZone) {
            $z = $ticket->occupiedZone;
            $section = $z->row?->section?->section_code ?? '?';
            $lines[] = "Zona:  {$section}-{$z->row?->row_code} pares {$z->start_seat_pair_sequence}-{$z->end_seat_pair_sequence}";
        }
        if ($ticket->delivery_reference_label) {
            $lines[] = 'Entrega: '.$ticket->delivery_reference_label;
        }
        $lines[] = 'Hora:  '.$ticket->requested_at?->format('Y-m-d H:i');
        $lines[] = str_repeat('-', self::WIDTH);

        /** @var OrderItem $item */
        foreach ($ticket->items as $item) {
            $name = $item->menuItem?->display_name ?? "Item #{$item->menu_item_id}";
            $qty  = $item->quantity;
            $left = sprintf('%2dx %s', $qty, $name);
            $lines[] = mb_strimwidth($left, 0, self::WIDTH);
            if ($item->delivery_reference_label) {
                $lines[] = '   -> '.$item->delivery_reference_label;
            }
        }

        $lines[] = str_repeat('=', self::WIDTH);
        $lines[] = 'Ticket #'.$ticket->id;

        return implode("\n", $lines)."\n";
    }

    public function renderBill(BillingDocument $bill): string
    {
        $bill->loadMissing(['billingGroup.orderHeaders.items.menuItem', 'billingGroup.paymentRecords']);

        $lines = [];
        $lines[] = $this->center('CONTA INTERNA');
        if ($bill->is_reprint) {
            $lines[] = $this->center('** REIMPRESSÃO **');
        }
        $lines[] = str_repeat('=', self::WIDTH);
        $lines[] = 'Grupo:    '.$bill->billingGroup?->display_code;
        $lines[] = 'Documento: '.($bill->document_number ?: '#'.$bill->id);
        $lines[] = 'Hora:      '.$bill->requested_at?->format('Y-m-d H:i');
        $lines[] = str_repeat('-', self::WIDTH);

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
            $name = $item->menuItem?->display_name ?? "Item #{$item->menu_item_id}";
            $left = sprintf('%2dx %s', $item->quantity, mb_strimwidth($name, 0, 28));
            $right = number_format((float) $item->line_subtotal, 2, ',', ' ').' EUR';
            $lines[] = $this->row($left, $right);
        }

        $lines[] = str_repeat('-', self::WIDTH);
        $lines[] = $this->row('SUBTOTAL', number_format((float) $bill->subtotal_amount, 2, ',', ' ').' EUR');
        $lines[] = $this->row('TOTAL',    number_format((float) $bill->total_amount,    2, ',', ' ').' EUR');

        $paid = (float) $bill->billingGroup?->paymentsTotal();
        if ($paid > 0) {
            $lines[] = $this->row('Pago',  number_format($paid, 2, ',', ' ').' EUR');
            $lines[] = $this->row('Em dívida', number_format((float) $bill->total_amount - $paid, 2, ',', ' ').' EUR');
        }

        $lines[] = str_repeat('=', self::WIDTH);
        $lines[] = $this->center('Documento interno - sem valor fiscal');

        return implode("\n", $lines)."\n";
    }

    private function center(string $text): string
    {
        $len = mb_strlen($text);
        if ($len >= self::WIDTH) {
            return $text;
        }
        $pad = (int) floor((self::WIDTH - $len) / 2);
        return str_repeat(' ', $pad).$text;
    }

    private function row(string $left, string $right): string
    {
        $space = self::WIDTH - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            return $left.' '.$right;
        }
        return $left.str_repeat(' ', $space).$right;
    }
}
