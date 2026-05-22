<?php

namespace App\Domain\Accounting;

use App\Models\AccountingExport;
use App\Models\BillingGroup;
use Illuminate\Support\Facades\Storage;

class AccountingExportService
{
    public function generate(AccountingExport $export): string
    {
        $query = BillingGroup::query()
            ->with([
                'status',
                'occupiedZones.row.section',
                'paymentRecords',
                'orderHeaders.items' => fn ($q) => $q->whereNull('voided_at'),
            ]);

        if ($export->service_session_id) {
            $query->where('service_session_id', $export->service_session_id);
        }

        if ($export->export_range_start) {
            $query->where('opened_at', '>=', $export->export_range_start);
        }

        if ($export->export_range_end) {
            $query->where('opened_at', '<=', $export->export_range_end);
        }

        $groups = $query->get();

        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'billing_group_code',
            'status',
            'opened_at',
            'closed_at',
            'total_charges',
            'total_payments',
            'remaining_balance',
            'occupied_zones',
            'payment_records',
        ]);

        foreach ($groups as $group) {
            $charges = (float) $group->orderHeaders->flatMap->items->whereNull('voided_at')->sum('line_subtotal');
            $payments = (float) $group->paymentRecords->where('is_voided', false)->sum('amount');
            $balance = round($charges - $payments, 2);

            $zones = $group->occupiedZones->map(function ($zone) {
                return $zone->rangeLabel();
            })->implode('; ');

            $paymentsStr = $group->paymentRecords->where('is_voided', false)->map(function ($payment) {
                return number_format($payment->amount, 2).' '.$payment->payment_label.' '.$payment->recorded_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
            })->implode('; ');

            fputcsv($handle, [
                $group->display_code,
                $group->status?->code ?? '',
                $group->opened_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                $group->closed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
                number_format($charges, 2, '.', ''),
                number_format($payments, 2, '.', ''),
                number_format($balance, 2, '.', ''),
                $zones,
                $paymentsStr,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $path = "exports/accounting_export_{$export->id}.csv";
        Storage::disk('local')->put($path, $content);

        return $path;
    }
}
