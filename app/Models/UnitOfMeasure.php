<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $abbreviation
 * @property bool $is_active
 */
class UnitOfMeasure extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'name',
        'abbreviation',
        'category',
        'is_base',
        'conversion_factor_to_base',
        'decimals',
        'is_active',
    ];

    protected $casts = [
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'conversion_factor_to_base' => 'float',
        'decimals' => 'integer',
    ];
}
