<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomController extends Controller
{
    public function show(Product $product): JsonResponse
    {
        return response()->json($product->bomItems()->with('material:id,code,name,unit')->orderBy('id')->get());
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.material_id' => ['required', 'distinct', 'exists:materials,id'],
            'items.*.qty_per_unit' => ['required', 'numeric', 'gt:0'],
            'items.*.scrap_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        DB::transaction(function () use ($product, $data) {
            $product->bomItems()->delete();
            foreach ($data['items'] as $item) {
                $product->bomItems()->create([
                    'material_id' => $item['material_id'],
                    'qty_per_unit' => $item['qty_per_unit'],
                    'scrap_percent' => $item['scrap_percent'] ?? 0,
                ]);
            }
        });

        return $this->show($product);
    }
}
