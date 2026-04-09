<?php

namespace App\Models;

use App\Helpers\UomHelper;
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
                $abbreviation = substr($abbreviation, 0, 4);
            } else {
                $abbreviation = strtoupper(substr($words[0], 0, 3));
            }

            // 2. Automated Internal ID (SKU)
            // Pattern: [CAT_CODE]-[ABBR]-[SERIAL]
            if (empty($product->sku)) {
                $category = Category::find($product->category_id);
                $catPrefix = $category?->code ?: strtoupper(substr($category?->name ?: 'GEN', 0, 3));

                $nextId = (self::max('id') ?? 0) + 1;
                $serial = str_pad($nextId, 4, '0', STR_PAD_LEFT);

                $product->sku = "{$catPrefix}-{$abbreviation}-{$serial}";
            }

            // 3. Automated Vendor Code (Product Code / MPN)
            // Removed auto-generation. Users should explicitly provide the Manufacturer Part Number (MPN) if applicable.
            // if (empty($product->product_code)) {
            //     $product->product_code = "PRD-{$abbreviation}-".strtoupper(substr(uniqid(), -4));
            // }
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
     * Get the average cost scaled to the primary unit of measure.
     * Stored value is now "Cost per Piece" (Atomic).
     */
    public function getAverageCostAttribute($value): float
    {
        $multiplier = UomHelper::getMultiplierToSmallest($this->uom_id, $this->id, false);

        return $multiplier > 0 ? (float) $value * $multiplier : (float) $value;
    }

    /**
     * Get the total quantity on hand across all locations.
     */
    public function getTotalQohAttribute(): float
    {
        // Internal QOH is now stored in Atomic Pieces.
        // We convert it back to the Product's Base UOM for catalog display.
        $pieces = (float) $this->inventories()->sum('quantity_on_hand');
        $multiplier = UomHelper::getMultiplierToSmallest($this->uom_id, $this->id, false);

        return $multiplier > 0 ? $pieces / $multiplier : $pieces;
    }

    /**
     * Get the formatted total quantity on hand across all locations.
     */
    public function getFormattedTotalQohAttribute(): string
    {
        // total_qoh is now correctly scaled to the product's UOM in the getter above.
        return UomHelper::format($this->total_qoh, $this->uom_id, $this->id, false);
    }
}
