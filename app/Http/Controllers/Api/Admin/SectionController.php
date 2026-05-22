<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends ApiController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'venueId' => ['required', 'exists:venues,id'],
            'sectionCode' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
            'sortOrder' => ['nullable', 'integer'],
        ]);

        $section = Section::create([
            'venue_id' => $validated['venueId'],
            'section_code' => $validated['sectionCode'],
            'name' => $validated['name'],
            'sort_order' => $validated['sortOrder'] ?? 0,
            'is_active' => true,
        ]);

        return $this->success([
            'sectionId' => $section->id,
            'sectionCode' => $section->section_code,
            'name' => $section->name,
        ], status: 201);
    }

    public function update(Request $request, Section $section): JsonResponse
    {
        $validated = $request->validate([
            'sectionCode' => ['nullable', 'string', 'max:10'],
            'name' => ['nullable', 'string', 'max:100'],
            'sortOrder' => ['nullable', 'integer'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('sectionCode', $validated)) {
            $update['section_code'] = $validated['sectionCode'];
        }
        if (array_key_exists('name', $validated)) {
            $update['name'] = $validated['name'];
        }
        if (array_key_exists('sortOrder', $validated)) {
            $update['sort_order'] = $validated['sortOrder'];
        }
        if (array_key_exists('isActive', $validated)) {
            $update['is_active'] = $validated['isActive'];
        }

        $section->update($update);

        return $this->success([
            'sectionId' => $section->id,
            'sectionCode' => $section->section_code,
            'name' => $section->name,
            'isActive' => $section->is_active,
        ]);
    }
}
