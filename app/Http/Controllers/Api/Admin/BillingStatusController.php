<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\BillingStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingStatusController extends ApiController
{
    public function index(): JsonResponse
    {
        $statuses = BillingStatus::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($statuses->map(fn ($s) => [
            'billingStatusId' => $s->id,
            'code'            => $s->code,
            'displayName'     => $s->display_name,
            'sortOrder'       => $s->sort_order,
            'isActive'        => $s->is_active,
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'unique:billing_statuses,code'],
            'displayName' => ['required', 'string', 'max:100'],
            'sortOrder'   => ['nullable', 'integer'],
        ]);

        $status = BillingStatus::create([
            'code'        => $validated['code'],
            'display_name'=> $validated['displayName'],
            'sort_order'  => $validated['sortOrder'] ?? 0,
            'is_active'   => true,
        ]);

        return $this->success([
            'billingStatusId' => $status->id,
            'code'            => $status->code,
            'displayName'     => $status->display_name,
        ], status: 201);
    }

    public function update(Request $request, BillingStatus $billingStatus): JsonResponse
    {
        $validated = $request->validate([
            'displayName' => ['nullable', 'string', 'max:100'],
            'sortOrder'   => ['nullable', 'integer'],
            'isActive'    => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('displayName', $validated)) $update['display_name'] = $validated['displayName'];
        if (array_key_exists('sortOrder', $validated))   $update['sort_order'] = $validated['sortOrder'];
        if (array_key_exists('isActive', $validated))    $update['is_active'] = $validated['isActive'];

        $billingStatus->update($update);

        return $this->success([
            'billingStatusId' => $billingStatus->id,
            'code'            => $billingStatus->code,
            'displayName'     => $billingStatus->display_name,
            'isActive'        => $billingStatus->is_active,
        ]);
    }
}
