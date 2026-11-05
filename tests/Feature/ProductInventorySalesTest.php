<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInventorySalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function tokenFor(string $role): string
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);

        return auth('api')->login($user);
    }

    public function test_admin_can_create_product_and_opening_stock_is_recorded(): void
    {
        $token = $this->tokenFor('Admin');

        $response = $this->withToken($token)->postJson('/api/v1/admin/products', [
            'sku' => 'TEA-001',
            'name' => 'Tea',
            'unit_price' => '4.25',
            'stock_quantity' => 10,
        ]);

        $response->assertCreated()->assertJsonPath('data.stock_quantity', 10);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $response->json('data.id'),
            'type' => 'opening',
            'quantity' => 10,
        ]);
    }

    public function test_sale_decrements_stock_and_records_line_and_movement(): void
    {
        $token = $this->tokenFor('Shop Attendant');
        $product = Product::create([
            'sku' => 'MILK-001', 'name' => 'Milk', 'unit_price' => '2.35',
            'stock_quantity' => 5, 'low_stock_threshold' => 1,
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/shop/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonPath('data.total', '4.70');
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $saleId = $response->json('data.id');
        $this->assertDatabaseHas('sale_items', ['sale_id' => $saleId, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'sale', 'quantity' => -2, 'reference_id' => $saleId]);
    }

    public function test_insufficient_stock_rejects_sale_without_partial_changes(): void
    {
        $token = $this->tokenFor('Shop Attendant');
        $available = Product::create(['sku' => 'P-1', 'name' => 'Available', 'unit_price' => '3.00', 'stock_quantity' => 5]);
        $short = Product::create(['sku' => 'P-2', 'name' => 'Short', 'unit_price' => '7.00', 'stock_quantity' => 1]);

        $this->withToken($token)->postJson('/api/v1/shop/sales', [
            'items' => [
                ['product_id' => $available->id, 'quantity' => 2],
                ['product_id' => $short->id, 'quantity' => 2],
            ],
        ])->assertUnprocessable();

        $this->assertSame(5, $available->fresh()->stock_quantity);
        $this->assertSame(1, $short->fresh()->stock_quantity);
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, StockMovement::where('type', 'sale')->count());
    }

    public function test_admin_stock_adjustment_is_audited_and_cannot_go_below_zero(): void
    {
        $token = $this->tokenFor('Admin');
        $product = Product::create(['sku' => 'P-3', 'name' => 'Item', 'unit_price' => '1.00', 'stock_quantity' => 4]);

        $this->withToken($token)->postJson("/api/v1/admin/products/{$product->id}/stock-adjustments", [
            'quantity' => 3,
            'notes' => 'Received delivery',
        ])->assertOk()->assertJsonPath('data.stock_quantity', 7);

        $this->withToken($token)->postJson("/api/v1/admin/products/{$product->id}/stock-adjustments", [
            'quantity' => -8,
        ])->assertUnprocessable();

        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'adjustment', 'quantity' => 3]);
    }

    public function test_shop_attendant_cannot_manage_products(): void
    {
        $this->withToken($this->tokenFor('Shop Attendant'))
            ->postJson('/api/v1/admin/products', [
                'sku' => 'NOPE', 'name' => 'No permission', 'unit_price' => '1.00',
            ])
            ->assertForbidden();
    }
}
