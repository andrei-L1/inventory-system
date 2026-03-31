<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $product_code
 * @property string $name
 * @property string|null $description
 * @property int $category_id
 * @property int $uom_id
 * @property int|null $preferred_vendor_id
 * @property string|null $brand
 * @property string $sku
 * @property string|null $barcode
 * @property int $costing_method_id
 * @property float $average_cost
 * @property float $selling_price
 * @property float $reorder_point
 * @property float $reorder_quantity
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read float $total_qoh
 * @property-read Category $category
 * @property-read UnitOfMeasure $uom
 * @property-read Vendor|null $preferredVendor
 * @property-read CostingMethod $costingMethod
 * @property-read Collection|Inventory[] $inventories
 * @property-read Collection|InventoryCostLayer[] $costLayers
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Product query()
 */
class Product extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // 1. Generate the Suffix (Abbreviation)
            // Strategy: Take first letter of each word. If one word, take first 3 letters.
            $nameClean = preg_replace('/[^A-Za-z0-9 ]/', '', $product->name);
            $words = explode(' ', strtoupper(trim($nameClean)));

            if (count($words) > 1) {
                $abbreviation = '';
                foreach ($words as $w) {
                    if (! empty($w)) {
                        $abbreviation .= $w[0];
                    }
                }
                // Limit to 4 letters for the acronym if it's very long
                $abbreviation = substr($abbreviation, 0, 4);
            } else {
                $abbreviation = substr($words[0], 0, 3);
            }

            // 2. Automated Internal ID (SKU)
            if (empty($product->sku)) {
                $nextId = (self::max('id') ?? 0) + 1;
                $paddedId = str_pad($nextId, 12, '0', STR_PAD_LEFT);
                $product->sku = "{$paddedId}-{$abbreviation}";
            }

            // 3. Automated Vendor Code (Product Code)
            if (empty($product->product_code)) {
                $product->product_code = "PRD-{$abbreviation}-".strtoupper(substr(uniqid(), -4));
            }
        });
    }

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
        'costing_method_id',
        'average_cost',
        'selling_price',
        'reorder_point',
        'reorder_quantity',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the costing method for this product.
     */
    public function costingMethod(): BelongsTo
    {
        return $this->belongsTo(CostingMethod::class);
    }

    /**
     * Get the vendor that this product prefers.
     */
    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }

    /**
     * Get the category for this product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the unit of measure for this product.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    /**
     * Get the current inventory levels for this product.
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Get the transactions this product has been involved in.
     */
    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    /**
     * Get the cost layers for this product.
     */
    public function costLayers(): HasMany
    {
        return $this->hasMany(InventoryCostLayer::class);
    }

    /**
     * Get the total quantity on hand across all locations.
     */
    public function getTotalQohAttribute(): float
    {
        return (float) $this->inventories()->sum('quantity_on_hand');
    }
}
