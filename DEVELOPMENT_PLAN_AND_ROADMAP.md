# Inventory System — Full Lifecycle Development Plan & Roadmap
> Last audited: 2026-03-29 (post-refactor). All status markers reflect actual codebase state.

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

### 0.2 Database Schema
- [x] 35 migrations covering every business domain
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

---

## ✅ Phase 1 — System Setup: Master Data & Auth
> Status: COMPLETE
> Before any transaction can occur, the system needs its catalog, users, and configuration.

### 1.1 Authentication & Access Control
- [x] Session-based login / logout (`LoginController`) for Inertia web routes
- [x] Laravel Sanctum — token-based auth for REST API
- [x] `Login.vue` — username + password form, redirects to dashboard on success
- [x] `HandleInertiaRequests` — shares user, role, and permission slugs to all frontend pages
- [x] Role-Permission system: `roles`, `permissions`, `role_permission` pivot (slug-based)
- [x] `PermissionSeeder` — seeds default roles (Admin, Warehouse, Sales, Viewer) and all permission slugs
- [x] `usePermissions.js` composable — `can(slug)` helper for permission-gating UI elements
- [x] `CheckPermission` middleware — tied to API routes enforcing server-side security for writes

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
- [x] **VendorCenter.vue** — Full CRUD view (vendor details, transaction history, and creation/editing parameters)
- [x] **Vendor Create/Edit Dialog** — Interactive premium form integrated into Vendor Center

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

## 🚧 Phase 2 — Warehouse Operations: Stock Movements
> Status: IN PROGRESS — API layer now live, UI forms remaining

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
- [x] **"New Movement" button** — inline action to trigger stock movement forms (Logic pending Phase 2.4)
- [x] **Average Cost (WAC)** display in the product technical manifest
- [x] **Cost Layer Inspector panel** — High-performance table showing live FIFO/LIFO layers with status tracking
- [x] **Location breakdown** — Visualized QOH distribution split by physical warehouse/location nodes
- [x] **Null-Safety & Error Resolution** — Audited template structure and backend resources for 100% stability


### 2.4 Stock Movement UI (New Pages/Dialogs)
- [ ] **Receipt Form** (`/movements/receipt`)
  - Header: Vendor, Reference (PO #), Date, Destination Location, Notes
  - Lines: Product search, Qty, Unit Cost, UOM
  - "Save as Draft" and "Post" actions
- [ ] **Issue Form** (`/movements/issue`)
  - Header: Customer or Internal, Reference, Date, Source Location
  - Lines: Product, Qty (auto-checks available stock), UOM
- [ ] **Transfer Form** (`/movements/transfer`)
  - From Location → To Location
  - Product lines with qty
  - Atomic — both legs post or neither does
- [ ] **Adjustment Form** (`/movements/adjustment`)
  - Product, Qty (can be negative), Reason dropdown, Notes
- [ ] Enable "Transfers" nav item (currently disabled)

### 2.5 Replenishment
- [ ] `GET /api/replenishment-suggestions` — Products below reorder_point with suggested qty from `reorder_rules`
- [ ] `ReorderRuleController` — CRUD (`/api/reorder-rules`)
- [ ] "Suggest PO" action — pre-populate a Purchase Order draft from replenishment suggestions
- [ ] Replenishment suggestions panel on Dashboard

---

## 📊 Phase 3 — Dashboard & Command Center
> Status: IN PROGRESS — API live, primary UI wired up and rendering dynamically.

### 3.1 Dashboard API ✅ NOW LIVE
- [x] `DashboardController` with `GET /api/dashboard/stats`:
  - [x] `total_products` (all, including soft-deleted)
  - [x] `total_inventory_value` (SUM of QOH × average_cost across all inventories)
  - [x] `low_stock_count` (products where aggregate QOH < reorder_point)
  - [x] `recent_transactions` — last 5 transaction lines with product + type
  - [ ] `transactions_today` count — not yet implemented
  - [ ] `pending_po_count` — not yet implemented (no PO backend)
  - [ ] `pending_so_count` — not yet implemented (no SO backend)
- [ ] `GET /api/dashboard/recent-transactions` — dedicated endpoint (currently embedded in stats)
- [ ] `GET /api/dashboard/low-stock` — dedicated top-5 low stock endpoint

### 3.2 Dashboard UI Overhaul ✅ NOW LIVE
- [x] KPI cards fully wired to live API (`loadDashboard`)
- [x] Recent Transactions feed (live)
- [x] Low Stock Alert logic bound to stats
- [ ] Pending POs + Pending SOs count cards
- [ ] Stock value trend mini-chart (last 7 days)

---

## 📋 Phase 4 — Procurement: Purchase Order Lifecycle
> Status: NOT STARTED — schema fully in place, zero backend/frontend code

### Procurement Workflow
```
Replenishment Suggestion
       ↓
  Draft PO Created  →  Approved  →  Sent to Vendor
       ↓                                  ↓
  PO Lines (product,                Vendor ships goods
   qty, agreed price)
       ↓
  Goods Receipt Note (GRN)  →  StockService::recordMovement()
       ↓                            ↓
  PO status: Partially         Transaction posted,
  Received / Closed            cost layers updated
```

### 4.1 Purchase Order API
- [ ] `PurchaseOrderController` — Full CRUD (`/api/purchase-orders`)
- [ ] `PurchaseOrderStoreRequest` + `PurchaseOrderUpdateRequest`
- [ ] `PurchaseOrderResource` + `PurchaseOrderLineResource`
- [ ] PO Status lifecycle:
  - `Draft` → `Approved` → `Sent` → `Partially Received` → `Closed` | `Cancelled`
- [ ] `PATCH /api/purchase-orders/{id}/approve` — Approve a PO
- [ ] `POST /api/purchase-orders/{id}/receive` — Post a Goods Receipt Note (GRN):
  - Creates a `Transaction` (type: Receipt) via `StockService`
  - Links `transaction.purchase_order_id`
  - Updates `purchase_order_lines.received_qty`
  - Auto-transitions PO to `Partially Received` or `Closed`

### 4.2 Purchase Orders Frontend
- [ ] **Purchase Orders list page** (`/purchase-orders`)
  - DataTable: PO number, vendor, date, status, total value, actions
  - Filters: status, vendor, date range
- [ ] **PO Create/Edit form**
  - Header: Vendor, expected delivery date, notes
  - Lines: product selector, qty, agreed unit cost, UOM
  - "Save Draft" and "Submit for Approval" buttons
- [ ] **PO Detail / Receive page**
  - Shows PO lines with ordered qty vs. received qty
  - "Post Receipt" button per line or bulk
  - GRN history (all receipts against this PO)
- [ ] PO status badge and lifecycle action buttons

---

## 🛒 Phase 5 — Sales: Sales Order Lifecycle
> Status: NOT STARTED — schema fully in place, zero backend/frontend code

### Sales Workflow
```
Customer Inquiry
       ↓
  Sales Quotation  →  Confirmed SO  →  Picking List generated
       ↓                                      ↓
  SO Lines (product,                 Warehouse picks items
   qty, agreed price)                        ↓
       ↓                              StockService::recordMovement()
  Shipment Created  →  Shipped  →    Transaction posted (Issue)
       ↓                             Cost layers consumed (FIFO/LIFO)
  Invoice / Billing                          ↓
       ↓                             SO status: Fulfilled / Closed
  Customer Payment
```

### 5.1 Customer Management API
- [ ] `CustomerController` — Full CRUD (`/api/customers`)
- [ ] `CustomerResource` + `CustomerStoreRequest`
- [ ] Customer fields: name, code, email, phone, address, credit_limit, payment_terms

### 5.2 Sales Order API
- [ ] `SalesOrderController` — Full CRUD (`/api/sales-orders`)
- [ ] `SalesOrderStoreRequest` + `SalesOrderResource`
- [ ] SO Status lifecycle:
  - `Quotation` → `Confirmed` → `Picked` → `Shipped` → `Invoiced` → `Closed` | `Cancelled`
- [ ] `PATCH /api/sales-orders/{id}/confirm` — Confirm a quotation
- [ ] `POST /api/sales-orders/{id}/fulfill` — Post fulfillment:
  - Creates a `Transaction` (type: Issue) via `StockService`
  - Links `transaction.sales_order_id`
  - Consumes FIFO/LIFO cost layers (cost of goods sold tracked)
  - Transitions SO to Shipped/Closed
- [ ] Price lookup: apply price list if assigned to customer

### 5.3 Sales Orders Frontend
- [ ] **Sales Orders list page** (`/sales-orders`)
  - DataTable: SO number, customer, date, status, total value, actions
  - Filters: status, customer, date range
- [ ] **SO Create/Edit form**
  - Header: Customer, delivery date, shipping address, notes
  - Lines: product search, qty, selling price (auto-filled from price list), UOM
  - Stock availability indicator per line (available QOH vs. ordered qty)
  - "Save Quote" and "Confirm Order" buttons
- [ ] **SO Detail page**
  - Lines with qty ordered vs. qty fulfilled
  - "Fulfill / Ship" action button
  - Linked transactions (issue postings)
- [ ] Customers management page or embedded panel

---

## 🚚 Phase 6 — Logistics: Shipments & Carriers
> Status: NOT STARTED — schema in place (shipments, packages, carriers)

### 6.1 Shipments API
- [ ] `ShipmentController` — CRUD (`/api/shipments`)
- [ ] `CarrierController` — Read/Write (`/api/carriers`)
- [ ] Shipment linked to SO — one SO can have multiple shipments (partial)
- [ ] Fields: tracking number, carrier, ship date, estimated delivery, status

### 6.2 Shipments Frontend
- [ ] **Shipments panel** on SO Detail page
  - Log a shipment with carrier + tracking number
  - View all shipments for an SO
- [ ] **Carriers lookup management page** (in Settings)

### 6.3 Serial / Batch Tracking
- [ ] `ProductSerialController` — assign + query serial numbers
- [ ] On Receipt: auto-generate or manually enter serials per unit
- [ ] On Issue/Ship: select specific serial numbers to fulfill
- [ ] `product_serials` status lifecycle: `In Stock` → `Reserved` → `Sold` | `Returned`
- [ ] **Serial Registry page** — search serials, view status, view transaction history per unit
- [ ] Serial scan input (keyboard wedge / barcode scanner compatible)

---

## 💰 Phase 7 — Pricing & Discounts
> Status: NOT STARTED — schema in place (price_lists, price_list_items, discounts)

### 7.1 Price Lists API
- [ ] `PriceListController` — CRUD (`/api/price-lists`)
- [ ] `PriceListItemController` — manage per-product prices within a list
- [ ] `DiscountController` — CRUD for discount rules
- [ ] Price list assignment: to customer, to customer group, or default
- [ ] Price resolution logic on SO creation: customer price list → default list → product.selling_price

### 7.2 Pricing Frontend
- [ ] **Price Lists page** — create lists, set prices per product
- [ ] **Discounts page** — define discount rules (%, flat, volume-based)
- [ ] Price lookup visible on SO Create form (show which price list applied)

---

## 📈 Phase 8 — Reporting & Financial Analysis
> Status: NOT STARTED — reports/report_runs tables exist, no engine or frontend

### 8.1 Reports API Engine
- [ ] `ReportController` — list available reports, trigger a run, fetch results
- [ ] Async report runs via Laravel Jobs (for heavy queries)
- [ ] `ReportRunController` — check status + download result

### 8.2 Core Reports

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
- [ ] Apply `CheckPermission` middleware to all write API routes (currently 0% enforced server-side)

### 9.3 Location & Warehouse Admin
- [x] `LocationController` — CRUD (`/api/locations`) ✅ Completed in Phase 1.2
- [x] Location types: Warehouse, Store, Transit, Virtual ✅ Completed in Phase 1.2
- [x] **Location Center page** — manage the warehouse network topology ✅ Completed in Phase 1.2
- [ ] Stock view breakdown per location in Inventory Center (Phase 2.3 dependency)

### 9.4 UOM & Category Admin
- [ ] UOM Management frontend (currently no UI — only backend)
- [ ] Category Management frontend (currently used as dropdown only)
- [ ] UOM Conversion management UI

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

## Overall Progress Tracker

| Phase | Domain | Status |
|-------|---------|--------|
| 0 | Core Stock Engine | ✅ Complete (refactored: global WAC, COGS tracking, draft enforcement, transfer pivot) |
| 1 | System Setup: Master Data & Auth | ✅ Complete (UOM UI + Conversion Controller implemented) |
| 2 | Warehouse Operations (Stock Movements) | 🚧 ~70% — write API live, Intelligence Grid UI live, Movement forms pending |
| 3 | Dashboard & KPIs | ✅ 100% — backend stats live, frontend successfully consuming them |
| 4 | Procurement (Purchase Orders) | ⬜ 0% — schema + models only |
| 5 | Sales (Sales Orders) | ⬜ 0% — schema + models only |
| 6 | Logistics (Shipments & Serials) | ⬜ 0% — schema + models only |
| 7 | Pricing & Discounts | ⬜ 0% — schema + models only |
| 8 | Reporting & Financial Analysis | ⬜ 0% — schema + models only |
| 9 | Administration & Security | 🚧 ~25% — middleware + models + location UI done, no user/role admin UI |
| 10 | Production Hardening | ⬜ 0% |

---

## Immediate Next Steps (Priority Order)

1. **Stock Movement UI (Phase 2.4)** — Build the Receipt, Issue, Transfer, and Adjustment modals to submit data to Phase 2.1 routes.
2. **Movements Interactivity** — Wire the "New Movement" button to launch the above stock movement forms.
3. **Purchase Orders lifecycle** — Full procurement flow (Phase 4)

