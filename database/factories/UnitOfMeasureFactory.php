<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word().'_'.$this->faker->unique()->numberBetween(1, 9999),
            'abbreviation' => strtoupper($this->faker->unique()->lexify('??')),
            'is_active' => true,
        ];
    }
}
