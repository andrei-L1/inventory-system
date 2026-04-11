<?php

namespace App\Helpers;

use App\Models\UnitOfMeasure;
use App\Models\UomConversion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class UomHelper
{
    private static ?Collection $conversions = null;

    private static array $uoms = [];

    /**
     * Format a quantity based on the UOM type (Discrete vs. Continuous).
     */
    public static function format($quantity, int $uomId, ?int $productId = null, bool $throw = false): string
    {
        if ($quantity === null) {
            return '0';
        }
        $quantity = (float) $quantity;

        self::ensureCacheLoaded();
        $uom = self::getUom($uomId);
        if (! $uom) {
            return (string) $quantity;
        }

        // Mass / Volume auto-scaling (e.g. 1500g -> 1.5 kg)
        if ($uom->category !== 'count') {
            if ($uom->is_base && abs($quantity) > 0) {
                $scaledUom = null;
                $bestFactor = 1.0;
                foreach (self::$uoms as $candidate) {
                    if ($candidate->category === $uom->category && ! $candidate->is_base && $candidate->conversion_factor_to_base) {
                        if (abs($quantity) >= $candidate->conversion_factor_to_base && $candidate->conversion_factor_to_base > $bestFactor) {
                            $scaledUom = $candidate;
                            $bestFactor = $candidate->conversion_factor_to_base;
                        }
                    }
                }
                if ($scaledUom) {
                    $scaledQty = $quantity / $bestFactor;

                    return round($scaledQty, $scaledUom->decimals).' '.$scaledUom->abbreviation;
                }
            }

            return round($quantity, $uom->decimals).' '.$uom->abbreviation;
        }

        // Discrete units (BOX, PCS) use recursive breakdown if fractional
        return self::formatDiscrete($quantity, $uomId, $productId, $throw);
    }

    /**
     * Handle recursive dual-UOM breakdown for discrete units.
     */
    private static function formatDiscrete(float $quantity, int $uomId, ?int $productId = null, bool $throw = false): string
    {
        $uom = self::getUom($uomId);
        $absQty = abs($quantity);
        $isNegative = $quantity < -0.00000001; // display only — float ok here

        // Base case: whole number check (display only — float epsilon ok here)
        if (abs($absQty - round($absQty)) < 0.00000001) {
            $res = round($absQty).' '.$uom->abbreviation;

            // Expand to base unit if this is a packaging unit
            if (! $uom->is_base) {
                $multiplier = self::getMultiplierToSmallest($uomId, $productId, $throw);
                if ($multiplier > 1) {
                    $totalSmallest = round($absQty * $multiplier);
                    $smallestUom = self::getUom(self::getSmallestUnitId($uomId));
                    $res .= ' ['.$totalSmallest.' '.($smallestUom->abbreviation ?? 'pcs').']';
                }
            }

            return $isNegative ? '-'.$res : $res;
        }

        // It has a fraction. Get the conversion rule to breakdown.
        $query = self::$conversions->where('from_uom_id', $uomId);
        $conversion = null;
        if ($productId) {
            $conversion = $query->where('product_id', $productId)->first();
        }
        if (! $conversion) {
            $conversion = $query->whereNull('product_id')->first();
        }

        if (! $conversion) {
            // No conversion rule found, show original decimal bounded by defined precision
            $res = round($absQty, $uom->decimals).' '.$uom->abbreviation;

            return $isNegative ? '-'.$res : $res;
        }

        $multiplier = $conversion->conversion_factor;
        $wholeUnits = floor($absQty + 0.00000001); // display only — float epsilon ok here
        $remainder = $absQty - $wholeUnits;
        $smallerQty = $remainder * $multiplier;

        $parts = [];
        if ($wholeUnits > 0) {
            $parts[] = $wholeUnits.' '.$uom->abbreviation;
        }

        // Recurse into the smaller unit
        $smallerPart = self::formatDiscrete($smallerQty, $conversion->to_uom_id, $productId, $throw);

        if (str_starts_with($smallerPart, '-')) {
            $smallerPart = substr($smallerPart, 1);
        }

        $parts[] = $smallerPart;

        $res = implode(', ', $parts);

        return $isNegative ? '-'.$res : $res;
    }

    /**
     * @deprecated Use check on $uom->category === 'count' instead. Left for compatibility.
     */
    public static function isDiscrete(string $abbr): bool
    {
        self::ensureCacheLoaded();
        foreach (self::$uoms as $u) {
            if (strcasecmp($u->abbreviation, $abbr) === 0) {
                return $u->category === 'count';
            }
        }

        return true;
    }

    /**
     * Find the absolute smallest unit by category traversal.
     */
    public static function getSmallestUnitId(?int $startingUomId, ?int $productId = null): ?int
    {
        if (! $startingUomId) {
            return null;
        }
        self::ensureCacheLoaded();
        $uom = self::getUom($startingUomId);
        if (! $uom) {
            return null;
        }

        if ($uom->is_base) {
            return $uom->id;
        }

        foreach (self::$uoms as $baseUom) {
            if ($baseUom->category === $uom->category && $baseUom->is_base) {
                return $baseUom->id;
            }
        }

        return $startingUomId;
    }

    /**
     * Calculate the multiplier to get from any UOM cleanly to its base unit.
     * Returns a BCMath string — safe to pass directly into FinancialMath::mul/div.
     */
    public static function getMultiplierToSmallest(?int $fromUomId, ?int $productId = null, bool $throw = true): string
    {
        if (! $fromUomId) {
            return '1';
        }

        self::ensureCacheLoaded();
        $uom = self::getUom($fromUomId);
        if (! $uom || $uom->is_base) {
            return '1';
        }

        if ($uom->category !== 'count') {
            // conversion_factor_to_base comes from DB as string — pass directly to BCMath.
            return (string) ($uom->conversion_factor_to_base ?? '1');
        }

        // Contextual Counting layer uses the Product-aware conversions table
        $query = self::$conversions->where('from_uom_id', $fromUomId);

        $bestConv = null;
        if ($productId) {
            $bestConv = $query->where('product_id', $productId)->first();
        }
        if (! $bestConv) {
            $bestConv = $query->whereNull('product_id')->first();
        }

        if ($bestConv) {
            return (string) $bestConv->conversion_factor;
        }

        if (! $throw) {
            return '1';
        }

        throw ValidationException::withMessages([
            'uom_id' => 'Missing conversion rule for this unit ('.$uom->abbreviation.'). Please define its packaging size.',
        ]);
    }

    public static function clearCache(): void
    {
        self::$conversions = null;
        self::$uoms = [];
    }

    private static function ensureCacheLoaded(): void
    {
        if (self::$conversions !== null) {
            return;
        }
        self::$conversions = UomConversion::all();
        foreach (UnitOfMeasure::all() as $uom) {
            self::$uoms[$uom->id] = $uom;
        }
    }

    /**
     * Get the conversion factor from one UOM to another.
     * Returns a BCMath string — safe to pass directly into FinancialMath::mul/div
     * in StockService::applyUomConversion() without any (float) cast.
     */
    public static function getConversionFactor(int $fromId, int $toId, ?int $productId = null): string
    {
        self::ensureCacheLoaded();

        $fromBase = self::getSmallestUnitId($fromId, $productId);
        $toBase = self::getSmallestUnitId($toId, $productId);

        if ($fromBase !== $toBase) {
            throw new \Exception("No conversion defined between UOM #{$fromId} and #{$toId} (different base units: {$fromBase} vs {$toBase}).");
        }

        $fromMult = self::getMultiplierToSmallest($fromId, $productId);
        $toMult = self::getMultiplierToSmallest($toId, $productId);

        // BCMath division — no float arithmetic.
        return FinancialMath::div($fromMult, $toMult, FinancialMath::LINE_SCALE);
    }

    private static function getUom(int $id): ?UnitOfMeasure
    {
        return self::$uoms[$id] ?? null;
    }
}
