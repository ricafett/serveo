<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\Printer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterController extends ApiController
{
    public function index(): JsonResponse
    {
        $printers = Printer::orderBy('name')->get();

        return $this->success($printers->map(fn ($p) => [
            'printerId' => $p->id,
            'name' => $p->name,
            'printerType' => $p->printer_type,
            'connectionType' => $p->connection_type,
            'address' => $p->address,
            'port' => $p->port,
            'isActive' => $p->is_active,
            'healthStatus' => $p->health_status,
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'printerType' => ['required', 'string', 'in:KITCHEN,BAR,BILL,GENERIC'],
            'connectionType' => ['required', 'string', 'in:LAN,USB_AGENT,NULL'],
            'address' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
        ]);

        $printer = Printer::create([
            'name' => $validated['name'],
            'printer_type' => $validated['printerType'],
            'connection_type' => $validated['connectionType'],
            'address' => $validated['address'] ?? null,
            'port' => $validated['port'] ?? null,
            'is_active' => true,
            'health_status' => 'UNKNOWN',
        ]);

        return $this->success([
            'printerId' => $printer->id,
            'name' => $printer->name,
        ], status: 201);
    }

    public function update(Request $request, Printer $printer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'printerType' => ['nullable', 'string', 'in:KITCHEN,BAR,BILL,GENERIC'],
            'connectionType' => ['nullable', 'string', 'in:LAN,USB_AGENT,NULL'],
            'address' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('name', $validated)) {
            $update['name'] = $validated['name'];
        }
        if (array_key_exists('printerType', $validated)) {
            $update['printer_type'] = $validated['printerType'];
        }
        if (array_key_exists('connectionType', $validated)) {
            $update['connection_type'] = $validated['connectionType'];
        }
        if (array_key_exists('address', $validated)) {
            $update['address'] = $validated['address'];
        }
        if (array_key_exists('port', $validated)) {
            $update['port'] = $validated['port'];
        }
        if (array_key_exists('isActive', $validated)) {
            $update['is_active'] = $validated['isActive'];
        }

        $printer->update($update);

        return $this->success([
            'printerId' => $printer->id,
            'name' => $printer->name,
            'isActive' => $printer->is_active,
        ]);
    }
}
