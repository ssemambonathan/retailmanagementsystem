<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(Sale::with('items.product')->latest('sold_at')->paginate(25));
    }

    public function show(string $id): JsonResponse
    {
        return $this->success(Sale::with('items.product')->findOrFail($id));
    }

    public function store(Request $request, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $sale = $inventory->recordSale($data['items'], $request->user()?->getAuthIdentifier());

        return $this->created($sale, 'Sale recorded successfully.');
    }
}
