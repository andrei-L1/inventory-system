# 📦 Inventory System: Enterprise-Grade Capability Map

This document serves as the **Complete System Capability Map**, detailing all features, workflows, and technical safeguards designed into the platform—including core engine features currently live, and planned capabilities that complete the full ERP lifecycle.

---

## 🏗️ 1. Multi-Warehouse & Location Management
*Focus: Full topological visibility across a distributed logistics network.*

- **Infinite Tier Hierarchies**: Define limitless location depth (e.g., `Region` > `Warehouse` > `Zone` > `Aisle` > `Bay` > `Bin`).
- **Location Typology**: Classify nodes by type (`Warehouse`, `Retail Store`, `Transit Vehicle`, `Virtual/Quarantine`).
- **Granular Stock Visibility**: Real-time querying of "Exactly how many units of SKU-A are in Bin B?" vs. "How many in the whole region?".
- **Atomic Transfers**: Move stock between any two locations with cryptographic-level certainty. The system locks both locations; if the transfer fails mid-flight, the database completely rolls back. Zero lost stock.

---

## 💰 2. Advanced Costing Engine (Financial Integrity)
*Focus: Professional-grade inventory valuation and margin analysis.*

- **Triple-Method Costing**: Set valuation algorithms at the **Product Level**.
  - **FIFO (First-In, First-Out)**: Consumes the oldest cost layers first. Perfect for standard retail.
  - **LIFO (Last-In, First-Out)**: Consumes the newest cost layers first. Useful in specialized commodities.
  - **Weighted Average Cost (WAC)**: Creates a smoothed, moving average on every single inbound receipt.
- **Physical Cost Layers**: The system doesn't just calculate costs dynamically; it physically persists `InventoryCostLayers` in the database. You can drill down into a product and see exactly what receipts make up your current on-hand value.
- **Cost of Goods Sold (COGS) Tracing**: Every outbound sale links directly back to the specific cost layer it consumed, guaranteeing 100% accurate profit margins.

---

## 🛡️ 3. Transactional Safety & "Zero-Loss" Engine
*Focus: Mathematical certainty and concurrency protection.*

- **Pessimistic Row Locking**: During an issue, the system places a hardware-level lock (`lockForUpdate`) on the specific inventory row. If 500 users try to buy the last unit simultaneously, exactly 1 will succeed. No "Ghost Stock".
- **Strict Over-Issue Prevention**: Throws an `InsufficientStockException` if out-of-stock. The system physically prevents Negative Inventory, eliminating the #1 cause of ERP database corruption.
- **Immutable Ledger**: The `transactions` and `transaction_lines` tables act as an append-only ledger. You cannot edit a stock movement once posted—you can only issue a reversing transaction.

---

## 📦 4. Procurement & Inbound Operations (Purchase Orders)
*Focus: Streamlining vendor relations and stock receiving.*

- **Full PO Lifecycle**: `Draft` ➔ `Approved` ➔ `Sent` ➔ `Partially Received` ➔ `Closed`.
- **Intelligent Replenishment**: The system automatically generates PO suggestions based on `reorder_point` and `reorder_rules`.
- **Integrated Goods Receipt (GRN)**: Clicking "Receive" on a PO automatically triggers the `StockService` to increase inventory, create a receipt transaction, update cost layers, and advance the PO status.
- **Vendor Scorecarding**: Track average lead times and historical pricing per vendor.

---

## 🛍️ 5. Sales & Outbound Fulfillment (Sales Orders)
*Focus: Quote-to-cash workflows and order picking.*

- **Full SO Lifecycle**: `Quotation` ➔ `Confirmed` ➔ `Picked` ➔ `Shipped` ➔ `Invoiced` ➔ `Closed`.
- **Real-Time Stock Allocation**: Visually exposes available stock during quotation creation to prevent promising out-of-stock items.
- **Integrated Fulfillment**: Marking an order "Shipped" automatically triggers the `StockService` to issue stock, consume cost layers, and lock the margin.

---

## 🕵️ 6. Traceability & Product Intelligence
*Focus: Specialized item management for complex goods.*

- **Serial Number Tracking (Cradle-to-Grave)**: 
  - Trace specific, unique units (e.g., IMEI numbers or MAC addresses) from vendor receipt, through the warehouse, out to the specific customer.
- **Batch & Expiration Tracking**: 
  - Group stock by production lot. Enable rapid product recalls and implement FEFO (First-Expired, First-Out) picking strategies.
- **UOM (Unit of Measure) Conversions**: 
  - Buy in `Pallets`, store in `Cases`, sell in `Pieces`. The system handles the multi-tiered fractional math seamlessly under the hood without losing a cent of cost precision.

---

## 🏷️ 7. Dynamic Pricing & Discounting
*Focus: B2B/B2C flexible financial models.*

- **Customer Price Lists**: Assign specialized pricing matrices to specific customers or wholesale groups overriding default MSRPs.
- **Volume & Tiered Discounting**: Construct automated rules ("Buy 10, get 5% off").
- **Real-Time Profit Gates**: Prevent sales reps from offering discounts that dip below the current FIFO cost basis.

---

## 🚚 8. Logistics & Shipping
*Focus: Getting the product to the destination.*

- **Multi-Shipment Orders**: Fulfill a single large Sales Order via multiple staggered shipments.
- **Carrier Management**: Track waybills, tracking numbers, and integrated carrier APIs.

---

## 📊 9. Reporting & Analytics (Business Intelligence)
*Focus: Extracting actionable data from the ledger.*

- **Live Inventory Valuation**: Exact dollar value of your warehouse based on remaining unexhausted cost layers.
- **Theoretical vs. Physical Variance**: Worksheets for cycle counts and stock-takes.
- **Gross Margin Analysis**: Real-time ROI per order, product, or customer.
- **Aging & Dead Stock Reports**: Identify capital tied up in slow-moving items to trigger liquidation sales.

---

## 🔐 10. Security, Audit & Multi-Tenancy
*Focus: Enterprise-grade compliance and access control.*

- **Granular Role-Based Access Control (RBAC)**: Fine-grained permissions (e.g., `can('approve-po')`, `can('adjust-stock')`) dynamically applied via middleware.
- **Complete Audit Trail**: Every insert, update, or soft-delete is logged against a User ID and Timestamp. `Activity logs` provide an airtight history of configuration changes.
- **Soft Deletions**: Deleting a product, vendor, or customer only hides it. It remains fully intact in the database forever so historical transaction reports never break.
- **Stateless API Architecture**: Built around Laravel Sanctum tokens, allowing for easy expansion into mobile apps, barcode scanners, or third-party EDI integrations.

---
*Last Updated: 2026-03-28. Covers complete designed feature set.*
