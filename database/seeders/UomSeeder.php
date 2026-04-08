<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            // ==========================================
            // COUNT / PACKAGING (Discrete Layer)
            // ==========================================
            // Base Unit
            [
                'name' => 'Piece',
                'abbreviation' => 'PCS',
                'category' => 'count',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            // Generic Common Packaging Units (Rules mapped dynamically per product, but registered globally here)
            [
                'name' => 'Dozen',
                'abbreviation' => 'DZN',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null, // Intentionally null for count. Custom mapped via uom_conversions
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Box',
                'abbreviation' => 'BOX',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Case',
                'abbreviation' => 'CASE',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Pallet',
                'abbreviation' => 'PLT',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],

            // ==========================================
            // MASS / WEIGHT (Universal Scaling)
            // ==========================================
            // Base Unit
            [
                'name' => 'Gram',
                'abbreviation' => 'G',
                'category' => 'mass',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Milligram',
                'abbreviation' => 'MG',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 0.001,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Kilogram',
                'abbreviation' => 'KG',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 1000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Metric Ton',
                'abbreviation' => 'TON',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 1000000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Ounce',
                'abbreviation' => 'OZ',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 28.3495,
                'decimals' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Pound',
                'abbreviation' => 'LB',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 453.592,
                'decimals' => 4,
                'is_active' => true,
            ],

            // ==========================================
            // VOLUME (Universal Scaling)
            // ==========================================
            // Base Unit
            [
                'name' => 'Milliliter',
                'abbreviation' => 'ML',
                'category' => 'volume',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Liter',
                'abbreviation' => 'L',
                'category' => 'volume',
                'is_base' => false,
                'conversion_factor_to_base' => 1000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Cubic Centimeter',
                'abbreviation' => 'CM3',
                'category' => 'volume',
                'is_base' => false,
                'conversion_factor_to_base' => 1,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Gallon (US)',
                'abbreviation' => 'GAL',
                'category' => 'volume',
                'is_base' => false,
                'conversion_factor_to_base' => 3785.41,
                'decimals' => 3,
                'is_active' => true,
            ],

            // ==========================================
            // LENGTH (Universal Scaling)
            // ==========================================
            // Base Unit
            [
                'name' => 'Millimeter',
                'abbreviation' => 'MM',
                'category' => 'length',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Centimeter',
                'abbreviation' => 'CM',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 10,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Meter',
                'abbreviation' => 'M',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 1000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Inch',
                'abbreviation' => 'IN',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 25.4,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Foot',
                'abbreviation' => 'FT',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 304.8,
                'decimals' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($units as $u) {
            UnitOfMeasure::updateOrCreate(
                ['abbreviation' => $u['abbreviation']],
                $u
            );
        }
    }
}
