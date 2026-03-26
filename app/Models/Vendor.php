<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'tax_id',
        'is_active',
    ];

    /**
     * Get the products that prefer this vendor.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'preferred_vendor_id');
    }

    /**
     * Get the transactions associated with this vendor.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
