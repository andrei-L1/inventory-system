<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CostingMethod;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->unique()->lexify('SKU-????')),
            'product_code' => strtoupper($this->faker->unique()->lexify('PC-????')),
            'brand' => $this->faker->company,
            'category_id' => Category::factory(),
            'uom_id' => UnitOfMeasure::factory(),
            'costing_method_id' => CostingMethod::first()?->id ?? 1,
            'selling_price' => $this->faker->randomFloat(2, 10, 1000),
            'average_cost' => 0,
            'reorder_point' => $this->faker->numberBetween(10, 50),
            'is_active' => true,
        ];
    }
}
