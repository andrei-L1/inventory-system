# Plan: Global UOM & Category Parity

Achieving absolute mathematical and operational integrity across all inventory categories (Count, Mass, Volume, Length) by eradicating hardcoded unit assumptions and standardizing high-precision display patterns.

---

## ⚖️ System Contracts (Non-Negotiable Rules)

### Contract 1: The Two-Track Architecture
> **"No formatted value may ever re-enter a calculation path."**

A formatted value is **terminal**. The 324-pieces billing bug was caused by exactly this violation.

```
DATABASE (raw)           DISPLAY (formatted)
──────────────           ───────────────────
18.00000000  ──────────► "1 case [18 pcs]"   ← ends here, NEVER goes back
     ↑
     │  All math stays here (BCMath strings)
```

**API-level enforcement (required, not optional):**
- Raw fields: `quantity`, `unit_cost`, `conversion_factor` — numeric strings, BCMath-safe
- Formatted fields: `formatted_quantity`, `formatted_cost` — display strings, never sent to backend
- These must **never share the same field name**. A field cannot be both.
- Backend API response interceptor (dev): assert no `formatted_*` field contains a value that arrives on a subsequent POST/PUT request body.

```js
// ✅ CORRECT
v-model="line.quantity"               // raw → form, calculations only
{{ formatQty(line.quantity, uom) }}   // formatted → <template> display only

// ❌ BUG — contract violation
line.quantity = parseFloat(line.formatted_qty) * factor
```

### Contract 2: Priority Chain (Product Rule Always Wins)
```
1st  →  Product-specific rule   (UomConversion where product_id = X)
2nd  →  Global rule             (UomConversion where product_id IS NULL)
3rd  →  UOM's own multiplier    (conversion_factor_to_base — see safety check)
4th  →  Return '1' or THROW     (depending on $throw flag)
```

### Contract 3: Base Unit Resolution is Absolute
Every UOM category must have exactly one unit marked `is_base = 1`. **The API must throw 500 if `base_uom` cannot be resolved.** No silent null. No graceful omission. No fallback abbreviation.

This is especially critical for **financial documents** (invoices, bills, POs, GRNs). A 500 on page load is a recoverable incident. A printed document with `'???'` units sent to a vendor is a **trust-breaking production failure** that cannot be undone.

```php
// ABSOLUTE CONTRACT — no exceptions
private function resolveBaseUom(): UnitOfMeasure
{
    $category = $this->product?->uom?->category
        ?? throw new \RuntimeException(
            "Product #{$this->product_id} has no resolvable UOM. Fix the record."
        );

    return UnitOfMeasure::where('category', $category)
        ->where('is_base', 1)
        ->firstOrFail(); // throws ModelNotFoundException — never returns null
}
```

### Contract 4: No Circular Conversions
`Box → Pack → Box` causes infinite recursion. Validate at `UomConversion` save time using BFS/DFS traversal.

### Contract 5: No Hardcoded Unit Strings in UI
`'pcs'`, `'PCS'` must never appear as fallback labels. Use `base_uom.abbreviation`. If the API failed its contract, show `'???'` as an alarm — but this state must be **unreachable in a healthy system**.

### Contract 6: Zero and Invalid Multipliers are Forbidden
A multiplier of `0`, `0.000`, or any non-positive value is mathematically invalid and will cause division-by-zero or silent inventory corruption.

**Two-layer protection — both required:**
- **Layer 1 (Upstream — UomCenter.vue)**: Form validation must reject `<= 0` before saving. Prevent bad data at the source.
- **Layer 2 (Runtime — UomHelper.php)**: BCMath triple-check before using any stored multiplier:

```php
// ✅ REQUIRED — all three conditions
if (
    $uom->conversion_factor_to_base !== null &&
    is_numeric($uom->conversion_factor_to_base) &&
    FinancialMath::gt((string) $uom->conversion_factor_to_base, '0')  // "0.000" fails here
) {
    return (string) $uom->conversion_factor_to_base;
}
// Falls through to THROW or return '1'
```

Note: `FinancialMath::gt('0.000', '0')` returns `false` — so stored `"0.000"` is correctly rejected at runtime even if Layer 1 fails.

---

## 🔴 Critical Risks (Must Fix Before Ship)

### Risk 1: `UomHelper` Fallback Condition — Wrong Safety Check

**Problem**: `!== null` alone is insufficient. A stored value of `"0"` passes `!== null` but causes division-by-zero in `FinancialMath::div()`, silently corrupting stock math.

**Required fix** — triple validation using BCMath, no PHP float casts:
```php
// ❌ UNSAFE — was in the plan
if ($uom->conversion_factor_to_base !== null) { ... }

// ✅ SAFE — required
if (
    $uom->conversion_factor_to_base !== null &&
    is_numeric($uom->conversion_factor_to_base) &&
    FinancialMath::gt((string) $uom->conversion_factor_to_base, '0')
) {
    return (string) $uom->conversion_factor_to_base;
}
```

### Risk 2: Frontend Math vs Backend Mismatch

**Problem**: `console.error` is invisible in production. If an endpoint accidentally sends a formatted-looking value (e.g., `"0.5 kg"`) in a field the frontend treats as numeric, calculations will corrupt stock silently — the same class of bug as the 324-pieces error.

**Required mitigations before ship:**
1. **Strict field naming**: All API resources must ensure `formatted_*` fields are strings and raw fields are numeric strings. Never overlap.
2. **Vue computed audit**: Before shipping each module, audit that no `computed` property uses a `formatted_*` field as input to multiplication, division, or form submission.
3. **Dev interceptor** (axios): In dev mode, log a warning if any `formatted_*` key appears in a POST/PUT request body:
```js
// In axios dev interceptor
if (import.meta.env.DEV) {
    Object.keys(data).forEach(k => {
        if (k.startsWith('formatted_'))
            console.error(`[CONTRACT VIOLATION] formatted field "${k}" in request body`)
    })
}
```

### Risk 3: Missing API Contract Enforcement (Highest Risk)

**Problem**: Backend currently adds `base_uom` only "when present." If a product has a NULL `uom` relation (one corrupted record), the field is silently omitted. The frontend crashes or silently falls back to a wrong label.

**Required fix — hard backend validation:**

Add a shared private resolver to all affected Resources. It must **throw, not return null**:
```php
private function resolveBaseUom(): UnitOfMeasure
{
    $category = $this->product?->uom?->category
        ?? throw new \RuntimeException(
            "Product #{$this->product_id} has no resolvable UOM category. " .
            "Fix the product record before generating API responses."
        );

    return UnitOfMeasure::where('category', $category)
        ->where('is_base', 1)
        ->firstOrFail(); // throws ModelNotFoundException if none found
}
```

This guarantees the API **never ships a response without `base_uom`**. In production, a 500 on one record is infinitely better than a silent wrong label on every page.

---

## Proposed Changes

---

### 🔴 Priority 0A — `UomCenter.vue`: Expose Multiplier for Count Units

Currently hidden for `Count / Packaging`. Admin cannot define "1 DZN = 12" globally.

**Fix**: Show multiplier for all categories. Update tooltip to:
> *"Global default (e.g., 1 Box = 12 pcs). Product rules always override this."*

**Add input validation**: Do not allow saving `0` or negative values. Enforce `> 0` at the form level before the API is ever called.

#### [MODIFY] [UomCenter.vue](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/UomCenter.vue)

---

### 🔴 Priority 0B — `UomHelper.php`: Hardened Count Fallback

#### [MODIFY] [UomHelper.php](file:///c:/xampp/htdocs/inventory-system/app/Helpers/UomHelper.php)

Hardened triple-validation condition (see Risk 1 above). Full updated method chain:
```
1. Product-specific UomConversion rule → return factor
2. Global UomConversion rule (null product_id) → return factor
3. UOM's conversion_factor_to_base (numeric && > '0') → return it
4. $throw = false → return '1'
4. $throw = true  → ValidationException
```

---

### 🧹 Priority 0.5 — Orphaned Conversion Rule Cleanup

LEFT JOIN purge of `UomConversion` records where `product_id` references a hard-deleted product. Handle soft deletes (check `deleted_at IS NULL` on products table).

Run before any production data sync.

#### [NEW] Migration file

---

### 1. Priority 1 — API: Hard-Enforced `base_uom` Object

The `base_uom` field must be present on **every** line-level resource. Use the shared `resolveBaseUom()` method (throws on failure). Shape:

```php
'base_uom' => [
    'id'           => $baseUom->id,
    'abbreviation' => $baseUom->abbreviation,
    'name'         => $baseUom->name,
    'category'     => $baseUom->category,
    'decimals'     => $baseUom->decimals,
],
```

Remove all `'pcs'` / `'PCS'` fallback strings.

| File | Status |
|---|---|
| [`PurchaseOrderResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Procurement/PurchaseOrderResource.php) | Upgrade string → full object |
| [`PurchaseOrderLineResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Procurement/PurchaseOrderLineResource.php) | Add full object |
| [`TransactionResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/TransactionResource.php) | Add full object |
| [`TransactionLineResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/TransactionLineResource.php) | Add full object |
| [`SalesOrderLineResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Sales/SalesOrderLineResource.php) | Add full object |
| [`ProductResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/ProductResource.php) | Add full object |

---

### 2. Priority 2 — Frontend: Remove `|| 'pcs'` + Add Production-Safe Guards

Replace all `|| 'pcs'` / `|| 'PCS'` with `line.base_uom?.abbreviation`.

Dev guard **and** production fallback — log loudly, but do not crash the page:
```js
function getBaseUomAbbr(line) {
    if (!line.base_uom?.abbreviation) {
        console.error('[UOM CONTRACT] base_uom missing:', line)
        return '???' // visible wrong value is better than silent 'pcs'
    }
    return line.base_uom.abbreviation
}
```

> `'???'` is intentionally ugly — if it appears in production, it signals a data problem immediately rather than hiding it with a plausible-looking `'pcs'`.

| File | Lines |
|---|---|
| [`SalesOrders/Form.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/SalesOrders/Form.vue) | 751, 772 |
| [`PurchaseOrders/Show.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/PurchaseOrders/Show.vue) | 350, 352, 435 |
| [`Movements/Show.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/Show.vue) | 193 |
| [`Movements/TransferForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/TransferForm.vue) | 564 |
| [`Movements/IssueForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/IssueForm.vue) | 512 |
| [`Movements/AdjustmentForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/AdjustmentForm.vue) | 458 |
| [`InventoryCenter.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/InventoryCenter.vue) | 497 |
| [`VendorCenter.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/VendorCenter.vue) | 442, 519 |
| [`Catalog.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Catalog.vue) | 886, 1018 |

---

### 3. Priority 3 — Frontend: Category-Aware `formatQty()`

Shared composable `useUom.js`:
```js
export function formatQty(qty, uom) {
    if (!uom) return qty
    if (uom.category === 'count') return Math.floor(qty)
    return Number(parseFloat(qty).toFixed(uom.decimals ?? 8))
}
```

> [!WARNING]
> **Display only.** Never use `formatQty()` output in a calculation or form submission. Violating this is a Contract 1 breach. (See the 324-pieces billing bug.)

Scattered `Math.floor` across 9 files (see previous section) — each causes silent inventory drift for mass/volume products.

---

## ⚠️ Medium Risks (Ship After Critical Fixes)

| Risk | Impact | Action |
|---|---|---|
| Orphaned rule cleanup | Data noise, slow lookups | Run migration before production sync |
| `console.error` only in dev | Invisible in production | Use `'???'` fallback (visible corruption signal) |
| `Math.floor` scattered | Inventory drift on mass/volume | Priority 3 — category-aware `formatQty` |
| Circular conversion possible | Infinite recursion on save | Add BFS check to `UomConversion` model save hook |

---

## Execution Order

| Priority | Target | Blocker? |
|---|---|---|
| **0A** | `UomCenter.vue` — expose multiplier, add `> 0` validation | ✅ Ship blocker |
| **0B** | `UomHelper.php` — triple-validated fallback | ✅ Ship blocker |
| **0.5** | Orphaned rule cleanup migration | Before prod sync |
| **1** | 6 API Resources — hard-enforced `base_uom` object | ✅ Ship blocker |
| **2** | 9 Frontend files — remove `\|\| 'pcs'`, production-safe guards | ✅ Ship blocker |
| **3** | `useUom.js` composable + 9 files `Math.floor` fix | After blockers |

---

## Verification Plan

| Test | Expected |
|---|---|
| Count UOM (DZN = 12), no product rule | `1 dzn [12 pcs]`, no error |
| Count UOM with `conversion_factor_to_base = 0` | Skips fallback, throws/returns `'1'` — no divide-by-zero |
| Product override = 15, UOM default = 12 | `[15 pcs]` wins |
| Global rule = 20, UOM default = 12 | `[20 pcs]` wins (step 2 before step 3) |
| Product with NULL `uom` relation | API returns 500 / `ModelNotFoundException`, not silent null |
| `formatted_*` field in POST body | Dev interceptor fires `console.error` |
| Mass: adjust 0.5 kg | `0.5 kg [500 g]`, no `Math.floor` truncation |
| Any page shows `???` label | Data integrity alarm — investigate immediately |
| Billing regression | `1 case = 18 pcs`, not 324 |
