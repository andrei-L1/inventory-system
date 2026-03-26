<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'name',
        'description',
        'category_id',
        'uom_id',
        'preferred_vendor_id',
        'brand',
        'sku',
        'barcode',
        'costing_method',
        'average_cost',
        'selling_price',
        'reorder_point',
        'reorder_quantity',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the vendor that this product prefers.
     */
    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }
}
