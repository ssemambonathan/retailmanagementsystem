<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** @param array<int, array{product_id: string, quantity: int}> $items */
    public function recordSale(array $items, ?string $userId): Sale
    {
        return DB::transaction(function () use ($items, $userId): Sale {
            // Acquire row locks in a stable order to reduce deadlocks for multi-item sales.
            $productIds = collect($items)->pluck('product_id')->unique()->sort()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $prices = [];
            $subtotalCents = 0;

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);
                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['The selected product is unavailable.'],
                    ]);
                }

                $quantity = (int) $item['quantity'];
                if ($product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Only {$product->stock_quantity} unit(s) of {$product->name} are in stock."],
                    ]);
                }

                $unitCents = (int) round(((float) $product->unit_price) * 100);
                $lineCents = $unitCents * $quantity;
                $prices[$product->id] = ['unit_cents' => $unitCents, 'line_cents' => $lineCents];
                $subtotalCents += $lineCents;
            }

            $total = $this->money($subtotalCents);
            $sale = Sale::create([
                'user_id' => $userId,
                'subtotal' => $total,
                'total' => $total,
                'status' => 'completed',
                'sold_at' => now(),
            ]);

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];
                $price = $prices[$product->id];

                // A conditional decrement is a second guard against overselling.
                $updated = Product::query()
                    ->whereKey($product->id)
                    ->where('stock_quantity', '>=', $quantity)
                    ->decrement('stock_quantity', $quantity);

                if ($updated !== 1) {
                    throw ValidationException::withMessages([
                        'items' => ["Stock changed while recording the sale for {$product->name}; please retry."],
                    ]);
                }

                $sale->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $this->money($price['unit_cents']),
                    'total_price' => $this->money($price['line_cents']),
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'sale',
                    'quantity' => -$quantity,
                    'unit_price' => $this->money($price['unit_cents']),
                    'reference_id' => $sale->id,
                    'notes' => "Sale {$sale->id}",
                ]);
            }

            return $sale->load('items.product');
        }, attempts: 3);
    }

    public function adjustStock(Product $product, int $quantity, ?string $notes = null): Product
    {
        return DB::transaction(function () use ($product, $quantity, $notes): Product {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $newStock = $locked->stock_quantity + $quantity;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Stock adjustment would make {$locked->name} stock negative."],
                ]);
            }

            $locked->stock_quantity = $newStock;
            $locked->save();

            StockMovement::create([
                'product_id' => $locked->id,
                'type' => 'adjustment',
                'quantity' => $quantity,
                'reference_id' => null,
                'notes' => $notes,
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
