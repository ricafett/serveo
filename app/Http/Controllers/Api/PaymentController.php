<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\BillingService;
use App\Http\Controllers\ApiController;
use App\Models\BillingGroup;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(private readonly BillingService $billingService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'billingGroupId' => ['required', 'exists:billing_groups,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paymentLabel' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $group = BillingGroup::findOrFail($validated['billingGroupId']);

        if ($group->is_closed) {
            return $this->error('GROUP_CLOSED', 'Cannot record payment on a closed billing group.', status: 409);
        }

        try {
            $payment = $this->billingService->recordPayment(
                $group,
                $request->user(),
                (float) $validated['amount'],
                $validated['paymentLabel'],
                $validated['notes'] ?? null,
            );
        } catch (AuthorizationException $e) {
            return $this->error('FORBIDDEN', $e->getMessage(), status: 403);
        } catch (\RuntimeException $e) {
            return $this->error('VALIDATION_ERROR', $e->getMessage(), status: 400);
        }

        return $this->success([
            'paymentRecordId' => $payment->id,
            'billingGroupId' => $payment->billing_group_id,
            'amount' => (string) $payment->amount,
            'paymentLabel' => $payment->payment_label,
            'recordedAt' => $payment->recorded_at?->toIso8601String(),
        ], status: 201);
    }
}
