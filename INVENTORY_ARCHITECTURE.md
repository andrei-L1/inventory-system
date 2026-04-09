# Inventory System Architecture & Logic

This document details the core logic governing stock movements, costing, and data integrity.


## THE STRATEGY PATTERN

The Formal Definition

Strategy Pattern is a behavioral design pattern that lets you define a family of algorithms, encapsulate each one, and make them interchangeable at runtime.

Implementation in this project

## 2. The Costing Engine (Strategy Pattern)

The system delegates valuation logic to a family of interchangeable algorithms using the **Strategy Pattern**. This ensures that physical stock movement remains decoupled from the specific mathematical rules governing a product's value.

### A. Core Architecture
- **Context (`StockService`)**: The orchestrator that coordinates physical movement and locks.
- **Strategy Interface (`CostingStrategy`)**: Defines the required hooks for `onReceipt` and `onIssue`.
- **Factory (`CostingStrategyFactory`)**: Resolves the correct strategy based on the product's `costing_method_id` (FIFO, LIFO, or Weighted Average).

### B. Concrete Strategies

| Strategy | Methodology | `onIssue` Logic | `onReceipt` Logic |
|---|---|---|---|
| **FIFO** | First-In, First-Out | Consumes oldest layers (`receipt_date` ASC) | Updates Running Average + Creates Layer |
| **LIFO** | Last-In, First-Out | Consumes newest layers (`receipt_date` DESC) | Updates Running Average + Creates Layer |
| **Average** | Weighted Average | Consumes layers in FIFO order (all layers identical) | Updates Running Average + Creates Layer + **Levels Layers** |

### C. The "Layer Leveling" Worker
For Weighted Average Costing, the system implements a specialized mechanism called **Layer Leveling**:
1. When a receipt occurs, the **Running Average** is recalculated globally for that inventory record.
2. All existing (non-exhausted) `inventory_cost_layers` are then **leveled** — their `unit_cost` is updated to match the new global average.
3. This ensures that any subsequent issue (Sales Order, Adjustment) correctly reflects the perpetual average value regardless of which underlying layer is consumed.

### D. The Financial Invariant
The system enforces a strict mathematical rule across all movement types:
> **`Inventory.average_cost × Inventory.quantity_on_hand == SUM(InventoryCostLayers.remaining_qty × InventoryCostLayers.unit_cost)`**

This invariant is verified by the automated test suite on every commit to prevent "valuation drift."

---

## 3. The Inventory Engine (StockService)

### B. Concurrency Control — Pessimistic Locking (`lockForUpdate`)
The system uses **pessimistic row locking** at six distinct layers to prevent race conditions across all concurrent request scenarios. Every lock is held inside a `DB::transaction`, so if any step fails the entire operation rolls back atomically.

| Lock Target | Method | Protection |
|---|---|---|
| `inventories` row | `applyLineToInventory()` | Prevents two concurrent issues from reading the same QOH before either write completes ("ghost stock") |
| `inventories` row (new) | Re-fetched with lock after `create()` | Ensures newly inserted inventory rows are immediately serialised for the creating request |
| `inventory_cost_layers` rows | `consumeLayers()` | Locks all non-exhausted layers for the product+location so FIFO/LIFO consumption is serialised |
| `Transaction` header row | `postTransaction()` | Makes the "already posted?" idempotency check and the inventory write atomic — prevents double-posting |
| `Transaction` header row | `reverseTransaction()` | Makes the "is it posted?" check and the counter-transaction write atomic — prevents double-reversal |
| `PurchaseOrder` header row | `approve / send / markAsShipped / close` | Each status transition re-fetches and locks the PO row so concurrent API calls cannot double-transition |
| `PurchaseOrderLine` rows | `receive() / processReturn()` | Locks all PO lines before reading `received_qty` — prevents two concurrent GRNs from beating the over-receipt guard |


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

### C. Contextual Scaling (Product-Aware Rules)
To support diverse packaging across the catalog, the UOM engine is **Context-Aware**:
- **Rule Prioritization**: Lookups prioritize product-specific rules (e.g., "Box of 12" for SKU-A) over global defaults (e.g., "Box of 24" as a system-wide standard).
- **Isolation**: This allows generic unit names like "Box" or "Carton" to coexist with different mathematical values for different products without data contamination.
- **Backend Enforcement**: Controllers for Sales, Procurement, and Logistics strictly inject the `product_id` context into all scaling operations to ensure ledger integrity.

### D. Financial Precision
- **Atomic Costing**: Unit costs are similarly normalized. If a Box of 10 costs $100, the ledger records a unit cost of $10 per "Piece".
- **Zero-Loss Reversals**: By using integers for the base ledger, the system avoids floating-point drift, ensuring that reversing a "Box" movement always returns exactly the same "Piece" count to the shelves.
