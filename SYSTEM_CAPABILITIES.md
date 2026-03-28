# 📦 Inventory System: Enterprise-Grade Capability Map

This document serves as a specialized feature guide, detailing the advanced business logic and technical safeguards currently active in the **Phase 0 Inventory Engine**.

---

## 🏗️ 1. Multi-Dimensional Inventory Tracking
*Focus: Full visibility across a distributed warehouse network.*

- **Hierarchical Location Logic**:
  - **What it is**: The ability to organize stock into a parent-child structure: `Warehouse` > `Zone` > `Bin/Aisle`.
  - **The Benefit**: You don't just know you have "10 Pencils"—you know you have **"10 Pencils in Warehouse A, Zone 1, Aisle 4."**
- **Real-Time On-Hand Intelligence**:
  - **The Rule**: The `inventories` table acts as a high-speed cache for the current balance, while the `transactions` table stores the non-editable proof.
  - **The Math**: `On-Hand = Σ(Receipts) - Σ(Issues)`.

---

## 💰 2. Advanced Costing Engine (FIFO / LIFO / Average)
*Focus: Professional-grade financial inventory valuation.*

The system supports the three major accounting methods, selectable at the **Product Level**.

### A. FIFO (First-In, First-Out)
- **The Behavior**: The system identifies the oldest "Layer" of stock and exhausts it first.
- **Example Scenario**:
  - *Day 1*: Receive 10 units at **$10**.
  - *Day 2*: Receive 10 units at **$15**.
  - *Sale*: You sell 12 units.
  - **System Result**: It deducts **10 at $10** and **2 at $15**. Your remaining stock value is accurately $120.

### B. LIFO (Last-In, First-Out)
- **The Behavior**: The system consumes the most recently received stock first.
- **Why it matters**: Crucial for specific industries where newer inventory is more accessible or expensive (e.g., coal or specific chemicals).

### C. Weighted Average Cost (WAC)
- **The Behavior**: Every new receipt triggers an automatic recalculation of the SKU's moving average cost.
- **The Formula**: `(Value_on_Hand + New_Inbound_Value) / Total_Quantity`.
- **The Benefit**: Provides a stable, smoothed-out cost basis for margin reporting.

---

## 🛡️ 3. "Zero-Loss" Transaction Integrity
*Focus: Preventing data errors before they happen.*

- **Atomic Multi-Location Transfers**:
  - **The Safety Rule**: A transfer is a "Single Point of Failure" operation. The system locks **both** the source and destination locations simultaneously.
  - **The Result**: If the server crashes MID-TRANSFER, the database rolls back everything. You will **never** have stock that "disappeared in transit."
- **Pessimistic Row Locking**:
  - **The Guard**: During a sale or issue, the system places a "Hardware Lock" on the specific stock record.
  - **The Benefit**: If 10 people try to buy the last unit at the same time, only the FIRST person succeeds. No "Ghost Stock" or over-selling.
- **Out-of-Stock Protection**:
  - **The Policy**: Throws an `InsufficientStockException` if a user attempts to issue stock that doesn't have an associated cost layer. This prevents "Negative Inventory" which is the #1 cause of accounting failures.

---

## 🕵️ 4. Traceability & Product Compliance
*Focus: Specialized item management for high-value or regulated goods.*

- **Level 1: Serial Number Tracking (Cradle-to-Grave)**:
  - Tracks individual items by a unique identifier (e.g., iPhone IMEI).
  - You can track **exactly** which specific unit was sold to which customer.
- **Level 2: Batch & Lot Management**:
  - Grouping stock by production batch or expiration date.
  - Allows for "Recall" operations (finding all units from a specific bad batch).
- **Level 3: Units of Measure (UOM) Intelligence**:
  - Supports multiple units per SKU via precision conversion factors.
  - **Example**: Receive stock in **Pallets**, store in **Cases**, and sell in individual **Pieces**. The math is handled automatically (8 decimal place precision).

---

## 🌩️ 5. Master Data & API Gateway (Product Catalog)
*Focus: Secure, high-performance data access for the frontend.*

- **RESTful Master Data CRUD**:
  - Complete API lifecycle management for **Products**, **Categories**, **Vendors**, and **Units of Measure**.
  - Standardized JSON responses via **Laravel API Resources**, ensuring the frontend always receives clean, structured data.
- **Smart Catalog Filtering**:
  - High-performance search allowing users to filter by **SKU**, **Name**, **Product Code**, and **Category ID**.
  - Optimized pagination to handle catalogs with thousands of SKUs without performance degradation.
- **Automatic Inventory "Auto-Sync"**:
  - **The Safety Rule**: On product creation, the system automatically initializes zero-stock `Inventory` rows for all active locations (**Warehouse A**, **Zone 1**, etc.).
  - **The Result**: A new SKU is ready to receive stock at every warehouse the millisecond it is born, preventing "Ghost SKU" errors in the stock engine.

---

## 📊 6. Audit & Compliance Baseline
- **Immutable Transaction History**: Physical stock movements are permanent records. Deleting a SKU does not remove its history.
- **Soft Deleting**: Master data (Vendors, Customers, Products) are "soft-deleted"—they are archived but can be restored instantly, preserving all historical links.
- **Role-Based Security Layer**: Pre-built permission groups (Admin, Staff, User) ensure only authorized people can approve receipts or perform transfers.

---
*Last Updated: 2026-03-28 08:38:00*
