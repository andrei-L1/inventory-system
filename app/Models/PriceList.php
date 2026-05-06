<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    protected $fillable = ['name', 'currency', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class)->orderBy('product_id')->orderBy('min_quantity');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Resolve the best price for a given product and quantity.
     * Picks the item with the highest min_quantity that does not exceed $qty.
     */
    public function resolvePrice(int|string $productId, string $qty = '1'): ?string
    {
        $item = $this->items()
            ->where('product_id', $productId)
            ->where('min_quantity', '<=', $qty)
            ->orderByDesc('min_quantity')
            ->first();

        return $item ? (string) $item->price : null;
    }
}
