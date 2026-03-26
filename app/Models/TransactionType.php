<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionType extends Model
{
    protected $fillable = ['name', 'code', 'affects_inventory', 'is_debit'];

    protected $casts = [
        'affects_inventory' => 'boolean',
        'is_debit' => 'boolean',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Check if the type matches a specific code.
     */
    public function matchesCode(string $code): bool
    {
        return strtoupper($this->code) === strtoupper($code);
    }


    /**
     * Get the multiplier for stock updates (1 for debit, -1 for credit).
     */
    public function getStockMultiplier(): int
    {
        return $this->is_debit ? 1 : -1;
    }
}

