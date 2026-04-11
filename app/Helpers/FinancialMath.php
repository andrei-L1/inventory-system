<?php

namespace App\Helpers;

/**
 * FinancialMath — BCMath wrapper for ERP-grade financial arithmetic.
 *
 * PHP native floats are IEEE 754 binary-based, which means operations like
 * 0.1 + 0.2 do not equal 0.3 exactly. For a financial system where totals
 * must reconcile to the cent, BCMath provides arbitrary-precision decimal
 * arithmetic that eliminates this class of error entirely.
 *
 * Usage:
 *   $lineTotal = FinancialMath::lineTotal($qty, $price, $discountRate, $taxRate);
 *   $header    = FinancialMath::headerTotal($lineTotals); // returns 2dp string
 */
class FinancialMath
{
    /** Default scale for all line-level intermediate calculations. */
    public const LINE_SCALE = 8;

    /** Final scale for header totals (GAAP-compliant 2dp). */
    public const HEADER_SCALE = 2;

    // ─── Primitives ────────────────────────────────────────────────────────────

    public static function mul(string|float|int $a, string|float|int $b, int $scale = self::LINE_SCALE): string
    {
        return bcmul((string) $a, (string) $b, $scale);
    }

    public static function div(string|float|int $a, string|float|int $b, int $scale = self::LINE_SCALE): string
    {
        return bcdiv((string) $a, (string) $b, $scale);
    }

    public static function add(string|float|int $a, string|float|int $b, int $scale = self::LINE_SCALE): string
    {
        return bcadd((string) $a, (string) $b, $scale);
    }

    public static function sub(string|float|int $a, string|float|int $b, int $scale = self::LINE_SCALE): string
    {
        return bcsub((string) $a, (string) $b, $scale);
    }

    /**
     * Round a BCMath string to the desired scale.
     * BCMath itself truncates, not rounds. We implement half-up rounding.
     */
    public static function round(string|float|int $value, int $scale): string
    {
        $value = (string) $value;
        $half  = '0.'.str_repeat('0', $scale).'5';

        if ($value[0] === '-') {
            return bcsub($value, $half, $scale);
        }

        return bcadd($value, $half, $scale);
    }

    /**
     * Compare two values. Returns -1, 0, or 1 (like spaceship operator).
     */
    public static function cmp(string|float|int $a, string|float|int $b, int $scale = self::LINE_SCALE): int
    {
        return bccomp((string) $a, (string) $b, $scale);
    }

    // ─── Higher-Order Helpers ──────────────────────────────────────────────────

    /**
     * Calculate a single PO line cost: qty × unit_cost, rounded to LINE_SCALE.
     */
    public static function poLineCost(string|float|int $qty, string|float|int $unitCost): string
    {
        return self::round(self::mul($qty, $unitCost), self::LINE_SCALE);
    }

    /**
     * Calculate a SO line subtotal applying discount and tax.
     * All intermediate values stay at LINE_SCALE to prevent drift.
     * Returns an 8dp string.
     *
     * Formula: (qty * price) * (1 - discount%) * (1 + tax%)
     */
    public static function soLineSubtotal(
        string|float|int $qty,
        string|float|int $unitPrice,
        string|float|int $discountRate = 0,
        string|float|int $taxRate = 0,
    ): string {
        $base     = self::mul($qty, $unitPrice);
        $discount = self::mul($base, self::div($discountRate, 100));
        $taxable  = self::sub($base, $discount);
        $tax      = self::mul($taxable, self::div($taxRate, 100));

        return self::round(self::add($taxable, $tax), self::LINE_SCALE);
    }

    /**
     * Calculate SO line tax amount only (for storage in tax_amount column).
     */
    public static function soLineTax(
        string|float|int $qty,
        string|float|int $unitPrice,
        string|float|int $discountRate = 0,
        string|float|int $taxRate = 0,
    ): string {
        $base     = self::mul($qty, $unitPrice);
        $discount = self::mul($base, self::div($discountRate, 100));
        $taxable  = self::sub($base, $discount);

        return self::round(self::mul($taxable, self::div($taxRate, 100)), self::LINE_SCALE);
    }

    /**
     * Calculate SO line discount amount only (for storage in discount_amount column).
     */
    public static function soLineDiscount(
        string|float|int $qty,
        string|float|int $unitPrice,
        string|float|int $discountRate = 0,
    ): string {
        $base = self::mul($qty, $unitPrice);

        return self::round(self::mul($base, self::div($discountRate, 100)), self::LINE_SCALE);
    }

    /**
     * Sum an array of 8dp line totals in BCMath precision, then round
     * to 2dp for the GAAP-compliant header total_amount.
     *
     * @param  iterable<string|float|int>  $lineTotals
     */
    public static function headerTotal(iterable $lineTotals): string
    {
        $sum = '0';
        foreach ($lineTotals as $lineTotal) {
            $sum = self::add($sum, $lineTotal, self::LINE_SCALE);
        }

        return self::round($sum, self::HEADER_SCALE);
    }
}
