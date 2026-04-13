# Plan: Global UOM & Category Parity — Full System Audit

Achieving absolute mathematical and operational integrity across all inventory categories by eradicating hardcoded unit assumptions system-wide.

---

## Root Cause Summary

> [!IMPORTANT]
> A single method — `UomHelper::getMultiplierToSmallest()` — is the engine behind **every** quantity display and calculation in the system. It currently has a **split personality**: Mass/Volume categories use the UOM's own `conversion_factor_to_base`, but **Count/Packaging** categories ignore it entirely and require a product-specific `UomConversion` rule or they throw a `"Missing conversion rule"` error.
>
> **Fixing this one method (Priority 0) will cascade correct behavior to every module listed below.**

---

## Blast Radius Map

### 🔧 The Engine (Core — 1 file)

| File | Role | Impact |
|---|---|---|
| [`UomHelper.php`](file:///c:/xampp/htdocs/inventory-system/app/Helpers/UomHelper.php) | **Root multiplier logic** — all scaling, formatting, breakdown | **Fix here first.** All modules below inherit this fix automatically. |

---

### 📦 Workflow 1: Product Creation & Catalog

| File | Type | Gap |
|---|---|---|
| [`Catalog.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Catalog.vue) | Frontend | Hardcoded `|| 'pcs'` fallback on reorder_point & stock fields (lines 886, 1018). `Math.floor` used for stock display. |
| [`UomCenter.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/UomCenter.vue) | Frontend | Info tooltip incorrectly says "packaging conversions are product-specific only" — partially untrue after Priority 0 fix. |
| [`Product.php`](file:///c:/xampp/htdocs/inventory-system/app/Models/Product.php) | Backend Model | Calls `getMultiplierToSmallest($throw=false)` — will silently return `'1'` for Count UOMs without a rule. After fix: will correctly use UOM's own multiplier. ✅ Auto-fixed |
| [`ProductService.php`](file:///c:/xampp/htdocs/inventory-system/app/Services/Inventory/ProductService.php) | Backend Service | Uses `getSmallestUnitId` for initial stock — no `'pcs'` hardcode, but relies on UomHelper. ✅ Auto-fixed |

---

### 🛒 Workflow 2: Purchase Orders & Quotations

| File | Type | Gap |
|---|---|---|
| [`PurchaseOrderResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Procurement/PurchaseOrderResource.php) | API Resource | `base_uom_abbreviation` now correctly uses `is_base=1` lookup. ✅ **COMPLETED** |
| [`PurchaseOrderLineResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Procurement/PurchaseOrderLineResource.php) | API Resource | `uom_abbreviation` fallback is `'pcs'` (line 28). Missing `base_uom_abbreviation` field entirely. ❌ **NEEDS FIX** |
| [`PurchaseOrderLine.php`](file:///c:/xampp/htdocs/inventory-system/app/Models/PurchaseOrderLine.php) | Backend Model | Calls `getMultiplierToSmallest($throw=true)` — **will throw** for Count UOMs without a rule. ✅ Auto-fixed after Priority 0 |
| [`PurchaseOrderController.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Controllers/Api/Procurement/PurchaseOrderController.php) | Controller | PO returns call `getMultiplierToSmallest($throw=true)` — same blast. ✅ Auto-fixed |
| [`PurchaseOrders/Show.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/PurchaseOrders/Show.vue) | Frontend | Hardcoded `|| 'PCS'` fallback on unit labels (lines 350, 352, 435). `Math.floor` used for print preview quantities. ❌ **NEEDS FIX** |

---

### 🏭 Workflow 3: Goods Receipt (GRN)

| File | Type | Gap |
|---|---|---|
| [`Movements/ReceiptForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/ReceiptForm.vue) | Frontend | Uses `Math.floor` for available stock display. ❌ **NEEDS FIX** |
| [`StockService.php`](file:///c:/xampp/htdocs/inventory-system/app/Services/Inventory/StockService.php) | Backend Service | Calls `getMultiplierToSmallest($throw=false)`. Calls `getConversionFactor`. ✅ Auto-fixed |
| [`TransactionLine.php`](file:///c:/xampp/htdocs/inventory-system/app/Models/TransactionLine.php) | Backend Model | 5 separate calls to `getMultiplierToSmallest($throw=false)` for formatting. ✅ Auto-fixed |
| [`TransactionResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/TransactionResource.php) | API Resource | `uom_abbreviation` fallback is `'PCS'` (line 71). Missing `base_uom_abbreviation`. ❌ **NEEDS FIX** |
| [`TransactionLineResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/TransactionLineResource.php) | API Resource | `uom_abbreviation` fallback is `'PCS'` (lines 25, 29). Missing `base_uom_abbreviation`. ❌ **NEEDS FIX** |
| [`Movements/Show.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/Show.vue) | Frontend | Hardcoded `|| 'PCS'` fallback on quantity display (line 193). ❌ **NEEDS FIX** |

---

### 💰 Workflow 4: Sales Orders

| File | Type | Gap |
|---|---|---|
| [`SalesOrders/Form.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/SalesOrders/Form.vue) | Frontend | Two hardcoded `|| 'pcs'` fallbacks on stock popover (lines 751, 772). `Math.floor` on stock display. ❌ **NEEDS FIX** |
| [`SalesOrderLine.php`](file:///c:/xampp/htdocs/inventory-system/app/Models/SalesOrderLine.php) | Backend Model | 9 separate `UomHelper::format` calls — all auto-correct after Priority 0 fix. ✅ Auto-fixed |
| [`SalesOrderLineResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Sales/SalesOrderLineResource.php) | API Resource | Has `uom` object but **no `base_uom_abbreviation` field**. ❌ **NEEDS FIX** |
| [`SalesOrders/Show.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/SalesOrders/Show.vue) | Frontend | Uses `Math.floor` for shipment quantity summaries. ❌ **NEEDS FIX** |

---

### 🔄 Workflow 5: Returns (PO & SO)

| File | Type | Gap |
|---|---|---|
| [`PurchaseOrderController.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Controllers/Api/Procurement/PurchaseOrderController.php) | Controller | PO return uses `getMultiplierToSmallest($throw=true)`. ✅ Auto-fixed after Priority 0 |
| [`SalesOrderReturnController.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Controllers/Api/Sales/SalesOrderReturnController.php) | Controller | Uses UomHelper indirectly via StockService. ✅ Auto-fixed |

---

### 🏗️ Workflow 6: Warehouse Movements (Transfer / Issue / Adjust)

| File | Type | Gap |
|---|---|---|
| [`Movements/TransferForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/TransferForm.vue) | Frontend | Error toast hardcoded `|| 'pcs'` label (line 564). `Math.floor` for stock display. ❌ **NEEDS FIX** |
| [`Movements/IssueForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/IssueForm.vue) | Frontend | Error toast hardcoded `|| 'pcs'` label (line 512). `Math.floor` for stock display. ❌ **NEEDS FIX** |
| [`Movements/AdjustmentForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Movements/AdjustmentForm.vue) | Frontend | Error toast hardcoded `|| 'pcs'` label (line 458). No decimal support for mass entry. ❌ **NEEDS FIX** |
| [`Inventory.php`](file:///c:/xampp/htdocs/inventory-system/app/Models/Inventory.php) | Backend Model | 3 calls to `getMultiplierToSmallest($throw=false)`. ✅ Auto-fixed |
| [`InventoryResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/InventoryResource.php) | API Resource | Calls `getMultiplierToSmallest($throw=false)`. ✅ Auto-fixed |
| [`InventoryQueryController.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Controllers/Api/Inventory/InventoryQueryController.php) | Controller | 2 calls to `getMultiplierToSmallest($throw=false)`. ✅ Auto-fixed |

---

### 💳 Workflow 7: Finance / Billing

| File | Type | Gap |
|---|---|---|
| [`Finance/BillForm.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/Finance/BillForm.vue) | Frontend | Uses `base_uom_abbreviation` correctly. Fallback is `|| 'pcs'` (safe after backend fix). ✅ **DONE** |
| [`Finance/BillController.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Controllers/Api/Finance/BillController.php) | Controller | 2 calls to `getMultiplierToSmallest($throw=true)`. ✅ Auto-fixed after Priority 0 |

---

### 📊 Workflow 8: Inventory Dashboard

| File | Type | Gap |
|---|---|---|
| [`InventoryCenter.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/InventoryCenter.vue) | Frontend | UOM dropdown tooltip uses `|| 'pcs'` fallback (line 497). `Math.floor` for scaled quantity display. ❌ **NEEDS FIX** |
| [`VendorCenter.vue`](file:///c:/xampp/htdocs/inventory-system/resources/js/Pages/VendorCenter.vue) | Frontend | Two hardcoded `|| 'PCS'/'pcs'` fallbacks on product stock display (lines 442, 519). ❌ **NEEDS FIX** |
| [`ProductResource.php`](file:///c:/xampp/htdocs/inventory-system/app/Http/Resources/Inventory/ProductResource.php) | API Resource | Calls `getMultiplierToSmallest` — auto-fixed. But no `base_uom_abbreviation` exported. ❌ **NEEDS FIX** |
| [`InventoryCostLayer.php`](file:///c:/xampp/htdocs/inventory-system/app/Models/InventoryCostLayer.php) | Backend Model | Uses `UomHelper::format` for cost layer quantities. ✅ Auto-fixed |

---

## Execution Order

> [!IMPORTANT]
> Do **Priority 0 first**. It automatically fixes ~60% of the system with a single file change due to the cascade effect on all callers that pass `$throw=false`.

| Priority | File | Effort |
|---|---|---|
| **0** | `UomHelper.php` — add `conversion_factor_to_base` fallback for Count | Small |
| **1** | API Resources — add `base_uom_abbreviation` to `PurchaseOrderLineResource`, `TransactionResource`, `TransactionLineResource`, `SalesOrderLineResource`, `ProductResource` | Medium |
| **2** | Frontend — replace `|| 'pcs'` fallbacks in `SalesOrders/Form.vue`, `PurchaseOrders/Show.vue`, `Movements/Show.vue`, `TransferForm.vue`, `IssueForm.vue`, `AdjustmentForm.vue`, `InventoryCenter.vue`, `VendorCenter.vue` | Medium |
| **3** | Frontend — fix `Math.floor` truncation in `ReceiptForm.vue`, `SalesOrders/Show.vue`, `Catalog.vue` | Small |
| **4** | UomCenter.vue — update tooltip text to reflect new global multiplier capability | Trivial |

---

## Verification Plan

### Automated Tests
- `php artisan test` — full regression suite (46 assertions passing currently)
- New test: `UomHelper` returns correct multiplier for a Count UOM with `conversion_factor_to_base` set but **no** `UomConversion` rule

### Manual Verification (One Product Per Category)
1. **Count + Global Multiplier**: Create "Crate" UOM with `conversion_factor_to_base = 24`. Use on a PO — verify `1 crate [24 pcs]` with no error, no rule needed.
2. **Count + Product Override**: Same product, add rule "1 Crate = 30 pcs". Verify label shows `[30 pcs]` (override wins).
3. **Mass**: Adjust 0.5 kg product. Verify `0.5 kg [500 g]` everywhere — no `Math.floor` truncation.
4. **Volume**: Create SO for 2.5 L. Verify labels show `2.5 L [2500 ml]` in SO form and confirmation.
5. **Vendor Center**: View a kg-based product. Verify stock shows `12.5 kg`, NOT `12 pcs`.
6. **Returns**: Return 1 case from a PO. Verify no "Missing conversion rule" error.
