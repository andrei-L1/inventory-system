<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $parent_id
 * @property bool $is_active
 * @property-read Collection|Product[] $products
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description', 'parent_id', 'is_active'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
