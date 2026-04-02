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

## 2. Costing Logic (FIFO / LIFO / Weighted Average)

This system uses a **Layered Costing Engine**. Even Weighted Average products consume cost layers using FIFO ordering on issues. This design prioritizes ledger-layer consistency and auditability over a pure perpetual average calculation, ensuring that the physical stock balance always matches the sum of the valuation tiers.

### A. Inventory Cost Layers
Every "Receipt" (Inbound) creates a record in `inventory_cost_layers`.
- `received_qty`: Original quantity.
- `issued_qty`: How much has been used/sold so far.
- `remaining_qty`: Auto-calculated (Received - Issued).

### B. Consumption Workflow
When an "Issue" (Outbound) occurs:
1. The service identifies the selected `costing_method` for the product.
2. It fetches active layers sorted by `receipt_date` (**ASC** for FIFO and Weighted Average, **DESC** for LIFO).
3. It iterates through layers, incrementing `issued_qty` until the total requested quantity is satisfied.
4. The transaction line records the weighted-average cost of the specific layers consumed as the true COGS.

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

---

## 5. Atomic Storage Model (The Piece Ledger)

To maintain 100% mathematical integrity and eliminate rounding errors common in traditional ERP systems, this system uses an **Atomic Storage** model.

### A. The "Piece" Standard
- All inventory quantities in the database (`inventories`, `inventory_cost_layers`, `transaction_lines`) are stored as **raw integers** representing the absolute smallest unit (e.g., "Pieces").
- Even if a user transacts in "Boxes", "Cases", or "Pallets", the `StockService` calculates the equivalent "Pieces" using the UOM conversion tree before writing to the database.

### B. Scaled Display Logic
Because users rarely want to see "4800 Pieces" when they actually have "200 Boxes", the system uses **Display Scaling**:
- Models (`Product` and `Inventory`) use `scaled_` attributes (e.g., `$inventory->scaled_quantity_on_hand`) to convert raw integers back into the product's preferred unit for the UI.
- Calculation: `Raw Pieces / (Multiplier to Smallest) = Scaled Quantity`.

### C. Financial Precision
- **Atomic Costing**: Unit costs are similarly normalized. If a Box of 10 costs $100, the ledger records a unit cost of $10 per "Piece".
- **Zero-Loss Reversals**: By using integers for the base ledger, the system avoids floating-point drift, ensuring that reversing a "Box" movement always returns exactly the same "Piece" count to the shelves.
