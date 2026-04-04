<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\LocationType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->city.' Warehouse',
            'code' => strtoupper($this->faker->unique()->lexify('LOC-????')),
            'address' => $this->faker->address,
            'location_type_id' => LocationType::first()?->id ?? 1,
            'is_active' => true,
        ];
    }
}
