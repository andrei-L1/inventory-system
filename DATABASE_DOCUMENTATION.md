# Inventory System: Database Architecture Documentation

## 1. Overview
This document outlines the production-grade database architecture for the Inventory Management System. The design prioritizes **data integrity**, **auditability**, and **flexible costing methods** (FIFO, LIFO, Average).

---

## 2. Core Modules

### 2.1 Identity, Access Management & Partners
*   **`roles`**: Defines system access levels (`admin`, `staff`, `user`).
*   **`vendors`**: External partners supplying goods. Tracks contact info, address, and tax ID.
*   **`users`**: Extended profile including `is_active` flag, last login IP, and device tracking.
*   **`sessions`**: Production-ready session storage with parsed device info (`device_type`, `browser`, `platform`) and admin-driven termination support (`is_admin_terminated`).

### 2.2 Product Catalog
*   **`products`**: Master record for items. Stores `product_code`, `sku`, `barcode`, and reorder point/quantity.
*   **`costing_method`**: Per-product selection of `fifo`, `lifo`, or `average`.
*   **`categories`**: Nested categorization support using `parent_id`.
*   **`units_of_measure`**: Standardized units (pcs, kg, bx).
*   **`product_images`**: Support for multiple images with a `primary` flag.

### 2.3 Locations & Inventory Ledger
*   **`locations`**: Hierarchical warehouse management (Warehouse > Zone > Bin).
*   **`inventories`**: The real-time "Stock on Hand" ledger per product-location pair.
*   **`inventory_cost_layers`**: **CRITICAL** table for accounting. Records every receipt of stock with its unit cost. Transactions consume these layers based on the selected costing method.

### 2.4 Transactions & Stock Movement
*   **`transactions`**: High-level stock events (Receipt, Issue, Transfer, Adjustment). Includes status control (`draft`, `pending`, `posted`, `cancelled`).
*   **`transaction_lines`**: Detailed line items including cost and selling price snapshots at the time of the event.

---

## 3. Inventory Costing Methodologies

The system natively supports three major accounting methods:

### Weighted Average (AVCO)
*   **Mechanism**: The system maintain a running average in `products.average_cost` and `inventories.average_cost`.
*   **Calculation**: `(Current Stock Value + New Stock Value) / (Current Qty + New Qty)`.

### First-In, First-Out (FIFO)
*   **Mechanism**: Issues (sales) consume stock from the *oldest* unexhausted `inventory_cost_layers` first.
*   **Benefit**: Most accurate for tax purposes and perishables.

### Last-In, First-Out (LIFO)
*   **Mechanism**: Issues (sales) consume stock from the *newest* unexhausted `inventory_cost_layers` first.
*   **Use-case**: Standard for specific industrial accounting models.

---

## 4. Audit & Reliability

### 4.1 Audit Logs (The "Paper Trail")
*   **`audit_logs`**: An immutable, append-only table.
*   **Content**: Stores `old_values` and `new_values` as JSON strings for every record change.
*   **Context**: captures `user_id`, `ip_address`, `user_agent`, and the request `url`.

### 4.2 Stock Snapshots
*   **`stock_snapshots`**: Captured daily or on-demand.
*   **Purpose**: Provides "Point-in-Time" reporting. You can generate a valuation report for December 31st even if stocks have moved since then.

---

## 5. ER Diagram (Conceptual)

```mermaid
erDiagram
    ROLE ||--o{ USER : "has"
    USER ||--o{ TRANSACTION : "creates"
    VENDOR ||--o{ PRODUCT : "preferred for"
    VENDOR ||--o{ TRANSACTION : "supplies"
    LOCATION ||--o{ LOCATION : "parent of"
    LOCATION ||--o{ INVENTORY : "stores"
    PRODUCT ||--o{ INVENTORY : "tracked in"
    PRODUCT ||--o{ TRANSACTION_LINE : "sold/rcvd"
    PRODUCT ||--o{ INVENTORY_COST_LAYER : "accrues cost"
    TRANSACTION ||--o{ TRANSACTION_LINE : "contains"
    TRANSACTION_LINE ||--o{ INVENTORY_COST_LAYER : "updates"
    AUDIT_LOG }o--|| USER : "logged by"
```

---

## 6. Setup & Maintenance

### Running Migrations
```bash
php artisan migrate --force
```

### Initial Data Setup
```bash
php artisan db:seed --force
```

### Report Generation
*   **Engine**: Reports are defined in the `reports` table.
*   **Execution**: Report generation should be offloaded to queues, with results tracked in `report_runs`.

---

> [!IMPORTANT]
> **Data Integrity Rule**: Never manually edit `inventories` or `inventory_cost_layers`. Always use a `Transaction` entity to ensure the ledger and audit trail remain synchronized.
