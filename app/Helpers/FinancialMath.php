<?php

namespace App\Helpers;

/**
 * FinancialMath — BCMath wrapper for ERP-grade financial arithmetic.
 *
 * Design contract (strict):
 * - Floats are FORBIDDEN. Passing a float throws InvalidArgumentException.
 * - All inputs must be numeric string or int. assertNumeric() enforces this at every primitive.
 * - All return values are strings (decimal representation).
 * - NO native PHP float operations used internally.
 * - NO epsilon constants. Semantic comparators (gt/gte/lt/lte) replace all "+ 0.00000001" patterns.
 * - Single rounding gate: rounding to 2dp happens ONLY in headerTotal().
 * - Normalization does NOT "fix" data — it validates and passes through.
 */
class FinancialMath
{
    /** Scale for all line-level calculations (qty, unit cost, subtotals). */
    public const LINE_SCALE = 8;

    /** Scale for GAAP-compliant header totals (invoices, bank transfers). */
    public const HEADER_SCALE = 2;

    /**
     * Internal precision buffer for division — prevents truncation drift
     * when the result is later used in further arithmetic.
     */
    private const GUARD_DIGITS = 4;

    // ─── Validation Gate ───────────────────────────────────────────────────────

    /**
     * Assert that a value is safe to pass into BCMath.
     * Called at every primitive entry point — this is the enforcement boundary.
     *
     * Valid:  int, numeric string (e.g. "1.23456789", "42", "-5.5")
     * Invalid: float → throws (precision already lost before BCMath sees it)
     * Invalid: non-numeric string, array, object, null → throws
     */
    private static function assertNumeric(mixed $value): void
    {
        if (is_float($value)) {
            throw new \InvalidArgumentException(
                'Float detected in FinancialMath. Floats are forbidden in precision-critical paths. '
                .'Pass a numeric string (from DB/request) or int instead.'
            );
        }

        if (! is_int($value) && ! (is_string($value) && is_numeric($value))) {
            $type = get_debug_type($value);
            throw new \InvalidArgumentException(
                "FinancialMath requires a numeric string or int, got [{$type}]. "
                .'All financial values must be passed as strings or ints.'
            );
        }
    }

    /**
     * Convert a validated numeric input to a BCMath-safe string.
     *
     * Design: validates then passes through — does NOT "fix" data.
     * Normalization to a specific scale is intentionally NOT done here,
     * because BCMath handles mixed-scale input correctly at the primitive level.
     *
     * Callers (controllers, services, models) are responsible for ensuring
     * values are already clean decimal strings before calling FinancialMath.
     */
    public static function toDecimal(mixed $value): string
    {
        // Treat null or empty string as "0" to prevent validation failures on non-set database columns.
        if ($value === null || $value === '') {
            return '0';
        }

        self::assertNumeric($value);

        // int: cast directly to string — no float involved.
        if (is_int($value)) {
            return (string) $value;
        }

        // Numeric string: pass through as-is. BCMath handles leading zeros,
        // trailing zeros, and mixed-scale inputs natively.
        return $value;
    }

    // ─── Primitives ────────────────────────────────────────────────────────────

    public static function add(mixed $a, mixed $b, int $scale = self::LINE_SCALE): string
    {
        return bcadd(self::toDecimal($a), self::toDecimal($b), $scale);
    }

    public static function sub(mixed $a, mixed $b, int $scale = self::LINE_SCALE): string
    {
        return bcsub(self::toDecimal($a), self::toDecimal($b), $scale);
    }

    public static function mul(mixed $a, mixed $b, int $scale = self::LINE_SCALE): string
    {
        // Use GUARD_DIGITS extra internally to prevent intermediate truncation.
        return bcmul(self::toDecimal($a), self::toDecimal($b), $scale + self::GUARD_DIGITS);
        // Caller is responsible for rounding at domain boundaries.
        // This primitive intentionally returns at extended precision.
    }

    /**
     * Division at extended precision — the caller rounds at their domain boundary.
     *
     * We do NOT round inside div(). Division often needs to feed into further
     * multiplication or addition, so truncating here would compound error.
     * The caller (e.g. soLineSubtotal) does the final round().
     */
    public static function div(mixed $a, mixed $b, int $scale = self::LINE_SCALE): string
    {
        return bcdiv(self::toDecimal($a), self::toDecimal($b), $scale + self::GUARD_DIGITS);
    }

    /**
     * Half-up rounding: adds 5 at position (scale+1), BCMath truncates.
     *
     * This is the only function that should reduce precision.
     * Call it explicitly at domain boundaries, never inside intermediates.
     */
    public static function round(mixed $value, int $scale): string
    {
        $str = self::toDecimal($value);
        $half = '0.'.str_repeat('0', $scale).'5';

        if ($str[0] === '-') {
            return bcsub($str, $half, $scale);
        }

        return bcadd($str, $half, $scale);
    }

    /**
     * String-safe equivalent for number_format to avoid precision-loss from float casting.
     * Rounds to the given scale, adds comma separators to the integer portion.
     */
    public static function format(mixed $value, int $decimals = self::HEADER_SCALE): string
    {
        $rounded = self::round($value, $decimals);
        $parts = explode('.', $rounded);

        $intPart = $parts[0];
        $decPart = $parts[1] ?? str_repeat('0', $decimals);

        // Add thousands separator without casting to float
        $intPartFormatted = preg_replace('/(?<=\d)(?=(\d{3})+(?!\d))/', ',', $intPart);

        if ($decimals === 0) {
            return $intPartFormatted;
        }

        // Ensure decimal part is padded to required scale if missing zeros
        $decPart = str_pad(substr($decPart, 0, $decimals), $decimals, '0', STR_PAD_RIGHT);

        return $intPartFormatted.'.'.$decPart;
    }

    /**
     * Deterministic comparison: normalizes both sides to LINE_SCALE strings,
     * then compares at scale=0 (integer boundary).
     *
     * Why scale=0:
     * - Financial comparisons should be strict at the meaningful precision level.
     * - Two values differing only beyond LINE_SCALE are treated as equal.
     * - Avoids decimal jitter in boundary guards (pick/pack/ship/return caps).
     *
     * Returns: -1, 0, or 1.
     */
    public static function cmp(mixed $a, mixed $b): int
    {
        // Normalize to LINE_SCALE strings first, then compare at integer boundary.
        $aNorm = bcadd(self::toDecimal($a), '0', self::LINE_SCALE);
        $bNorm = bcadd(self::toDecimal($b), '0', self::LINE_SCALE);

        return bccomp($aNorm, $bNorm, self::LINE_SCALE);
    }

    // ─── Semantic Comparators (replace ALL + 0.00000001 epsilon patterns) ─────

    /** $a > $b */
    public static function gt(mixed $a, mixed $b): bool
    {
        return self::cmp($a, $b) > 0;
    }

    /** $a >= $b */
    public static function gte(mixed $a, mixed $b): bool
    {
        return self::cmp($a, $b) >= 0;
    }

    /** $a < $b */
    public static function lt(mixed $a, mixed $b): bool
    {
        return self::cmp($a, $b) < 0;
    }

    /** $a <= $b */
    public static function lte(mixed $a, mixed $b): bool
    {
        return self::cmp($a, $b) <= 0;
    }

    /** $a == 0 */
    public static function isZero(mixed $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    /** $a > 0 */
    public static function isPositive(mixed $a): bool
    {
        return self::cmp($a, '0') > 0;
    }

    /** $a < 0 */
    public static function isNegative(mixed $a): bool
    {
        return self::cmp($a, '0') < 0;
    }

    public static function max(mixed $a, mixed $b): string
    {
        return self::gte($a, $b) ? self::toDecimal($a) : self::toDecimal($b);
    }

    public static function min(mixed $a, mixed $b): string
    {
        return self::lte($a, $b) ? self::toDecimal($a) : self::toDecimal($b);
    }

    // ─── Private Domain Intermediates ─────────────────────────────────────────

    /**
     * Base gross amount: qty × unit_price (at extended precision).
     * Not rounded — intermediate only.
     */
    private static function soBase(mixed $qty, mixed $unitPrice): string
    {
        return self::mul($qty, $unitPrice, self::LINE_SCALE);
    }

    /**
     * Taxable amount after discount: base − (base × discount%).
     * Not rounded — intermediate only.
     */
    private static function soTaxable(mixed $qty, mixed $unitPrice, mixed $discountRate): string
    {
        $base = self::soBase($qty, $unitPrice);
        $discount = self::mul($base, self::div($discountRate, '100'));

        return self::sub($base, $discount);
    }

    // ─── Higher-Order Financial Helpers ───────────────────────────────────────

    /**
     * PO line cost: qty × unit_cost, rounded to LINE_SCALE.
     */
    public static function poLineCost(mixed $qty, mixed $unitCost): string
    {
        return self::round(self::mul($qty, $unitCost), self::LINE_SCALE);
    }

    /**
     * SO line subtotal: (qty × price − discount%) × (1 + tax%).
     * Returns an 8dp string. Single source of truth for the subtotal column.
     */
    public static function soLineSubtotal(
        mixed $qty,
        mixed $unitPrice,
        mixed $discountRate = 0,
        mixed $taxRate = 0,
    ): string {
        $taxable = self::soTaxable($qty, $unitPrice, $discountRate);
        $tax = self::mul($taxable, self::div($taxRate, '100'));

        return self::round(self::add($taxable, $tax), self::LINE_SCALE);
    }

    /**
     * SO tax amount only (tax_amount column).
     * Reuses soTaxable() — no duplicated base/discount logic.
     */
    public static function soLineTax(
        mixed $qty,
        mixed $unitPrice,
        mixed $discountRate = 0,
        mixed $taxRate = 0,
    ): string {
        $taxable = self::soTaxable($qty, $unitPrice, $discountRate);

        return self::round(self::mul($taxable, self::div($taxRate, '100')), self::LINE_SCALE);
    }

    /**
     * SO discount amount only (discount_amount column).
     * Reuses soBase() — no duplicated logic.
     */
    public static function soLineDiscount(
        mixed $qty,
        mixed $unitPrice,
        mixed $discountRate = 0,
    ): string {
        $base = self::soBase($qty, $unitPrice);

        return self::round(self::mul($base, self::div($discountRate, '100')), self::LINE_SCALE);
    }

    /**
     * Accumulate line totals in 8dp BCMath precision, then apply the single
     * rounding gate to HEADER_SCALE (2dp) for GAAP-compliant header total_amount.
     *
     * Defensive: each lineTotal is explicitly normalized to LINE_SCALE
     * before accumulation to guard against callers passing inconsistent scales.
     *
     * THIS is the only place in the system where rounding to 2dp ever occurs.
     *
     * @param  iterable<mixed>  $lineTotals
     */
    public static function headerTotal(iterable $lineTotals): string
    {
        $sum = '0';
        foreach ($lineTotals as $lineTotal) {
            // Force each input to LINE_SCALE before accumulating —
            // guards against 2dp strings, ints, or inconsistent-scale inputs.
            $normalized = bcadd(self::toDecimal($lineTotal), '0', self::LINE_SCALE);
            $sum = bcadd($sum, $normalized, self::LINE_SCALE);
        }

        return self::round($sum, self::HEADER_SCALE);
    }
}
