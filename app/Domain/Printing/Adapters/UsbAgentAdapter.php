<?php

namespace App\Domain\Printing\Adapters;

use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrintResult;
use App\Models\Printer;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * USB printers are reached through a small local print agent running on the
 * machine that owns the USB cable. The agent exposes a tiny HTTP endpoint that
 * the backend POSTs raw payloads to.
 *
 * The backend remains the source of truth; the agent is only a transport.
 */
class UsbAgentAdapter implements PrinterAdapter
{
    public function supports(Printer $printer): bool
    {
        return $printer->connection_type === Printer::CONN_USB_AGENT
            && filled($printer->agent_endpoint);
    }

    public function send(Printer $printer, string $payload): PrintResult
    {
        $endpoint = rtrim($printer->agent_endpoint, '/').'/print';
        $token = (string) config('services.print_agent.token');

        try {
            $response = Http::timeout(5)
                ->withHeaders(array_filter([
                    'X-Agent-Token' => $token ?: null,
                    'Accept' => 'application/json',
                ]))
                ->asJson()
                ->post($endpoint, [
                    'printer_id' => $printer->agent_printer_id,
                    'payload' => base64_encode($payload),
                ]);
        } catch (Throwable $e) {
            return PrintResult::fail("USB agent {$endpoint} unreachable: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return PrintResult::fail("USB agent {$endpoint} returned HTTP {$response->status()}: {$response->body()}");
        }

        return PrintResult::ok('Submitted to USB agent');
    }

    /**
     * Send a cash-drawer kick pulse through the USB print agent.
     */
    public function openCashDrawer(Printer $printer): PrintResult
    {
        $endpoint = rtrim($printer->agent_endpoint, '/').'/drawer';
        $token = (string) config('services.print_agent.token');

        try {
            $response = Http::timeout(5)
                ->withHeaders(array_filter([
                    'X-Agent-Token' => $token ?: null,
                    'Accept' => 'application/json',
                ]))
                ->asJson()
                ->post($endpoint, [
                    'printer_id' => $printer->agent_printer_id,
                ]);
        } catch (Throwable $e) {
            return PrintResult::fail("USB agent {$endpoint} unreachable for drawer kick: {$e->getMessage()}");
        }

        if (! $response->successful()) {
            return PrintResult::fail("USB agent {$endpoint} drawer returned HTTP {$response->status()}: {$response->body()}");
        }

        return PrintResult::ok('Drawer kick sent via USB agent');
    }

    /**
     * Lightweight probe — same transport as send() but with a minimal
     * payload so the agent can distinguish health checks from real prints.
     */
    public function probe(Printer $printer): PrintResult
    {
        return $this->send($printer, "\x1B\x40\x1D\x72\x01");
    }
}
