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
                'abbreviation' => 'pcs',
                'category' => 'count',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            // Generic Common Packaging Units
            [
                'name' => 'Dozen',
                'abbreviation' => 'dzn',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Box',
                'abbreviation' => 'bx',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Case',
                'abbreviation' => 'case',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Pallet',
                'abbreviation' => 'plt',
                'category' => 'count',
                'is_base' => false,
                'conversion_factor_to_base' => null,
                'decimals' => 0,
                'is_active' => true,
            ],

            // ==========================================
            // MASS / WEIGHT
            // ==========================================
            // Base Unit
            [
                'name' => 'Gram',
                'abbreviation' => 'g',
                'category' => 'mass',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Milligram',
                'abbreviation' => 'mg',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 0.001,
                'decimals' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Kilogram',
                'abbreviation' => 'kg',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 1000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Metric Ton',
                'abbreviation' => 'ton',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 1000000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Ounce',
                'abbreviation' => 'oz',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 28.3495,
                'decimals' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Pound',
                'abbreviation' => 'lb',
                'category' => 'mass',
                'is_base' => false,
                'conversion_factor_to_base' => 453.592,
                'decimals' => 4,
                'is_active' => true,
            ],

            // ==========================================
            // VOLUME
            // ==========================================
            // Base Unit
            [
                'name' => 'Milliliter',
                'abbreviation' => 'ml',
                'category' => 'volume',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Liter',
                'abbreviation' => 'l',
                'category' => 'volume',
                'is_base' => false,
                'conversion_factor_to_base' => 1000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Cubic Centimeter',
                'abbreviation' => 'cm3',
                'category' => 'volume',
                'is_base' => false,
                'conversion_factor_to_base' => 1,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Gallon (US)',
                'abbreviation' => 'gal',
                'category' => 'volume',
                'is_base' => false,
                'conversion_factor_to_base' => 3785.41,
                'decimals' => 3,
                'is_active' => true,
            ],

            // ==========================================
            // LENGTH
            // ==========================================
            // Base Unit
            [
                'name' => 'Millimeter',
                'abbreviation' => 'mm',
                'category' => 'length',
                'is_base' => true,
                'conversion_factor_to_base' => null,
                'decimals' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Centimeter',
                'abbreviation' => 'cm',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 10,
                'decimals' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Meter',
                'abbreviation' => 'm',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 1000,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Inch',
                'abbreviation' => 'in',
                'category' => 'length',
                'is_base' => false,
                'conversion_factor_to_base' => 25.4,
                'decimals' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Foot',
                'abbreviation' => 'ft',
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
