<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\BillingService;
use App\Http\Controllers\ApiController;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingDocumentController extends ApiController
{
    public function __construct(private readonly BillingService $billingService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'billingGroupId' => ['required', 'exists:billing_groups,id'],
            'documentType' => ['required', 'string', 'in:INTERNAL_BILL'],
            'print' => ['nullable', 'boolean'],
        ]);

        $group = BillingGroup::findOrFail($validated['billingGroupId']);

        if ($group->is_closed) {
            return $this->error('GROUP_CLOSED', 'Cannot generate bill for a closed group.', status: 409);
        }

        try {
            $bill = $this->billingService->generateInternalBill($group, $request->user());
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'cashier printer')) {
                return $this->error('PRINTER_ROUTE_MISSING', $e->getMessage(), status: 422);
            }

            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        return $this->success([
            'billingDocumentId' => $bill->id,
            'documentType' => $bill->document_type,
            'documentStatus' => $bill->document_status,
            'totalAmount' => (string) $bill->total_amount,
        ], status: 201);
    }

    public function reprint(Request $request, BillingDocument $billingDocument): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $reprint = $this->billingService->reprintBill($billingDocument, $request->user());
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'cashier printer')) {
                return $this->error('PRINTER_ROUTE_MISSING', $e->getMessage(), status: 422);
            }

            return $this->error('PRINT_FAILED', $e->getMessage(), status: 500);
        }

        return $this->success([
            'billingDocumentId' => $reprint->id,
            'documentType' => $reprint->document_type,
            'documentStatus' => $reprint->document_status,
            'totalAmount' => (string) $reprint->total_amount,
            'isReprint' => true,
        ]);
    }
}
