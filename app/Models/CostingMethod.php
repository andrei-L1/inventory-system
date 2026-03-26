<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostingMethod extends Model
{
    protected $fillable = [
        'name',
        'label',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the products using this costing method.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the transaction lines using this costing method.
     */
    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    /**
     * Helper to check for specific methods by name (e.g., 'fifo', 'lifo').
     */
    public function matchesName(string $methodName): bool
    {
        return strtolower($this->name) === strtolower($methodName);
    }
}
