<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\MenuCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuCategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $categories = MenuCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success($categories->map(fn ($c) => [
            'menuCategoryId' => $c->id,
            'code'           => $c->code,
            'displayName'    => $c->display_name,
            'routeType'      => $c->route_type,
            'sortOrder'      => $c->sort_order,
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'unique:menu_categories,code'],
            'displayName' => ['required', 'string', 'max:100'],
            'routeType'   => ['required', 'string', 'in:KITCHEN,BAR,NONE'],
            'sortOrder'   => ['nullable', 'integer'],
        ]);

        $category = MenuCategory::create([
            'code'         => $validated['code'],
            'display_name' => $validated['displayName'],
            'route_type'   => $validated['routeType'],
            'sort_order'   => $validated['sortOrder'] ?? 0,
            'is_active'    => true,
        ]);

        return $this->success([
            'menuCategoryId' => $category->id,
            'code'           => $category->code,
            'displayName'    => $category->display_name,
        ], status: 201);
    }
}
