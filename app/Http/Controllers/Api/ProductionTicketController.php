<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrintQueueService;
use App\Http\Controllers\ApiController;
use App\Models\ProductionTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionTicketController extends ApiController
{
    public function __construct(private readonly PrintQueueService $printQueue) {}

    public function show(ProductionTicket $productionTicket): JsonResponse
    {
        $productionTicket->load(['items.menuItem', 'printer', 'billingGroup', 'occupiedZone']);

        return $this->success([
            'productionTicketId'     => $productionTicket->id,
            'ticketType'             => $productionTicket->ticket_type,
            'ticketStatus'           => $productionTicket->ticket_status,
            'billingGroupId'         => $productionTicket->billing_group_id,
            'occupiedZoneId'         => $productionTicket->occupied_zone_id,
            'printerId'              => $productionTicket->printer_id,
            'printerName'            => $productionTicket->printer?->name,
            'printedAt'              => $productionTicket->printed_at?->toIso8601String(),
            'requestedAt'            => $productionTicket->requested_at?->toIso8601String(),
            'isVoidSlip'             => $productionTicket->is_void_slip,
            'isReprint'              => $productionTicket->is_reprint,
            'deliveryReferenceLabel' => $productionTicket->delivery_reference_label,
            'items'                  => $productionTicket->items->map(fn ($item) => [
                'orderItemId'   => $item->id,
                'menuItemName'  => $item->menuItem?->display_name,
                'quantity'      => $item->quantity,
            ])->values()->all(),
        ]);
    }

    public function reprint(Request $request, ProductionTicket $productionTicket): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $reprint = DB::transaction(function () use ($productionTicket, $user) {
            $newTicket = ProductionTicket::create([
                'service_session_id'     => $productionTicket->service_session_id,
                'billing_group_id'       => $productionTicket->billing_group_id,
                'occupied_zone_id'       => $productionTicket->occupied_zone_id,
                'printer_id'             => $productionTicket->printer_id,
                'ticket_type'            => $productionTicket->ticket_type,
                'ticket_status'          => 'PENDING',
                'delivery_reference_label' => $productionTicket->delivery_reference_label,
                'requested_at'           => now(),
                'reprint_of_ticket_id'   => $productionTicket->id,
                'is_void_slip'           => $productionTicket->is_void_slip,
                'is_reprint'             => true,
                'created_by_user_id'     => $user->id,
            ]);

            $newTicket->items()->sync($productionTicket->items->pluck('id'));

            $this->printQueue->enqueueProductionTicket($newTicket, $user);

            Audit::record(
                'PRODUCTION_TICKET_REPRINTED',
                "Reimpressão do ticket #{$productionTicket->id}",
                ['original_ticket_id' => $productionTicket->id],
                [
                    'billing_group_id'     => $productionTicket->billing_group_id,
                    'service_session_id'   => $productionTicket->service_session_id,
                    'production_ticket_id' => $newTicket->id,
                ],
            );

            return $newTicket;
        });

        return $this->success([
            'productionTicketId' => $reprint->id,
            'isReprint'          => true,
            'reprintOfTicketId'  => $productionTicket->id,
        ]);
    }
}
