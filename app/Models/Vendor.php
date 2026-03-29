<?php

namespace App\Models;

use App\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $vendor_code
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $contact_person
 * @property bool $is_active
 */
class Vendor extends Model
{
    use HasAttachments, HasFactory, SoftDeletes;

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
