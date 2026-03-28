<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_it_can_create_product_and_initializes_inventory()
    {
        $category = Category::first();
        $uom = UnitOfMeasure::first();
        $costing = CostingMethod::first();
        $activeLocationsCount = Location::where('is_active', true)->count();

        $data = [
            'product_code' => 'TEST-001',
            'name' => 'API Test Product',
            'sku' => 'SKU-001',
            'category_id' => $category->id,
            'uom_id' => $uom->id,
            'costing_method_id' => $costing->id,
            'selling_price' => 199.99,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/products', $data);

        $response->assertJsonPath('data.name', 'API Test Product');

        $productId = $response->json('data.id');

        // Verify Inventory Initialization
        $inventoryCount = Inventory::where('product_id', $productId)->count();
        $this->assertEquals($activeLocationsCount, $inventoryCount, 'Inventory rows were not initialized for all locations.');
    }

    public function test_it_can_list_products_with_filters()
    {
        // Seeder already creates some products? Let's check or create one.
        $this->getJson('/api/products')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }
}
