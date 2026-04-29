<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuItemController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::with('category')
            ->where('is_active', true)
            ->orderBy('display_name');

        if ($request->filled('categoryId')) {
            $query->where('menu_category_id', $request->input('categoryId'));
        }

        return $this->success($query->get()->map(fn ($item) => [
            'menuItemId'     => $item->id,
            'menuCategoryId' => $item->menu_category_id,
            'categoryName'   => $item->category?->display_name,
            'sku'            => $item->sku,
            'code'           => $item->code,
            'displayName'    => $item->display_name,
            'shortName'      => $item->short_name,
            'unitPrice'      => (string) $item->unit_price,
            'isActive'       => $item->is_active,
        ])->all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'menuCategoryId' => ['required', 'exists:menu_categories,id'],
            'displayName'    => ['required', 'string', 'max:100'],
            'shortName'      => ['nullable', 'string', 'max:50'],
            'sku'            => ['nullable', 'string', 'max:50'],
            'code'           => ['nullable', 'string', 'max:50'],
            'unitPrice'      => ['required', 'numeric', 'min:0'],
            'taxCode'        => ['nullable', 'string', 'max:20'],
        ]);

        $item = MenuItem::create([
            'menu_category_id' => $validated['menuCategoryId'],
            'display_name'     => $validated['displayName'],
            'short_name'       => $validated['shortName'] ?? null,
            'sku'              => $validated['sku'] ?? null,
            'code'             => $validated['code'] ?? null,
            'unit_price'       => $validated['unitPrice'],
            'tax_code'         => $validated['taxCode'] ?? null,
            'is_active'        => true,
        ]);

        return $this->success([
            'menuItemId'  => $item->id,
            'displayName' => $item->display_name,
            'unitPrice'   => (string) $item->unit_price,
        ], status: 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $validated = $request->validate([
            'menuCategoryId' => ['nullable', 'exists:menu_categories,id'],
            'displayName'    => ['nullable', 'string', 'max:100'],
            'shortName'      => ['nullable', 'string', 'max:50'],
            'sku'            => ['nullable', 'string', 'max:50'],
            'code'           => ['nullable', 'string', 'max:50'],
            'unitPrice'      => ['nullable', 'numeric', 'min:0'],
            'taxCode'        => ['nullable', 'string', 'max:20'],
            'isActive'       => ['nullable', 'boolean'],
        ]);

        $update = [];
        if (array_key_exists('menuCategoryId', $validated)) $update['menu_category_id'] = $validated['menuCategoryId'];
        if (array_key_exists('displayName', $validated))    $update['display_name'] = $validated['displayName'];
        if (array_key_exists('shortName', $validated))      $update['short_name'] = $validated['shortName'];
        if (array_key_exists('sku', $validated))            $update['sku'] = $validated['sku'];
        if (array_key_exists('code', $validated))           $update['code'] = $validated['code'];
        if (array_key_exists('unitPrice', $validated))      $update['unit_price'] = $validated['unitPrice'];
        if (array_key_exists('taxCode', $validated))        $update['tax_code'] = $validated['taxCode'];
        if (array_key_exists('isActive', $validated))       $update['is_active'] = $validated['isActive'];

        $menuItem->update($update);

        return $this->success([
            'menuItemId'  => $menuItem->id,
            'displayName' => $menuItem->display_name,
            'unitPrice'   => (string) $menuItem->unit_price,
            'isActive'    => $menuItem->is_active,
        ]);
    }
}
