# Inventory System Architecture & Logic

This document details the core logic governing stock movements, costing, and data integrity.

## 1. The Inventory Engine (StockService)

The heart of the system is `App\Services\Inventory\StockService`. It handles all physical stock movements to ensure that the balance in the `inventories` table always matches the sum of transactions.

### A. The "Atomic Posting" Rule
Every stock movement must be wrapped in a database transaction.
- **Header**: Creates a `Transaction` (The "Why").
- **Lines**: Creates `TransactionLine` records (The "What").
- **Inventory Update**: Updates/Creates rows in the `inventories` table.
- **Costing**: Creates or consumes `InventoryCostLayer` records.

### B. Concurrency Control
We use **Pessimistic Locking** (`lockForUpdate()`) on the `inventories` table row during posting. This prevents "Race Conditions" where two simultaneous sales might deduct stock from the same balance before it can be updated.

---

## 2. Costing Logic (FIFO/LIFO/Average)

The system supports multiple costing methods defined at the **Product** level.

### A. Inventory Cost Layers
Every "Receipt" (Inbound) creates a record in `inventory_cost_layers`.
- `received_qty`: Original quantity.
- `issued_qty`: How much has been used/sold so far.
- `remaining_qty`: Auto-calculated (Received - Issued).

### B. Consumption Workflow
When an "Issue" (Outbound) occurs:
1. The service identifies the selected `costing_method` for the product.
2. It fetches active layers sorted by `receipt_date` (**ASC** for FIFO, **DESC** for LIFO).
3. It iterates through layers, incrementing `issued_qty` until the total requested quantity is satisfied.

---

## 3. Data Life Cycle

| Stage | Action | Database Influence |
| :--- | :--- | :--- |
| **Drafting** | Creating a PO or SO. | No inventory change. |
| **Posting** | Receiving or Shipping goods. | `inventories` + `transactions` + `cost_layers`. |
| **Adjusting** | Correcting errors / Shrinkage. | `transactions` (Type: ADJS). |
| **Closing** | Completing the Order. | Status change only. |

## 4. Relationship Map

- **Product** has many **Inventories** (one per location).
- **Transaction** has many **TransactionLines**.
- **TransactionLine** belongs to a **Product** and a **Location**.
- **InventoryCostLayer** is linked to the **TransactionLine** that created it.
