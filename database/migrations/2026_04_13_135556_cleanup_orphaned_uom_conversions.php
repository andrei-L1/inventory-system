<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Remove UomConversion records that reference a product
     * which has been hard-deleted from the products table.
     *
     * Soft-deleted products (deleted_at IS NOT NULL) are preserved
     * because they may still be needed for historical audit trails.
     */
    public function up(): void
    {
        $orphanedIds = DB::table('uom_conversions as uc')
            ->leftJoin('products as p', function ($join) {
                // Only join to non-soft-deleted products
                $join->on('uc.product_id', '=', 'p.id')
                    ->whereNull('p.deleted_at');
            })
            ->whereNotNull('uc.product_id')  // only product-specific rules
            ->whereNull('p.id')              // LEFT JOIN miss = product gone
            ->pluck('uc.id');

        if ($orphanedIds->isNotEmpty()) {
            Log::info('[UOM Cleanup] Removing '.$orphanedIds->count().' orphaned UomConversion rule(s): '.$orphanedIds->join(', '));
            DB::table('uom_conversions')->whereIn('id', $orphanedIds)->delete();
        } else {
            Log::info('[UOM Cleanup] No orphaned UomConversion rules found. Database is clean.');
        }
    }

    /**
     * Orphaned records cannot be meaningfully restored — no-op.
     */
    public function down(): void
    {
        // Intentional no-op: deleted orphans cannot be restored.
    }
};
