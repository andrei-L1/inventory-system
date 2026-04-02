<?php

namespace App\Helpers;

use App\Models\UnitOfMeasure;
use App\Models\UomConversion;
use Illuminate\Support\Collection;

class UomHelper
{
    private static ?Collection $conversions = null;

    private static array $uoms = [];

    /**
     * Format a quantity based on the UOM type (Discrete vs. Continuous).
     */
    public static function format($quantity, int $uomId): string
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

        // Continuous units (KG, L, etc.) always show decimals
        if (! self::isDiscrete($uom->abbreviation)) {
            return round($quantity, 4).' '.$uom->abbreviation;
        }

        // Discrete units (BOX, PCS) use recursive breakdown if fractional
        return self::formatDiscrete($quantity, $uomId);
    }

    /**
     * Handle recursive dual-UOM breakdown for discrete units.
     */
    private static function formatDiscrete(float $quantity, int $uomId): string
    {
        $uom = self::getUom($uomId);
        $absQty = abs($quantity);
        $isNegative = $quantity < -0.00000001;

        // Base case: If it's a whole number, just show it
        if (abs($absQty - round($absQty)) < 0.000001) {
            $res = round($absQty).' '.$uom->abbreviation;

            // NEW: Add expanded total if there's a smaller unit
            $conversion = self::$conversions->where('from_uom_id', $uomId)->sortByDesc('conversion_factor')->first();
            if ($conversion && $absQty > 0) {
                // We keep traversing to find the absolute smallest to show the total
                $multiplierToSmallest = self::getMultiplierToSmallest($uomId);
                if ($multiplierToSmallest > 1) {
                    $totalSmallest = round($absQty * $multiplierToSmallest, 2);
                    $smallestUom = self::getUom(self::getSmallestUnitId($uomId));
                    $res .= ' ['.$totalSmallest.' '.($smallestUom->abbreviation ?? 'pcs').']';
                }
            }

            return $isNegative ? '-'.$res : $res;
        }

        // It has a fraction. Try to find a smaller unit.
        $conversion = self::$conversions
            ->where('from_uom_id', $uomId)
            ->sortByDesc('conversion_factor')
            ->first();

        if (! $conversion) {
            // No smaller unit found, show original decimal
            $res = round($absQty, 4).' '.$uom->abbreviation;

            return $isNegative ? '-'.$res : $res;
        }

        $multiplier = $conversion->conversion_factor;
        $wholeUnits = floor($absQty + 0.000001);
        $remainder = $absQty - $wholeUnits;
        $smallerQty = $remainder * $multiplier;

        $parts = [];
        if ($wholeUnits > 0) {
            $parts[] = $wholeUnits.' '.$uom->abbreviation;
        }

        // Recurse into the smaller unit
        $smallerPart = self::formatDiscrete($smallerQty, $conversion->to_uom_id);

        // Remove minus sign from recursion if parent handled it
        if (str_starts_with($smallerPart, '-')) {
            $smallerPart = substr($smallerPart, 1);
        }

        $parts[] = $smallerPart;

        $res = implode(', ', $parts);

        return $isNegative ? '-'.$res : $res;
    }

    /**
     * List of mass/volume base units that should never be broken down.
     */
    public static function isDiscrete(string $abbr): bool
    {
        $nonDiscrete = ['KG', 'L', 'M', 'ML', 'G', 'LB', 'OZ', 'CM', 'MM', 'FT', 'IN'];

        return ! in_array(strtoupper($abbr), $nonDiscrete);
    }

    /**
     * Find the absolute smallest unit (PCS) by traversing the conversion chain down.
     */
    public static function getSmallestUnitId(?int $startingUomId): ?int
    {
        if (!$startingUomId) return null;
        self::ensureCacheLoaded();

        $currentUomId = $startingUomId;
        $processed = [$currentUomId];

        while (true) {
            $outgoing = self::$conversions->where('from_uom_id', $currentUomId);
            $bestConv = $outgoing->sortByDesc('conversion_factor')->first();

            if (! $bestConv || in_array($bestConv->to_uom_id, $processed)) {
                break;
            }

            $currentUomId = $bestConv->to_uom_id;
            $processed[] = $currentUomId;
        }

        return $currentUomId;
    }

    /**
     * Calculate the total multiplier to get from any UOM in the chain to the smallest base unit.
     */
    public static function getMultiplierToSmallest(?int $fromUomId): float
    {
        if (!$fromUomId) return 1.0;
        self::ensureCacheLoaded();

        $smallestUnitId = self::getSmallestUnitId($fromUomId);
        if ($fromUomId === $smallestUnitId) {
            return 1.0;
        }

        $multiplier = 1.0;
        $current = $fromUomId;
        $processed = [$current];

        while ($current !== $smallestUnitId) {
            $outgoing = self::$conversions->where('from_uom_id', $current);
            $bestConv = $outgoing->sortByDesc('conversion_factor')->first();

            if (! $bestConv || in_array($bestConv->to_uom_id, $processed)) {
                break;
            }

            $multiplier *= $bestConv->conversion_factor;
            $current = $bestConv->to_uom_id;
            $processed[] = $current;
        }

        return $multiplier;
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
     * Get the conversion factor from one UOM to another by traversing the hierarchy.
     */
    public static function getConversionFactor(int $fromId, int $toId): float
    {
        self::ensureCacheLoaded();

        $fromBase = self::getSmallestUnitId($fromId);
        $toBase = self::getSmallestUnitId($toId);

        if ($fromBase !== $toBase) {
            throw new \Exception("No conversion defined between UOM #{$fromId} and #{$toId} (different base units: {$fromBase} vs {$toBase}).");
        }

        $fromMult = self::getMultiplierToSmallest($fromId);
        $toMult = self::getMultiplierToSmallest($toId);

        return $fromMult / $toMult;
    }

    private static function getUom(int $id): ?UnitOfMeasure
    {
        return self::$uoms[$id] ?? null;
    }
}
