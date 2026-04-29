<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\PrinterRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterRouteController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = PrinterRoute::with(['printer', 'venue']);

        if ($request->filled('venueId')) {
            $query->where('venue_id', $request->input('venueId'));
        }

        $routes = $query->get();

        return $this->success($routes->map(fn ($r) => [
            'printerRouteId'   => $r->id,
            'venueId'          => $r->venue_id,
            'documentType'     => $r->document_type,
            'fulfillmentRoute' => $r->fulfillment_route,
            'printerId'        => $r->printer_id,
            'printerName'      => $r->printer?->name,
            'isActive'         => $r->is_active,
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venueId'           => ['required', 'exists:venues,id'],
            'documentType'      => ['required', 'string', 'in:PRODUCTION_TICKET,BILL,VOID_SLIP'],
            'fulfillmentRoute'  => ['nullable', 'string', 'in:KITCHEN,BAR,NONE'],
            'printerId'         => ['required', 'exists:printers,id'],
        ]);

        $route = PrinterRoute::updateOrCreate(
            [
                'venue_id'          => $validated['venueId'],
                'document_type'     => $validated['documentType'],
                'fulfillment_route' => $validated['fulfillmentRoute'] ?? null,
            ],
            [
                'printer_id' => $validated['printerId'],
                'is_active'  => true,
            ]
        );

        return $this->success([
            'printerRouteId'   => $route->id,
            'documentType'     => $route->document_type,
            'fulfillmentRoute' => $route->fulfillment_route,
        ], status: 201);
    }

    public function update(Request $request, PrinterRoute $printerRoute): JsonResponse
    {
        $validated = $request->validate([
            'printerId'  => ['nullable', 'exists:printers,id'],
            'isActive'   => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('printerId', $validated)) $update['printer_id'] = $validated['printerId'];
        if (array_key_exists('isActive', $validated))  $update['is_active'] = $validated['isActive'];

        $printerRoute->update($update);

        return $this->success([
            'printerRouteId'   => $printerRoute->id,
            'documentType'     => $printerRoute->document_type,
            'printerId'        => $printerRoute->printer_id,
            'isActive'         => $printerRoute->is_active,
        ]);
    }
}
