/**
 * useUom — UOM display utilities
 *
 * SYSTEM CONTRACT: These functions are display-only.
 * The output of formatQty() must NEVER re-enter a calculation path.
 * Use raw quantity fields (e.g. line.quantity) for all math and form submissions.
 * See: implementation_plan.md — Contract 1: The Two-Track Architecture
 */

/**
 * Format a quantity for display based on the UOM category.
 *
 * - Count:       integer (Math.floor) — fractional pieces are not meaningful
 * - Mass/Volume: decimal rounded to uom.decimals precision
 *
 * @param {number|string} qty   - Raw atomic quantity
 * @param {object} uom          - UOM object with `category` and `decimals` fields
 * @returns {number}            - Display-safe number (NEVER feed this back into math)
 */
export function formatQty(qty, uom) {
    const n = parseFloat(qty)
    if (isNaN(n)) return 0
    if (!uom) return n
    if (uom.category === 'count') return Math.floor(n)
    return Number(n.toFixed(uom.decimals ?? 8))
}

/**
 * Safely get the base UOM abbreviation from a line object.
 * Returns '???' if the API failed its contract — intentionally ugly
 * so it is immediately visible in production rather than silently
 * showing 'pcs' for a Mass/Volume product.
 *
 * @param {object} line  - Line object from API (must have base_uom)
 * @returns {string}
 */
export function getBaseUomAbbr(line) {
    if (!line?.base_uom?.abbreviation) {
        console.error('[UOM CONTRACT VIOLATION] base_uom missing on line:', line)
        return '???'
    }
    return line.base_uom.abbreviation
}

/**
 * Safely get the ordering UOM abbreviation from a line object.
 *
 * @param {object} line  - Line object from API
 * @returns {string|null}
 */
export function getUomAbbr(line) {
    return line?.uom?.abbreviation ?? line?.uom_abbreviation ?? null
}
