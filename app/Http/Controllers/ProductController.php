<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->orderBy('name');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($builder) => $builder
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('sku', 'ilike', "%{$search}%"));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }

        return $this->success($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function show(string $id): JsonResponse
    {
        return $this->success(Product::query()->where('is_active', true)->findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = DB::transaction(function () use ($data): Product {
            $product = Product::create($data);
            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'opening',
                    'quantity' => $product->stock_quantity,
                    'unit_price' => $product->unit_price,
                    'notes' => 'Opening stock',
                ]);
            }

            return $product;
        });

        return $this->created($product, 'Product created successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validate([
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'gt:0', 'decimal:0,2'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Stock is intentionally not editable here; use the audited stock-adjustment endpoint.
        $product->update($data);

        return $this->success($product->refresh(), 'Product updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return $this->success(null, 'Product archived successfully.');
    }

    public function adjustStock(Request $request, string $id, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'not_in:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = $inventory->adjustStock(Product::findOrFail($id), (int) $data['quantity'], $data['notes'] ?? null);

        return $this->success($product, 'Stock adjusted successfully.');
    }
}
