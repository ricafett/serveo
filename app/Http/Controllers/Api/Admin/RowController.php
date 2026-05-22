<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\Row;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RowController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sectionId' => ['required', 'exists:sections,id'],
            'rowCode' => ['required', 'string', 'max:10'],
            'sortOrder' => ['nullable', 'integer'],
        ]);

        $row = Row::create([
            'section_id' => $validated['sectionId'],
            'row_code' => $validated['rowCode'],
            'sort_order' => $validated['sortOrder'] ?? 0,
            'is_active' => true,
        ]);

        return $this->success([
            'rowId' => $row->id,
            'rowCode' => $row->row_code,
        ], status: 201);
    }

    public function update(Request $request, Row $row): JsonResponse
    {
        $validated = $request->validate([
            'rowCode' => ['nullable', 'string', 'max:10'],
            'sortOrder' => ['nullable', 'integer'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('rowCode', $validated)) {
            $update['row_code'] = $validated['rowCode'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $update['sort_order'] = $validated['sortOrder'];
        }
        if (array_key_exists('isActive', $validated)) {
            $update['is_active'] = $validated['isActive'];
        }

        $row->update($update);

        return $this->success([
            'rowId' => $row->id,
            'rowCode' => $row->row_code,
            'isActive' => $row->is_active,
        ]);
    }
}
