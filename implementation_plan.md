# Implementation Plan — Phase 5: Sales Order Lifecycle (Comprehensive)

This plan outlines the complete development cycle for the Sales Order (SO) domain.
It incorporates a full audit of workflow and logic problems discovered before execution began.

---

## ✅ Completed Milestones

### 1. Stock Engine Hardening (Phase 0.4)
- [x] Database: Added `reserved_qty` to the `inventories` table.
- [x] Service: Implemented `StockService::reserveStock()` and `releaseReservation()` with pessimistic locking.
- [x] Validation: Updated `applyLineToInventory()` to prevent physical stock issuance if it overlaps with reserved quantities.
- [x] Automated Tests: Created `StockReservationTest` confirming no over-selling.

### 2. Sales Order Schema Foundation (Step 2)
- [x] Database: Added `uom_id`, `tax_rate`, `tax_amount`, `discount_rate`, and `discount_amount` to `sales_order_lines`.
- [x] Models: Updated `SalesOrderLine` with new fillable fields and `uom()` relationship.

### 3. Customer Master Data (Step 3)
- [x] Backend: Created `CustomerController`, `CustomerResource`, and API routes.
- [x] Permissions: Seeded and assigned `view-customers` and `manage-customers`.
- [x] UI: Built the **Customer Center** (`CustomerCenter.vue`) with a premium cyan-themed interface.
- [x] Navigation: Integrated Customers into the sidebar.

### 4. Inventory Ledger UOM Persistence (Step 4)
- [x] Schema: Added `base_uom_id` to `transaction_lines`.
- [x] Core Engine: Updated `StockService` to decouple calculative base units from transactional units.
- [x] UI: Added UOM display columns to Inventory Center ledger and movement forms.
- [x] Procurement Audit: Integrated UOM display into PO audit modals.
- [x] Navigation UX: Implemented direct reference-to-movement navigation.

---

## 🔴 Pre-Execution Blockers (Must Fix Before Any Controller Code)

These are critical issues discovered during a codebase audit. Proceeding without
fixing them would result in silent data corruption or broken runtime behavior.

### BLOCKER 1 — Missing `location_id` on `sales_order_lines`
- **Problem**: `StockService::reserveStock(Product, Location, qty)` requires a `Location` object.
  Both `confirm()` (reservation) and `fulfill()` (issue) are completely non-functional without
  knowing *which warehouse* to target per line.
- **Fix**: New migration to add `location_id` (nullable FK → `locations.id`) to `sales_order_lines`.
- **Files**: New migration + update `SalesOrderLine::$fillable` + add `location()` relationship.

### BLOCKER 2 — `reserveStock()` Does Not Convert UOM
- **Problem**: `StockService::reserveStock()` accepts a raw float quantity. If an SO line is
  ordered in BOX (= 12 PCS), calling `reserveStock(..., 1.0)` reserves 1 PCS, not 12.
  The inventory is silently under-reserved, allowing over-selling.
- **Fix**: The `confirm()` action must manually convert `ordered_qty` to the product's base UOM
  (using `UomHelper::getConversionFactor()`) before calling `reserveStock()`. This mirrors the
  pattern in `PurchaseOrderController::receive()`.

### BLOCKER 3 — Fulfillment Atomicity: Wrong Order of Operations
- **Problem**: `applyLineToInventory()` in `StockService` checks:
  `available = QOH - reserved_qty`. If `recordMovement(Issue)` is called BEFORE
  `releaseReservation()`, the stock still appears reserved and a valid fulfillment
  throws `InsufficientStockException`.
- **Fix**: Inside a single `DB::transaction()`, the correct sequence is:
  1. `StockService::releaseReservation()` → decrements `reserved_qty`
  2. `StockService::recordMovement(Issue)` → issues against now-unreserved stock

### BLOCKER 4 — `SalesOrderStatus` Names Alignment
- **Problem**: Seeder used `Quotation`, `Confirmed`, `Cancelled`, `Closed` (Title Case). PO uses `draft`, `open`, `sent`.
- **Fix**: Normalize SO statuses to lowercase snake_case to match PO standard. Use `quotation` (draft equivalent), `quotation_sent`, `confirmed` (open equivalent), `processing`, `shipped`, `cancelled`, `closed`. Remove orphan `Draft`.
- **Status Lifecycle**: `quotation` → `quotation_sent` → `confirmed` → `processing` → `shipped` → `closed` | `cancelled`.

### BLOCKER 5 — Missing Order Standard Fields
- **Problem**: `PurchaseOrder` has `carrier`, `tracking_number`, `sent_at`, `shipped_at`, `approved_by`. `SalesOrder` is missing these or they are incomplete.
- **Fix**: Add `carrier`, `tracking_number`, `sent_at`, `shipped_at`, `delivered_at`, and `approved_by` (FK) to `sales_orders` table. Add `returned_qty` to `sales_order_lines` to support future Returns (Phase 5.4).
- **Boot Logic**: Implement `booted()` in `SalesOrder` to auto-assign `created_by` just like PO.
- **Casts**: Ensure decimal casts (4/6 places) are applied for all monetary and quantity fields.

---

## 🚧 Current Phase: Step 5 — Sales Order Backend API

### 5.1 Pre-Conditions (Schema & Seeder Fixes)
- [ ] Migration: Add `location_id` (nullable FK → `locations`) to `sales_order_lines`.
- [ ] Migration: Add `carrier`, `tracking_number`, `sent_at`, `shipped_at`, `delivered_at`, `approved_by` to `sales_orders`.
- [ ] Migration: Add `returned_qty` to `sales_order_lines`.
- [ ] Update `SalesOrder` model:
  - Add new fields to `$fillable`.
  - Add `sent_at`, `shipped_at`, `delivered_at` to `$casts`.
  - Add `booted()` method to auto-assign `created_by`.
- [ ] Update `SalesOrderLine` model:
  - Add `location_id`, `returned_qty` to `$fillable`.
  - Add `remaining_qty` accessor and formatted UOM helpers to match PO standard.
- [ ] Update `SalesOrderStatus` seeder: Normalize to lowercase snake_case (`quotation`, `quotation_sent`, `confirmed`, `processing`, `shipped`, `cancelled`, `closed`).

### 5.2 Core API Layer
- [ ] Create `app/Http/Controllers/Api/Sales/SalesOrderController.php`:
  - `index()` — paginated list with customer + status; supports `?search=` + `?status=` filters.
  - `store()` — create SO in `quotation` status; compute `total_amount` (qty × price × tax/discount).
  - `show()` — load with lines, customer, status, linked transactions.
  - `update()` — only when `is_editable`; replace lines atomically; recalculate `total_amount`.
  - `destroy()` — only when `is_editable`; soft-delete.
  - `approve()` — Transition `quotation` → `confirmed`. (Mirrors PO `approve`).
  - `send()` — Transition `quotation` → `quotation_sent`. Sets `sent_at`. (Mirrors PO `send`).
  - `ship()` — Records `carrier` and `tracking_number`. Transition `confirmed` → `shipped`. Sets `shipped_at`. (Mirrors PO `ship`).
  - `fulfill()` — `POST /api/sales-orders/{id}/fulfill`:
    - Mirrored after PO `receive` action.
    - Creates `ISS` (Issue) transaction via `StockService`.
    - Handles partial fulfillment with `lines: [{ so_line_id, fulfill_qty }]`.
    - Transition to `processing` or `closed` (if fully shipped/returned).
- [ ] `SalesOrderResource`: Full implementation mirroring `PurchaseOrderResource` with `fulfillments` and `returns` collections.
  - `cancel()` — `PATCH /api/sales-orders/{id}/cancel`:
    - If status is `confirmed` or `processing`: release all reservations for all lines.
    - Transition to `cancelled`.
- [ ] Create `app/Http/Requests/Sales/SalesOrderStoreRequest.php`:
  ```
  customer_id:             required|exists:customers,id
  requested_delivery_date: nullable|date
  currency:                nullable|string
  notes:                   nullable|string
  lines:                   required|array|min:1
  lines.*.product_id:      required|exists:products,id
  lines.*.location_id:     required|exists:locations,id
  lines.*.uom_id:          nullable|exists:units_of_measure,id
  lines.*.ordered_qty:     required|numeric|min:0.01
  lines.*.unit_price:      required|numeric|min:0
  lines.*.tax_rate:        nullable|numeric|min:0|max:100
  lines.*.discount_rate:   nullable|numeric|min:0|max:100
  ```
- [ ] Fully implement `app/Http/Resources/Sales/SalesOrderResource.php`:
  - Header, embedded lines, embedded fulfillment transactions (filtered by type code `ISS`).
- [ ] Register routes in `routes/api.php` under the existing sanctum auth group.

---

## 📅 Remaining Steps

### Step 6 — Sales Order Frontend

#### 6.1 Infrastructure
- [ ] Add web routes to `routes/web.php` for `/sales-orders`, `/sales-orders/create`, `/sales-orders/{id}`.
- [ ] Add **"Sales"** nav item to `AppLayout.vue` (`pi pi-receipt`, teal accent).

#### 6.2 `SalesOrders/Index.vue`
- [ ] Premium dark-themed index with teal accent.
- [ ] DataTable: SO Number, Customer, Order Date, Status (badge), Total Value, action chevron.
- [ ] Status badge color map (lowercase names):
  - `quotation` → warning, `confirmed` → info, `processing` → help,
    `shipped` → success, `cancelled` → danger, `closed` → secondary
- [ ] "Draft SO" button (requires `manage-sales-orders`).
- [ ] Row-click → `/sales-orders/{id}`.

#### 6.3 `SalesOrders/Form.vue`
- [ ] Multi-section form:
  - **Header**: Customer selector (searchable), delivery date, currency, notes.
  - **Lines table**: Product selector, Location selector (shows available QOH inline),
    UOM selector, Ordered Qty, Unit Price (auto-filled from `product.selling_price`),
    Tax Rate %, Discount Rate %, computed line total.
  - **Financial summary**: Subtotal, Discount, Tax, Grand Total.
- [ ] QOH availability indicator per line (amber warning if stock < qty, red if 0).
- [ ] Add/Remove line buttons.
- [ ] "Save as Quotation" → POST `/api/sales-orders` → redirect to Show page.
- [ ] "Discard" → back to Index.

#### 6.4 `SalesOrders/Show.vue`
- [ ] Layout mirrors PO `Show.vue`: sidebar for metadata and fulfillment/return history.
- [ ] Action buttons: "Approve Order", "Send Quote", "Mark Shipped", "Ship Items (Fulfill)", "Return Items (RMA)".
- [ ] Main table: REQ QTY, SHIP QTY, RET QTY, REM QTY.
- [ ] Partial fulfillment dialog mirrored after PO's GRN dialog.
- [ ] Issue transactions panel (COGS tracking).
- [ ] Financial summary card.

### Step 7 — UI Polish & Intelligence
- [ ] "Low Stock" / "Out of Stock" warning badges in the Form line items.
- [ ] Customer credit limit warning banner if SO total exceeds `customer.credit_limit`.
- [ ] Final visual audit of the full Sales flow.

---

## Known Gaps (Out of Phase 5 Scope)

| Gap | Phase |
|-----|-------|
| Backorder splitting (partial ship → auto-create back PO suggestion) | 5.6 |
| Sales Returns / RMA engine | 5.4 |
| Invoice generation + Customer Payments | 5.5 |
| Automated tax resolution from price lists | 7 |

---

## Verification Plan

### Backend API Tests (via HTTP client / Tinker)
1. `POST /api/sales-orders` → assert 201, status = `quotation`, `total_amount` correct.
2. `PATCH /api/sales-orders/{id}/confirm`:
   - Assert status = `confirmed`.
   - Query `inventories.reserved_qty` → assert increased by converted base qty.
   - Assert 422 for insufficient stock.
3. `POST /api/sales-orders/{id}/fulfill`:
   - Assert status = `shipped` (full) or `processing` (partial).
   - Assert `inventories.quantity_on_hand` decreased.
   - Assert `inventories.reserved_qty` decreased (released before issue).
   - Assert a `transaction` record created with type = `ISS`, linked `sales_order_id`.
   - Assert `sales_order_lines.shipped_qty` updated.
4. `PATCH /api/sales-orders/{id}/cancel` (from `confirmed`):
   - Assert status = `cancelled`.
   - Assert `inventories.reserved_qty` returned to pre-confirm value.

### Manual Browser Verification
1. Navigate `/sales-orders` → index loads, "Draft SO" button visible.
2. Create a quotation → redirect to Show page.
3. Confirm → check Inventory Center shows increased `reserved_qty`.
4. Fulfill (partial) → status = `processing`, transaction record visible.
5. Fulfill (remaining) → status = `shipped`.
6. Cancel from `confirmed` → reserved_qty released.
