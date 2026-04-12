# Nexus Inventory System — Full Lifecycle Development Plan & Roadmap
> Last audited: 2026-04-12 (Enterprise Precision & Frontend-Backend Handshake Alignment). All status markers reflect actual codebase state.

---

## Technical Mission

---

## Technical Mission
Build a production-ready ERP-grade inventory system where every unit of stock is traceable
from the moment it is ordered from a vendor to the moment it is sold to a customer — with
mathematically guaranteed accuracy via transactional integrity, real-time costing
(FIFO / LIFO / Weighted Average), and a complete audit trail at every step.

---

## System Lifecycle Overview

The full workflow of this system follows this chain:

```
[Setup] → [Vendor & Product Setup] → [Procurement / Inbound] → [Warehouse Operations]
       → [Sales / Outbound] → [Logistics] → [Financial Close] → [Reporting & Audit]
```

Each phase below corresponds to one stage of that chain.

---

## ✅ Phase 0 — Foundation: The Stock Engine
> Status: COMPLETE
> The mathematical core. Built and tested before any UI.

### 0.1 Stock Movement Engine (`StockService`)
- [x] `recordMovement(array $data): Transaction`
  - Creates a `Transaction` header + `TransactionLine` rows in a single DB transaction
  - Applies `lockForUpdate()` (pessimistic locking) on `Inventory` rows to prevent race conditions
  - **Draft/Posted enforcement** — inventory is ONLY updated when `status = posted`; drafts save header+lines only
  - Dispatches to `applyLineToInventory()` for each posted line
- [x] `postTransaction(Transaction $t): Transaction`
  - Promotes a draft → posted and applies all lines to inventory atomically
  - Guards against re-posting or posting a cancelled transaction
- [x] `recordTransfer(array $data): array`
  - Atomic double-sided movement: Issue from origin + Receipt at destination
  - Both legs in a single `DB::transaction()` — either both post or neither does
  - Creates a `Transfer` pivot record linking both transaction legs by FK (no more orphan transactions)
- [x] `consumeLayers(Inventory $inv, float $qty): float`
  - FIFO: consumes oldest layers first (`receipt_date ASC`)
  - LIFO: consumes newest layers first (`receipt_date DESC`)
  - Marks layer `is_exhausted = true` when `remaining_qty ≤ 0`
  - Throws `InsufficientStockException` if qty demanded exceeds available layers
  - **Returns the weighted-average unit cost of layers consumed** (COGS for the issue line)
- [x] `updateLocationAverageCost(Inventory $inv, float $newQty, float $newCost)`
  - Per-location WAC: `(Existing_Value + New_Value) / (Existing_Qty + New_Qty)`
  - Updates `inventories.average_cost` only (in-memory before save)
- [x] `updateProductGlobalAverageCost(int $productId)`
  - **Correct global WAC formula**: `SUM(location_QOH × location_avg_cost) / SUM(location_QOH)` across ALL locations
  - Called AFTER `$inventory->save()` so the query sees the latest state
  - Eliminates the single-location contamination bug
- [x] `applyLineToInventory(TransactionLine, array)` — extracted private method reused by both `recordMovement` and `postTransaction`
- [x] `TransactionValidator` — pre-movement guard (type, location, quantity sanity checks)
- [x] `InsufficientStockException` — typed exception for over-issue scenarios
- [x] **Reversal Audit Link** — `reverses_transaction_id` foreign key added to track the origin of a voided transaction. ✅ NEW
- [x] **Strict UOM Safety** — `StockService` now throws `UomConversionException` on missing or incompatible mappings, preventing silent math errors. ✅ NEW
- [x] **Atomic Piece Ledger** — All inventory quantities (QOH, Cost Layers, Transactions) are stored as integers in the absolute smallest unit ("Pieces"), eliminating floating-point drift. ✅ NEW
- [x] **Controller Exception Hardening** — `TransactionController`, `AdjustmentController`, and `PurchaseOrderController` now catch operational exceptions and return clean 422 errors. ✅ NEW
- [x] **Ledger UOM Persistence** — Refactored `transaction_lines` to store `base_uom_id`, decoupling calculative storage from original transaction UOM for 100% audit transparency. ✅ NEW
- [x] **Full Concurrency & Locking Audit** — Comprehensive race-condition audit across all controllers and StockService. PO status transitions (approve/send/ship/close), GRN receive, and processReturn now use `DB::transaction` + `lockForUpdate`. `postTransaction()` and `reverseTransaction()` lock the Transaction header row before idempotency checks. `ReorderRuleController` catches `QueryException` for DB-level unique guard. 33 tests, 113 assertions — 100% passing. ✅ NEW
- [x] **Inventory Costing Engine Refactor (Strategy Pattern)** — Successfully refactored the engine to use pluggable FIFO, LIFO, and Weighted Average strategies. Decoupled valuation logic from physical movement and ensured the global `average_cost` invariant is maintained across all receipt types. ✅ NEW
- [x] **Product-Aware Contextual Scaling** — Upgraded `UomHelper` and core controllers to support product-specific UOM conversion rules. The system now allows the same unit (e.g., "Box") to have different conversion factors per SKU without global rule pollution. ✅ NEW
- [x] **Enterprise Financial Precision Remediation** — Systemically eradicated PHP native floating-point math arithmetic from the engine. Deployed `FinancialMath` (BCMath wrapper) across all transaction, controller, and accumulation pipelines to enforce strict `decimal(18, 8)` string math. **Hardened against scale-truncation bugs and enforced the 'Honest Truth' standard (Ledger Precision + Visual Tilde Cues).** ✅ NEW


### 0.2 Database Schema
- [x] 45 migrations covering every business domain
- [x] 40 Eloquent models with full relationships, soft deletes, and fillables
- [x] `inventories` — per-product per-location QOH cache + average cost
- [x] `inventory_cost_layers` — FIFO/LIFO layers with `received_qty`, `issued_qty`, computed `remaining_qty`, `is_exhausted`
- [x] `transactions` + `transaction_lines` — the immutable ledger (issue lines now record actual COGS in `unit_cost`)
- [x] `transaction_types` + `transaction_statuses` — lookup tables (Receipt, Issue, Transfer, Adjustment)
- [x] `transfers` — pivot table linking the two transaction legs of every stock transfer by FK

### 0.3 Automated Tests
- [x] FIFO layer test: 3 receipts at different unit costs → verify correct layer consumed on issue
- [x] LIFO layer test: same as above but in reverse order
- [x] Concurrency test: 10 simultaneous issues of last 1 unit → only 1 succeeds, 9 throw exception
- [x] UOM Conversion test: receive 1 Box → verify `quantity_on_hand` increases by 12 (pieces)
- [x] Average cost test: verify WAC formula recalculates correctly across multiple receipts

### 0.4 Inventory Reservation Engine
> Status: ✅ COMPLETE
- [x] Database: `reserved_qty` column added to `inventories` table.
- [x] `StockService::reserveStock(Product $p, Location $l, float $qty)`:
  - Increases `reserved_qty` (does not touch `quantity_on_hand`).
  - Guards against `reserved_qty + requested > quantity_on_hand`.
- [x] `StockService::releaseReservation(Product $p, Location $l, float $qty)`:
  - Decreases `reserved_qty`.
- [x] **Full UOM Reservation Scaling** — Added `scaled_reserved_qty` to `Inventory` model to ensure mathematically correct availability across all units. ✅ NEW
- [x] Integration: confirmed orders trigger `reserveStock()`; Fulfillment triggers `releaseReservation()` + `recordMovement()`. (Reservation engine tested and verified).

---

## ✅ Phase 1 — System Setup: Master Data & Auth
> Status: COMPLETE
> Before any transaction can occur, the system needs its catalog, users, and configuration.

### 1.1 Authentication & Access Control
- [x] Session-based login / logout (`LoginController`) for Inertia web routes
- [x] **Google OAuth** (`GoogleController`) — `auth/google` + `auth/google/callback` routes, social login fully wired
- [x] Laravel Sanctum — token-based auth for REST API
- [x] `Login.vue` — username + password form, redirects to dashboard on success
- [x] `HandleInertiaRequests` — shares user, role, permission slugs, and `transactionMeta` (type/status id maps) to all frontend pages
- [x] Role-Permission system: `roles`, `permissions`, `role_permission` pivot (slug-based)
- [x] `PermissionSeeder` — seeds default roles (Admin, Warehouse, Sales, Viewer) and all permission slugs
- [x] `usePermissions.js` composable — `can(slug)` helper for permission-gating UI elements
- [x] `CheckPermission` middleware — tied to API routes enforcing server-side security for writes
- [x] **Login Security Guard** — Enforced `is_active` status in both Password and Google OAuth login flows. ✅ NEW
- [x] **Null-Safe Permissions** — Hardened `User::hasPermission()` to handle users with missing or invalid roles safely. ✅ NEW
- [x] **Permission Granularity** — Split PO access into `view-purchase-orders` (READ) and `manage-purchase-orders` (WRITE). ✅ NEW

### 1.2 Location & Warehouse Configuration
- [x] `locations` and `location_types` tables — migrated and seeded
- [x] `Location` Eloquent model with relationships
- [x] `LocationController` — Full CRUD API (`/api/locations`)
- [x] `LocationStoreRequest` + `LocationResource` (API response transformer)
- [x] **Location Center** — UI to construct network topology (warehouses, stores, bins, transit)
- [x] Mark locations as active/inactive (`is_active` toggle)
- [x] Set a "default receive location" per warehouse

### 1.3 Product Catalog (Master Data)
- [x] `ProductController` — Full CRUD (`/api/products`) via `ProductService`
- [x] `CategoryController` — Full CRUD (`/api/categories`)
- [x] `UnitOfMeasureController` — Full CRUD (`/api/uom`)
- [x] `CostingMethodController` — Read-only (`/api/costing-methods`)
- [x] `ProductService::search()` — filter by name, SKU, product_code
- [x] `ProductStoreRequest` + `ProductUpdateRequest` — validated input
- [x] `ProductResource`, `CategoryResource`, `UnitOfMeasureResource`, `CostingMethodResource`
- [x] `HasAttachments` trait — image/file upload handling
- [x] **Catalog.vue** — Full CRUD UI:
  - Create / edit / soft-delete with confirmation dialog
  - Fields: name, code, SKU, barcode, brand, description, category, UOM, costing method, preferred vendor, selling price, reorder point, status
  - Image upload with preview (FormData/multipart)
  - Permission-gated actions via `can('manage-products')`
  - Live search + paginated DataTable

### 1.4 Vendor Master
- [x] `VendorController` — Full CRUD (`/api/vendors`)
- [x] `VendorResource` — API response transformer
- [x] **Vendor Database Alignment** — Renamed column from `code` to `vendor_code` for naming consistency.
- [x] **VendorCenter.vue** — Full CRUD view with high-performance search.
- [x] **Vendor Create/Edit Dialog** — Interactive premium form integrated into Vendor Center.

### 1.5 UOM & Costing Configuration ✅ NOW LIVE
- [x] `UnitOfMeasureController` — Full CRUD
- [x] **UOM Management Page** — (UOM Center added to frontend UI)
- [x] `UomConversionController` — CRUD for conversion factors (e.g., 1 Box = 12 pcs)
- [x] UOM Conversion logic integrated into `StockService`

### 1.6 Seeders (Reference Data)
- [x] `TransactionTypeSeeder` — Receipt, Issue, Transfer, Adjustment
- [x] `TransactionStatusSeeder` — Draft, Posted, Cancelled
- [x] `PurchaseOrderStatusSeeder`, `SalesOrderStatusSeeder`, `LocationTypeSeeder`
- [x] `CategorySeeder`, `VendorSeeder`
- [x] `PermissionSeeder` — roles and permission slugs
- [x] `TestDataSeeder` — exhaustive test data for cost layers and multi-location distribution ✅ NEW
- [x] `SampleDataSeeder` — realistic demo data

### 1.7 App Shell
- [x] `AppLayout.vue` — collapsible sidebar, topbar, localStorage persistence
- [x] All 5 active routes wired: Dashboard, Catalog, Inventory Center, Vendor Center, Location Center
- [x] Topbar: username, role badge, logout button
- [x] Active nav link highlighting — `page.url.startsWith(item.href)` active state applied to all nav items
- [x] Notification bell (future — for low stock alerts, pending approvals)

---

## ✅ Phase 2 — Warehouse Operations: Stock Movements
> Status: COMPLETE — 100% System-wide Precision aligned across 12+ movement forms and order mission controls.

### 2.1 Stock Movement API ✅ COMPLETED
- [x] `POST /api/transactions` — Create any stock movement (receipt, issue, adjustment)
  - Body: `{ header: { transaction_type_id, transaction_status_id, transaction_date, reference_number, from_location_id, to_location_id, vendor_id?, customer_id?, notes }, lines: [{ product_id, location_id, quantity, unit_cost, uom_id? }] }`
  - Draft status: saves header+lines only, inventory untouched
  - Posted status: immediately updates inventory, cost layers, WAC
  - Returns `TransactionResource` with all relationships loaded
- [x] `PATCH /api/transactions/{id}/post` — Promote Draft → Posted (inventory updated at this moment)
- [x] `PATCH /api/transactions/{id}/cancel` — Void a draft (posted cancellation triggers reversal)
- [x] `POST /api/transfers` — Atomic two-leg transfer; links both legs via `transfers` pivot table
- [x] `TransactionStoreRequest` + `TransferStoreRequest` — fully validated with FK existence checks
- [x] `CheckPermission` middleware applied to all write routes (`manage-inventory`)
- [x] `POST /api/adjustments` — Dedicated adjustment endpoint (wrapper for ADJS type)
- [x] `AdjustmentReasonController` — Read-only list (`/api/adjustment-reasons`) 
- [x] Reversal logic for posted transaction cancellation (Creates a counter-transaction)

### 2.2 Inventory Query API ✅ COMPLETED
- [x] `GET /api/inventory/{product_id}/locations` — Stock per location for a single product
- [x] `GET /api/inventory/{product_id}/cost-layers` — Current FIFO/LIFO layers for a product
- [x] `GET /api/inventory` — Global stock list (product × location × QOH × average_cost)
- [x] `GET /api/inventory/low-stock` — All products where `QOH < reorder_point`
- [x] `InventoryResource` — response transformer

### ✅ 2.3 Inventory Intelligence Grid — NOW LIVE
- [x] Product sidebar (searchable Listbox, auto-select first)
- [x] Product specs panel (SKU, price, UOM, costing method)
- [x] Transaction ledger, pulling directly from live backend history
- [x] **QOH display** per product item in sidebar (color-coded: green/amber/red by stock level)
- [x] **"New Movement" button** — popup menu wired to all 4 movement form routes with product pre-selection
- [x] **Average Cost (WAC)** display in the product technical manifest
- [x] **Cost Layer Inspector panel** — High-performance table showing live FIFO/LIFO layers with status tracking
- [x] **Location breakdown** — Visualized QOH distribution split by physical warehouse/location nodes
- [x] **UOM in Ledger** — Ledger rows now explicitly show the move unit (PCS, BOX, etc.) and perform recursive conversion to "Atomic Pieces". ✅ NEW
- [x] **Recursive Conversion Engine** — Implemented `UomHelper` to bridge non-direct units (e.g. Case of 24 -> Box of 12) via a shared base unit. ✅ NEW
- [x] **Navigation & Context** — Enabled direct navigation from transaction references to movement details with smart "Go Back" history tracking. ✅ NEW
- [x] **Null-Safety & Error Resolution** — Audited template structure and backend resources for 100% stability.


### ✅ 2.4 Stock Movement UI (Terminology & v4 Migration) — NOW LIVE
- [x] **Monochrome Slate Redesign** — All movement forms updated to the industrial dark aesthetic.
- [x] **Business Terminology** — Replaced technical jargon with standard business terms (Receipt, Issue, Transfer, Adjustment).
- [x] **PrimeVue v4 Migration** — `Dropdown` components fully replaced with `Select` components.
- [x] **Paginator Fix** — `RowsPerPageDropdown` updated to `RowsPerPageSelect` in Catalog.
- [x] **Navigation Integration** — All forms wired to sidebars and context-aware URL parameters.

### ✅ 2.5 Transaction Wiring & Processing — DONE
- [x] **Receipt Form Submission** — Implement `useForm` to POST to `/api/transactions`.
- [x] **Issue Form Submission** — Link to `/api/transactions` with COGS tracking.
- [x] **Transfer Form Submission** — Wire to double-leg `/api/transfers` endpoint.
- [x] **Adjustment Form Submission** — Link to `/api/adjustments` with reason codes.
- [x] **Real-time Stock Checks** — Frontend validation against available QOH before submission.

---

## 📊 Phase 3 — Dashboard & Command Center
> Status: IN PROGRESS — API live, primary UI wired up and rendering dynamically.

### 3.1 Dashboard API ✅ NOW LIVE
- [x] `DashboardController` with `GET /api/dashboard/stats`:
  - [x] `total_products` (all, including soft-deleted)
  - [x] `total_inventory_value` (SUM of QOH × average_cost across all inventories)
  - [x] `low_stock_count` (products where aggregate QOH < reorder_point)
  - [x] `recent_transactions` — last 5 transaction lines with product + type
  - [x] `transactions_today` count
  - [x] `pending_po_count` — (live)
  - [x] `pending_so_count` — (live)
- [x] `GET /api/inventory/low-stock` — dedicated low stock endpoint

### 3.2 Dashboard UI Overhaul ✅ NOW LIVE
- [x] KPI cards fully wired to live API (`loadDashboard`)
- [x] Recent Transactions feed (live)
- [x] Critical Low Stock Alerts side panel (replaces placeholder)
- [x] Pending POs + Pending SOs count cards
- [x] Stock value trend mini-chart (last 7 days)

---

## 🚧 Phase 4 — Procurement: Purchase Order Lifecycle
> Status: IN PROGRESS — Basic lifecycle live; Multi-UOM conversion & RTV core pending refinement.

### Procurement Workflow
```
Replenishment Suggestion (UOM-Aware)
       ↓
  Draft PO Created  →  Approved  →  Sent to Vendor
       ↓                                  ↓
  PO Lines (product,                Vendor ships goods
   qty, UOM, price)                       ↓
       ↓                           Goods Receipt Note (GRN)
  PO Received (auto-convert) →  StockService::recordMovement()
       ↓                            ↓
  PO status: Partially         Transaction posted,
  Received / Closed            cost layers updated (in Pieces)
```

### 4.1 Purchase Order API
- [x] `PurchaseOrderController` — Full CRUD (`/api/purchase-orders`)
- [x] `PurchaseOrderStoreRequest` + `PurchaseOrderUpdateRequest`
- [x] `PurchaseOrderResource` + `PurchaseOrderLineResource`
- [x] PO Status lifecycle:
  - `Draft` → `Approved` → `Sent` → `Partially Received` → `Closed` | `Cancelled`
- [x] `PATCH /api/purchase-orders/{id}/approve` — Approve a PO
- [x] `POST /api/purchase-orders/{id}/receive` — Post a Goods Receipt Note (GRN):
  - [x] Creates a `Transaction` (type: Receipt) via `StockService`
  - [x] Links `transaction.reference_doc` manually
  - [x] Updates `purchase_order_lines.received_qty`
  - [x] Auto-transitions PO to `Partially Received` or `Closed`
- [x] **Multi-UOM Integration**:
  - [x] Add `uom_id` to `purchase_order_lines`.
  - [x] Implement conversion logic in `PurchaseOrderController@receive` (convert PO UOM to Base UOM before posting to `StockService`).

### 4.2 Purchase Orders Frontend ✅ NOW LIVE (v1.0)
- [x] **Purchase Orders list page** (`/purchase-orders`)
- [x] **PO Create/Edit form**
  - [x] Header: Vendor, expected delivery date, notes
  - [x] **Multi-UOM Line Selector** — support for choosing "Box", "Case", etc.
  - [x] "Save Draft" and "Discard" buttons
- [x] **PO Detail / Receive page**
  - [x] Shows PO metadata and lines with ordered qty vs. received qty
  - [x] Post Goods Receipt Note logic (receives remaining pending qty)
- [x] PO status badges and lifecycle action buttons (Approve, GRN, Delete)

### 4.3 Replenishment & Automation Engine ✅ NOW LIVE
- [x] **ReorderRuleController** — Location-specific and global multi-tier threshold management.
- [x] **Reorder Rules UI** — Modal in the Inventory Center to configure `min_stock` and `reorder_qty` per location.
- [x] **ReplenishmentService** — Headless scan engine that checks true stock (Product + Location) against active Reorder thresholds.
- [x] **Auto-Suggestions Pipeline** — Engine creates `ReplenishmentSuggestion` records with precise fill amounts and automated clean-up routing.
- [x] **Bulk Procure-to-PO** — Converting 1-to-N suggestions directly into drafted POs aggregated by vendor.

### ✅ 4.4 Purchase Returns / RTV Core Engine — HARDENED
- [x] Database: `returned_qty` column on PO lines.
- [x] Core Transaction Type `PRET` defined.
- [x] API Endpoint `POST /api/purchase-orders/{id}/return` — intelligent replacement (reopens PO) vs credit note (closes PO line) logic, natively tied into the Stock Engine.
- [x] **Financial Recalculation** — "Credit" resolutions automatically shrink the PO `total_amount` for accounting accuracy. ✅ NEW
- [x] **Over-Return Prevention** — Validation logic to ensure return quantities do not exceed net physical receipts. ✅ NEW

---

## ✅ Phase 5 — Sales: Sales Order Lifecycle
> Status: COMPLETE — Schema, Stock Reservation Engine, API & UI fully integrated.

> **Pre-Execution Audit Note (2026-04-03):** A full workflow audit before implementation
> identified 5 critical blockers that are resolved as part of this phase:
> 1. `sales_order_lines` missing `location_id` (required for reservation + fulfillment targeting)
> 2. `StockService::reserveStock()` does not convert UOM (controller must pre-convert)
> 3. Fulfillment atomicity: `releaseReservation()` MUST precede `recordMovement(Issue)`
> 4. SO status names were Title Case — normalized to lowercase snake_case to match PO standard
> 5. `sales_orders` missing standard fields (`carrier`, `tracking_number`, `sent_at`, `shipped_at`)

### Sales Workflow (Enterprise-Grade)
```
Customer Inquiry
       ↓
Quotation Created
       ↓
Confirmed SO  →  Stock Reserved (reserveStock)
       ↓
Picking List Created
       ↓
Items Picked (picked_qty updated)
       ↓
Packing (packed_qty updated)
       ↓
Shipment Created (carrier / tracking)
       ↓
DB Transaction:
   - releaseReservation()
   - recordMovement(Issue)
   - consume cost layers (COGS)
       ↓
Partial?  →  Processing (Backorder) → repeat fulfillment
       ↓
Invoice (configurable timing)
       ↓
Payment Received
       ↓
SO Completed (closed)
```

### SO Status Lifecycle (Normalized — lowercase snake_case)
```
quotation → quotation_sent → confirmed → picked → packed → shipped → closed | cancelled
```

### 5.1 Customer Management API
> Status: ✅ COMPLETE
- [x] `CustomerController` — Full CRUD (`/api/customers`)
- [x] `CustomerResource` + `CustomerStoreRequest` + `CustomerUpdateRequest`
- [x] **Customer Center UI** (`CustomerCenter.vue`) — Dashboard with premium cyan-themed UI.
- [x] Customer fields: name, code, email, phone, billing_address, shipping_address, tax_number, credit_limit, is_active.

### 5.2 Sales Order API
> Status: ✅ COMPLETE

- [x] **Migration**: Add `location_id` (nullable FK → `locations`) to `sales_order_lines`. ✅
- [x] **Migration**: Add `carrier`, `tracking_number`, `sent_at`, `shipped_at`, `delivered_at`, `approved_by` to `sales_orders`. ✅
- [x] **Migration**: Add `picked_qty`, `packed_qty`, `shipped_qty`, `returned_qty` to `sales_order_lines`. ✅
- [x] **Model**: Align `SalesOrder` and `SalesOrderLine` with PO standards (casts, booted logic, helpers). ✅
- [x] **Seeder Fix**: Normalize `SalesOrderStatus` names to lowercase snake_case + add `partially_picked` and `partially_packed` constants. ✅
- [x] **Professional Printing Engine**: Implemented [sales-order-print.blade.php](file:///c:/xampp/htdocs/inventory-system/resources/views/sales/sales-order-print.blade.php) for branded Picking Lists & Packing Slips. ✅

#### Controller & Routing
- [x] `SalesOrderController` — Mirroring PO action naming + WMS stages:
  - `approve()` — Transition `quotation` → `confirmed` (Triggers `reserveStock`)
  - `send()` — Transition `quotation` → `quotation_sent`
  - `pick()` — Granular Pick Dialog → Update `picked_qty` → Transition to `partially_picked` | `picked`. ✅
  - `pack()` — Granular Pack Dialog → Update `packed_qty` → Transition to `partially_packed` | `packed`. ✅
  - `ship()` / `fulfill()` — Granular Ship Dialog → Atomic release + issue transaction. ✅
  - `print()` — Server-side PDF/Print generation for warehouse vouchers. ✅
- [x] `SalesOrderResource` — Full transformer with embedded lines + stage progress + fulfillment history
- [x] **Multi-UOM Schema**: `uom_id` on `sales_order_lines`. ✅
- [x] **Multi-UOM Logic**: `confirm()` and `fulfill()` convert to base UOM before calling StockService.
- [x] **Financial Precision Schema**: `tax_rate`, `tax_amount`, `discount_rate`, `discount_amount` on lines. ✅
- [x] **Financial Computation**: `store()` and `update()` compute line totals and `total_amount` header.

### 5.3 Sales Orders Frontend ✅ NOW LIVE
- [x] **Sidebar nav entry**: "Sales" (`pi pi-receipt`, teal accent) → `/sales-orders`
- [x] **Web routes**: `/sales-orders`, `/sales-orders/create`, `/sales-orders/{id}`
- [x] **`SalesOrders/Index.vue`** — Premium dark index:
  - DataTable: SO Number, Customer, Order Date, Status badge, Total Value, →
  - Status badge colors (lowercase): quotation=warning, confirmed=info,
    processing=help, shipped=success, cancelled=danger, closed=secondary
  - Search field + "Draft SO" button (requires `manage-sales-orders`)
- [x] **`SalesOrders/Form.vue`** — Multi-section creation form:
  - Header: Customer selector, delivery date, currency, notes
  - Lines: Product, Location (shows QOH), UOM, Qty, Unit Price (auto-fill from `selling_price`),
    Tax Rate %, Discount Rate %, computed line total
  - QOH availability indicator per line (amber/red warnings)
  - Financial summary footer: Subtotal, Discount, Tax, Grand Total
  - "Save as Quotation" + "Discard" buttons
- [x] **`SalesOrders/Show.vue`** — Warehouse Mission Control:
  - Sidebar: Order metadata + Fulfillment/Return History (mirrored after PO Show).
  - Stage-aware Grid: ordered_qty, picked_qty, packed_qty, shipped_qty (status tracked).
  - **Surgical Fulfillment Dialogs**: Native support for Pick, Pack, and Ship stages. ✅
  - **Lifecycle Progress Bars**: Real-time visual tracking of warehouse stages. ✅
  - **Quotation Inventory Intelligence Grid**: Real-time stock health popover with location-specific QOH vs. Reserved breakdown. ✅
  - Status badge colors: `quotation`=warning, `confirmed`=info, `picked`=help, `packed`=help, `shipped`=success.
  - Linked issue transactions panel (COGS tracking).
  - Financial summary

### 5.4 Sales Returns (RMA) — Core Engine
> Status: ✅ COMPLETE
- [x] Core Transaction Type `SRET` (Sales Return) defined in `StockService`
- [x] `POST /api/sales-orders/{id}/return` — Post a Sales Return:
  - [x] Creates a `Transaction` (type: Receipt) via `StockService`
  - [x] Reverses cost calculation or creates a "Restocked" layer
  - [x] Updates `sales_order_lines.shipped_qty` (decrement)
- [x] Return reasons (Defective, Wrong Item, Customer Change)
- [x] Credit Note generation (links to Invoicing)

### 5.5 Invoicing & Customer Payments
> Status: ✅ COMPLETE (Native support implemented)
- [x] `InvoiceController` — CRUD (`/api/finance/invoices`) with Draft → Posted lifecycle.
- [x] Invoice linked to Sales Order (supports partial invoicing and line-level selection).
- [x] `PaymentController` — CRUD (`/api/finance/payments`).
- [x] Payment allocation logic (Apply payment to one or more invoices with balance tracking).
- [x] **Credit Notes** — Automatically generated via Sales Return resolution logic.
- [x] Customer Statement generation.
- [x] **Credit Limit enforcement** — Integrated into SO Approval gating (`SalesOrderController@approve`).
- [x] **Finance Center UI** — Build the frontend for Invoices, Payments, Credit Notes, and Document Viewers.

---

## 💰 Phase 5.7 — Procurement Financials: Accounts Payable (A/P)
> Status: ⬜ NOT STARTED
> Bridge the gap between receiving goods and paying vendors.

### 5.7.1 Vendor Billing (Bills)
- [ ] `BillController` — CRUD (`/api/finance/bills`) linked to Receipts (GRN).
- [ ] Logic: Convert specific PO lines into a Bill.
- [ ] Support for **Partial Billing** (Bill only what was received).
- [ ] `billable_qty` tracking on PO lines (already supported by lines model).

### 5.7.2 Vendor Payments & Debit Notes
- [ ] `VendorPaymentController` — Issue payments against vendor bills using `vendor_payments` table.
- [ ] `DebitNoteController` — Handle financial reversals for Purchase Returns (PRET) using `debit_notes` table.
- [ ] **Vendor Statement generation**: High-precision statement of account for every supplier.

---

### 5.6 Backorder & Short-Fulfill Management
> Status: ✅ COMPLETE (Native support implemented)
- [x] **Backorder Tracking**: Visualized via progress indicators where `shipped_qty < ordered_qty`.
- [x] **Split Fulfillment**: UI/API support for shipping partial quantities; natively supported by the Pick/Pack/Ship Mission Control.
- [x] **Procurement Trigger**: Automatically link short-fulfilled SOs to the `ReplenishmentSuggestion` engine in Phase 4.3.
- [x] Feature Complete: Polish UI/UX, verify test coverage, and complete integration phase 5.6.

### 🏁 Phase 5.5 - 5.6 Hardening (Ledger Integrity Audits)
> Status: ✅ COMPLETE (As of 2026-04-12)
- [x] **Audit v1-v3**: Rationalized all financial models for Honest Truth (8dp intermediate / 2dp header).
- [x] **Audit v4**: Eliminated costing "Split-Brain" — migrated Global Avg Cost to 100% BCMath PHP engine.
- [x] **Audit v5**: Aligned PO Lifecycle with SO — implemented formal CANCELLED status and billing bridges.
- [x] **Exhaustive Schema Audit**: 100% pinpointing of all 57 database tables vs. strategic roadmap nodes.

---

## 💰 Phase 5.7 — Procurement Financials: Accounts Payable (A/P)
> Status: ⬜ NOT STARTED
> Bridge the gap between receiving goods and paying vendors.

### 5.7.1 Vendor Billing (Bills)
- [ ] `BillController` — CRUD (`/api/finance/bills`) linked to Receipts (GRN).
- [ ] Logic: Convert specific PO lines into a Bill.
- [ ] Support for **Partial Billing** (Bill only what was received).
- [ ] `billable_qty` tracking on PO lines (already supported by lines model).

### 5.7.2 Vendor Payments & Debit Notes
- [ ] `VendorPaymentController` — Issue payments against vendor bills.
- [ ] `DebitNoteController` — Handle financial reversals for Purchase Returns (PRET) using the `DebitNote` bridge.
- [ ] **Vendor Statement generation**: High-precision statement of account for every supplier.

---

## 🚚 Phase 6 — Logistics: Shipments & Carriers
> Status: 🚧 IN PROGRESS (Schema & Model layer complete)

### 6.1 Shipments API
- [ ] `ShipmentController` — CRUD (`/api/shipments`).
- [ ] `CarrierController` — Read/Write (`/api/carriers`).
- [ ] Shipment linked to SO — one SO can have multiple shipments (partial).
- [ ] Fields: tracking number, carrier, ship date, estimated delivery, status.

### 6.2 Shipments Frontend
- [ ] **Shipments panel** on SO Detail page.
  - Log a shipment with carrier + tracking number.
  - View all shipments for an SO.
- [ ] **Carriers lookup management page** (in Settings).

### 6.3 Serial / Batch Tracking
- [ ] `ProductSerialController` — assign + query serial numbers using dormant `product_serials` table.
- [ ] On Receipt: assign serials to `transaction_line_serials` for unit-level traceability.
- [ ] On Issue/Ship: select specific serial numbers to fulfill.
- [ ] `product_serials` status lifecycle: `In Stock` → `Reserved` → `Sold` | `Returned`.
- [ ] **Serial Registry page** — search serials, view status, view transaction history per unit.

### 6.4 Landed Costs & Valuation Adjustment
- [ ] `LandedCostController` — allocation of freight, tax, and insurance.
- [ ] Logic: Prorate overhead costs (by value or weight) into the `inventory_cost_layers`.
- [ ] Ensure "Honest Truth" 8-decimal scaling for prorated costs.

---

## 💰 Phase 7 — Pricing & Discounts
> Status: 🚧 IN PROGRESS (Schema & Model layer complete)

### 7.1 Landed Costs & Valuation Alignment
- [ ] `LandedCostController` — allocation of freight, tax, and insurance using `landed_costs` table.
- [ ] Logic: Prorate overhead costs (by value or weight) into the `inventory_cost_layers`.
- [ ] Ensure "Honest Truth" 8-decimal scaling for prorated costs.

### 7.2 Price Lists & Discounts API
- [ ] `PriceListController` — CRUD (`/api/price-lists`).
- [ ] `PriceListItemController` — manage per-product prices within a list.
- [ ] `DiscountController` — CRUD for discount rules.
- [ ] Price list assignment: to customer, to customer group, or default.
- [ ] Price resolution logic on SO creation: customer price list → default list → product.selling_price.

---

## 📈 Phase 8 — Reporting & Financial Analysis
> Status: NOT STARTED — reports/report_runs tables exist, no engine or frontend

### 8.1 Reports API Engine
- [ ] `ReportController` — list available reports using the dormant `reports` table.
- [ ] Async report runs via Laravel Jobs (activating `jobs` and `report_runs` tables).
- [ ] `ReportRunController` — check status + download result.

### 8.2 Historical Valuation (Snapshot Engine)
- [ ] **EOD/EOM Snapshot Engine**: Automate the population of the dormant `stock_snapshots` table.
- [ ] Valuation Dashboard: Visualizing stock value over time (FIFO/LIFO/Average) using snapshots.

| Report | Description |
|--------|-------------|
| **Inventory Valuation** | Total on-hand value by product + location using actual cost layers |
| **Stock Movement Report** | Full transaction log with filters (date, type, product, vendor, location) |
| **FIFO/LIFO Cost Layer Report** | Current open layers per product — remaining qty + unit cost |
| **Variance Report** | Theoretical QOH (from transactions) vs. physical count |
| **Reorder Report** | All items below reorder_point + suggested order qty |
| **Gross Margin Analysis** | SO selling price vs. FIFO cost per issue line → profit per order |
| **Purchase Analysis** | PO history by vendor — quantities, costs, lead times |
| **Slow-Moving Stock Report** | Products with no transaction activity in N days |
| **Aging Report** | Stock layers older than N days still in inventory |

### 8.3 Reports Frontend
- [ ] **Reports page** — Enable the disabled nav item in sidebar
- [ ] Report catalog view — browse available report types 
- [ ] Parameter form per report (date range, product filter, category, location)
- [ ] Results DataTable + inline charts
- [ ] Export to PDF and CSV
- [ ] Saved report runs history

### 8.4 Audit Log
- [ ] `GET /api/audit-logs` — paginated, filterable audit trail
- [ ] Filters: user, action type, entity, date range
- [ ] **Audit Log viewer** — dedicated page or admin panel tab
- [ ] Per-record audit trail on detail pages (e.g., show change history on a PO)

---

## 🔒 Phase 9 — Administration & Security
> Status: PARTIALLY STARTED (backend models/middleware exist, no admin UI)

### 9.1 User Management
- [ ] `UserController` — CRUD (`/api/users`)
- [ ] Fields: username, first_name, last_name, email, role, is_active
- [ ] Password reset flow
- [ ] **User Management page** — list, invite (by email), deactivate/reactivate users
- [ ] Assign role to user from UI

### 9.2 Role & Permission Management
- [ ] `RoleController` — CRUD (`/api/roles`)
- [ ] `PermissionController` — Read-only list (`/api/permissions`)
- [ ] **Role Management page** — create roles, toggle permissions via checkbox grid
- [x] Apply `CheckPermission` middleware to all write API routes ✅ — enforced on all create/update/delete routes in `api.php`

### 9.3 Location & Warehouse Admin
- [x] `LocationController` — CRUD (`/api/locations`) ✅ Completed in Phase 1.2
- [x] Location types: Warehouse, Store, Transit, Virtual ✅ Completed in Phase 1.2
- [x] **Location Center page** — manage the warehouse network topology ✅ Completed in Phase 1.2
- [ ] Stock view breakdown per location in Inventory Center (Phase 2.3 dependency)

### 9.4 Metadata & Management UI
- [x] **UOM Management frontend** — `UomCenter.vue` fully built & routed.
- [ ] **Adjustment Reason Management**: Add CRUD for `adjustment_reasons` to remove hardcoding.
- [ ] **System Settings UI**: High-level config management for `system_settings` table.
- [ ] **Attachments Expansion**: Integrate `attachments` into PO/SO Mission Control for document scans.

### 9.5 System Settings
- [ ] `SettingsController` — Read/write key-value system config
- [ ] Settings: default currency, default costing method, default receive location, company name/logo
- [ ] **Settings page** — single page for all system configuration

---

## 🏭 Phase 10 — Production Hardening
> Status: NOT STARTED

### 10.1 Environment & Infrastructure
- [ ] `.env` production config (DB, Redis, queue driver, S3 storage, SMTP)
- [ ] Move file storage from `local` driver to S3/R2 for product images + attachments
- [ ] Configure Redis for session driver and queue backend
- [ ] Set up Laravel Horizon (queue dashboard)

### 10.2 Performance
- [ ] Route caching (`php artisan route:cache`)
- [ ] Config caching (`php artisan config:cache`)
- [ ] Eager loading audit — eliminate N+1 queries in all list endpoints
- [ ] Add database indexes to high-query columns (product_id, location_id, transaction_date)

### 10.3 Security Hardening
- [ ] CSRF protection audit on all state-changing routes
- [ ] Rate limiting on auth endpoints (login throttle)
- [ ] API rate limiting (`throttle:api` middleware)
- [ ] SQL injection audit (already mitigated by Eloquent, but verify raw queries)
- [ ] Sanctum token expiry policy

### 10.4 Notifications
- [ ] Email notification on low stock threshold breach
- [ ] Email on PO approval request
- [ ] In-app notification bell (topbar) for pending actions
- [ ] Notification preferences per user

---

## 🛡️ Phase 11 — System Governance & Auto-Audit
> Status: ⬜ NOT STARTED
> Continuous surveillance of mathematical and transactional integrity.

### 11.1 Real-time Rounding Watchdog
- [ ] Background job to verify line-sums vs header-totals daily.
- [ ] Alerting on any detected drift exceeding `0.00000001`.

### 11.2 Precision Audit Logs
- [ ] Dedicated registry for transactions involving automatic Epsilon adjustments.
- [ ] UI for administrators to review "Precision Events."

---

## Overall Progress Tracker

| phase | domain | status |
|-------|---------|--------|
| 0 | Core Stock Engine | ✅ Complete (Atomic Piece Ledger, Strategy Pattern, WAC, COGS) |
| 1 | System Setup: Master Data & Auth | ✅ Complete (Nexus Branding & Sidebar Hierarchy Optimized) |
| 2 | Warehouse Operations (Stock Movements) | ✅ 100% — Movement Forms + Intelligence Grid + Printable Vouchers live. |
| 3 | Dashboard & KPIs | ✅ Complete — All Phase 3 items live and rendering. |
| 4 | Procurement (Purchase Orders) | ✅ 100% — Lifecycle + GRN/RTV + Printable POs live. |
| 5 | Sales (Sales Orders) | ✅ 100% — Mission Control Fulfillment + Customer Center live. |
| 5.5 | Finance (A/R) | ✅ 100% — Full A/R, Payment Allocation, and Statements live. |
| 5.7 | Finance (A/P) | ⬜ 0% — Strategic Bridge Created (billable_qty live). |
| 6 | Logistics (Traceability) | 🚧 10% — Schema in place; `product_serials` ready for wire-up. |
| 7 | Pricing & Landed Costs | 🚧 5% — Strategic Bridge Created (Landed Cost pending). |
| 8 | Reporting & Snapshots | ⬜ 0% — Schema exists; `stock_snapshots` engine pending. |
| 9 | Administration & Security | 🚧 ~25% — Middleware + models done; Metadata Management pending. |
| 10 | Production Hardening | ⬜ 0% |
| 11 | System Governance | ⬜ 0% — Strategic Engineering Plan Initiated. |

---

## Immediate Next Steps (Priority Order)

1. **Reporting Engine (Phase 8)** — Develop the asynchronous valuation engine to calculate Total Inventory Value and Gross Margin historicals using our new 8-decimal precision standard.
2. **Serial & Batch Tracking (Phase 6.3)** — Implement the `product_serials` registry and integrate barcode scanning into the Receipt/Issue forms.
3. **Logistics & Shipments (Phase 6.1)** — Shipments panel on SO Detail page, Carrier management.
4. **User Management UI (Phase 9.1)** — Create the admin dashboard for managing staff roles and access permissions.

---

## 📂 Master Schema Inventory (61 Tables)
> **Snapshot Date**: 2026-04-12
> **Saturation Level**: 100% (All tables formally assigned to Roadmap)

### 📈 Active Financial Ledger (15 Tables)
`transactions` [P2.1], `transaction_lines` [P2.1], `transaction_types` [P2.1], `transaction_statuses` [P2.1], `transfers` [P2.1], `invoices` [P5.5], `invoice_lines` [P5.5], `payments` [P5.5], `payment_allocations` [P5.5], `payment_refunds` [P5.5], `bills` [P5.7], `vendor_payments` [P5.7], `debit_notes` [P5.7], `landed_costs` [P7.1], `costing_methods` [P1].

### 📦 Master Data & Stock (10 Tables)
`products` [P1.3], `categories` [P1.3], `inventories` [P2.1], `inventory_cost_layers` [P2.2], `locations` [P1.2], `location_types` [P1.2], `units_of_measure` [P1.5], `uom_conversions` [P1.5], `vendors` [P1.4], `customers` [P5.1].

### 🚀 Sales & Procurement Workflow (9 Tables)
`sales_orders` [P5.2], `sales_order_lines` [P5.2], `sales_order_statuses` [P5.2], `purchase_orders` [P4.1], `purchase_order_lines` [P4.1], `purchase_order_statuses` [P4.1], `reorder_rules` [P4.3], `replenishment_suggestions` [P4.3], `attachments` [P9.4].

### 🚚 Logistics & Traceability (5 Tables)
`shipments` [P6.1], `carriers` [P6.1], `product_serials` [P6.3], `transaction_line_serials` [P6.3], `packages` [P6.3].

### 🔮 Pricing & Intelligence (6 Tables)
`price_lists` [P7.2], `price_list_items` [P7.2], `discounts` [P7.2], `reports` [P8.1], `report_runs` [P8.1], `stock_snapshots` [P8.2].

### 🛡️ Governance & Security (6 Tables)
`users` [P1.1], `roles` [P1.1], `permissions` [P1.1], `audit_logs` [P11], `activity_logs` [P11], `adjustment_reasons` [P9.4].

### ⚙️ System Infrastructure (10 Tables)
`permission_role` [P1.1], `personal_access_tokens` [P1.1], `password_reset_tokens` [P1.1], `sessions` [P10], `cache` [P10], `cache_locks` [P10], `failed_jobs` [P8], `jobs` [P8], `job_batches` [P8], `migrations` [P0].

