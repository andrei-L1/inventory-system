# Implementation Plan - Phase 5: Sales Order Lifecycle (Comprehensive)

This plan outlines the complete development cycle for the Sales Order (SO) domain, ensuring mathematical integrity via stock reservations and a complete financial audit trail.

## ✅ Completed Milestones

### 1. Stock Engine Hardening (Step 1)
- [x] Database: Added `reserved_qty` to the `inventories` table.
- [x] Service: Implemented `StockService::reserveStock()` and `releaseReservation()` with pessimistic locking.
- [x] Validation: Updated `applyLineToInventory()` to prevent physical stock issuance if it overlaps with reserved quantities.
- [x] Automated Tests: Created `StockReservationTest` confirming no over-selling.

### 2. Sales Order Schema Refinement (Step 2)
- [x] Database: Added `uom_id`, `tax_rate`, `tax_amount`, `discount_rate`, and `discount_amount` to `sales_order_lines`.
- [x] Models: Updated `SalesOrderLine` with the new fillable fields and `uom()` relationship.

### 3. Customer Master Data (Step 3)
- [x] Backend: Created `CustomerController`, `CustomerResource`, and API routes.
- [x] Permissions: Seeded and assigned `view-customers` and `manage-customers`.
- [x] UI: Built the **Customer Center** (`CustomerCenter.vue`) with a premium cyan-themed interface, searchable list, and sales history.
- [x] Navigation: Integrated Customers into the sidebar.

---

## 🚧 Current Phase: Step 4 - Phase A: Quotation

- [ ] Create `SalesOrderController`, `SalesOrderResource`, and `SalesOrderStoreRequest`.
- [ ] Implement basic SO CRUD (Quotation status).
- [ ] Create **Sales Order Index** UI (`SalesOrderCenter.vue`).
- [ ] Create **Sales Order Form** UI (`SalesOrderForm.vue`) with:
    - Real-time Subtotal, Tax, and Discount calculations.
    - Customer selection.
    - Product line item entry with UOM selection.

---

## 📅 Remaining Steps

### Step 5 - Phase B: Confirmation & Reservation
- [ ] Add `confirm` action to `SalesOrderController`.
- [ ] Implementation: When moving SO from "Quotation/Draft" to "Confirmed":
    - Call `StockService::reserveStock()` for all line items.
- [ ] Update UI to show "Stock Reserved" status.

### Step 6 - Phase C: Fulfillment & COGS Tracking
- [ ] Create `POST /api/sales-orders/{id}/fulfill` endpoint.
- [ ] Logic:
    - Call `StockService::releaseReservation()` to clear the reservation.
    - Call `StockService::recordMovement()` (type: Issue) to physically remove stock and compute COGS using FIFO/LIFO.
- [ ] Close the Sales Order status to "Fulfilled" or "Closed".

### Step 7 - UI Polish & Intelligence
- [ ] Add "Low Stock" indicators directly in the Sales Order form.
- [ ] Add "Customer Credit Limit" warnings during order entry.
- [ ] Final visual audit of the Sales process flow.

## Verification Plan

### Automated Tests
- [ ] `SalesOrderQuotationTest`: Verify SO creation and financial math.
- [ ] `SalesOrderLifecycleTest`: Verify transition from Quotation → Confirmed (Stock Reservation) → Fulfilled (Inventory Move + COGS).

### Manual Verification
- Create a quote for a customer.
- Confirm the quote → check `inventories.reserved_qty` in the Inventory Center.
- Fulfill the order → check `inventories.quantity_on_hand` and verify a transaction record was created.
