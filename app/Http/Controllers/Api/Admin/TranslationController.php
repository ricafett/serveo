<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\ApiController;
use App\Models\TranslationKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'languageCode' => ['required', 'string', 'max:10'],
        ]);

        $translations = TranslationKey::where('language_code', $validated['languageCode'])
            ->where('is_active', true)
            ->get();

        $grouped = $translations->groupBy('translation_namespace');

        $data = [];
        foreach ($grouped as $namespace => $items) {
            $data[$namespace] = $items->mapWithKeys(fn ($t) => [
                $t->translation_key => $t->translation_value,
            ])->all();
        }

        return $this->success([
            'languageCode' => $validated['languageCode'],
            'translations' => $data,
        ]);
    }
}
