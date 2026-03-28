# Inventory System Detailed Development Plan

## 1. Technical Mission
Deliver a production-ready ERP system where the inventory balance is mathematically guaranteed to be accurate via transactional integrity and real-time costing.

---

## Phase 0: The Core Engine (Integrity Handshake)
*Focus: Finalize the "Math" of the system before building any UI.*

### 0.1 StockService Optimization
- [x] **Weighted Average Task**: Implement logic to update `average_cost` in `products` and `inventories` tables upon every Receipt (RCPT).
- [x] **Transfer Atomicity**: Add `recordTransfer` method to handle simultaneous deduction from origin and addition to destination.
- [ ] **Validation Expansion**: Ensure `StockService` throws `InsufficientStockException` if an issue (ISSU) exceeds available QTY.

### 0.2 Automated Unit Testing
- [ ] **FIFO/LIFO Test Case**: Create a test that posts 3 receipts at different prices and verifies that 1 sale consumes the correct price layers.
- [ ] **Concurrency Test**: Simulate 10 simultaneous sales of the last 1 item to ensure only 1 succeeds (Locking proof).
- [ ] **UOM Test**: Verify that converting "1 Box" to "12 Pieces" results in the correct QTY and Unit Cost adjustment.

---

## Phase 1: Master Data & API (The Inventory Foundation)
*Focus: Expose the database catalog to the frontend.*

### 1.1 RESTful API Controllers
- [ ] **Product Catalog**: `Api/ProductController` (Create, Update, Search, Filter).
- [ ] **Location Hierarchy**: `Api/LocationController` (Tree-view support for Warehouse > Zone > Bin).
- [ ] **Resource Layers**: Implement `ProductResource`, `CategoryResource`, and `UomResource` for clean front-end consumption.

### 1.2 Frontend Bootstrap (Vue.js + Inertia)
- [ ] **Global State**: Set up user authentication state (Roles/Permissions).
- [ ] **UI Component Library**: Install and configure PrimeVue (or similar) for advanced data tables.
- [ ] **Product Management Screen**: Full CRUD with image/attachment support via the `HasAttachments` trait.

---

## Phase 2: Transactional Workflows & UI (Weeks 3-5)
*Focus: Bringing the engine to life with a functional dashboard.*

### 2.1 UI Start Point: The "Stock Command Center"
**When to Start**: Immediately after the API endpoints for Products and Locations are stable (End of Week 2).

**First UI Deliverables**:
- [ ] **Global Inventory Grid**: A read-only PrimeVue DataTable showing real-time stock levels with a "Stock Movement" quick-action button.
- [ ] **The "Inbound/Outbound Posting" Form**: A structured form that sends data to `StockService`.
- [ ] **Cost Layer Inspector**: A drill-down view for each product to see their current FIFO/LIFO layers.
- [ ] **Transfer Wizard**: A drag-and-drop or location-select form for moving stock between BINs or Warehouses.

### 2.2 Dashboard & Visualization
- [ ] **Warehouse Heatmap**: Bar chart showing stock value/quantity distributed by location.
- [ ] **Low Stock Alerts**: Real-time identification of products below their `reorder_point`.

---

## Phase 3: Order Management & Logistics
*Focus: Supply chain integration.*

### 3.1 Procurement Flow
- [ ] **PO Lifecycle**: Handle Draft -> Approved -> Partially Received -> Closed.
- [ ] **Auto-Receiving**: One-click "Post Receipt" from an approved PO.

### 3.2 Sales & Logistics
- [ ] **SO Lifecycle**: Handle Quotation -> Order -> Picked -> Shipped.
- [ ] **Serial Tracking UI**: Interface to scan/select specific serial numbers for high-value product shipments.

---

## Phase 4: Financial & Audit (ERP Compliance)
*Focus: Reporting and auditability.*

### 4.1 Reporting Suite
- [ ] **Inventory Valuation**: Total value on hand based on actual FIFO layers.
- [ ] **Variance Report**: Comparison of theoretical stock vs. physical count.
- [ ] **Margin Analysis**: Real-time profit calculation by comparing SO Unit Price vs. FIFO Unit Cost.

---

## Readiness & Acceptance Checklist
- [x] Database successfully migrated (48 Tables).
- [x] Seeders populated for all Lookups (UOM, Statuses, Types).
- [ ] `StockService` tests pass 100%.
- [ ] Environment variables configured for local development.
