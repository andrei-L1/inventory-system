# Technical Specification: Inventory Mathematics & Financial Precision
*Version 3.1: Hardened Precision & Dual-Layer UOM Accounting*

## 1. Business Context: Why 8 Decimals?

The best way to understand our 8-decimal standard is the **"High-Resolution Camera"** analogy:

> Even if we only print a final photo at standard size (2 decimals for the invoice), we must capture the image in high resolution (8 decimals for the internal math). 
> 
> If we calculate at only 2 decimals, every single transaction loses a tiny fraction of a cent. Across 100,000 transactions, those lost fractions "bleed" out of the inventory, causing huge discrepancies at the end of the year. **Internal 8-decimal precision ensures that our "Internal Truth" is so sharp that we never lose a single penny to rounding math.**

---

## 2. Executive Summary
This document outlines the "Gold Standard" for inventory valuation and precision used in our system. We employ a **Hybrid Layered Approach**—calculating a stable Weighted Average for the business while maintaining a granular FIFO (First-In, First-Out) audit trail for every physical unit.

---

## 3. Mathematical Models

### 3.1 The Weighted Average Cost (WAC) Formula
Whenever new stock is received at a different price, the system recalculates the "Running Average" for that product at that location.

**The Golden Formula:**
$$ \text{New Average Cost} = \frac{(\text{Current Value}) + (\text{New Receipt Value})}{\text{Total New Quantity}} $$

*   **Logic Source**: [ManagesCostLayers.php](file:///c:/xampp/htdocs/inventory-system/app/Services/Inventory/Costing/Traits/ManagesCostLayers.php)
*   **Trigger**: This calculation fires immediately upon the "Post" status of any stock receipt.

### 3.2 The "Layer Leveling" Mechanism
To ensure financial consistency, we use a hybrid model. While we keep separate "layers" for each receipt (to track age and PO source), we **level** their costs to match the new average.

**The Leveling Logic:**
$$ \text{Active\_Layer}_{n}.\text{Unit\_Cost} = \text{Current\_Running\_Average} $$

*   **Benefit**: This eliminates "price jumps" when selling stock from different shipments while still allowing auditors to trace exactly which PO an item came from.

---

## 4. Precision Engineering

### 4.1 The 8-Decimal Internal Standard
While invoices and payments are rounded to 2 decimals (₱10.50), the "Internal Truth" of the system is tracked at **8 decimal places** (e.g., ₱10.50000000).

*   **Why?**: This prevents "Rounding Bleeding." If you buy 1,000,000 units, a tiny rounding error of ₱0.001 results in a **₱1,000.00** discrepancy. At 8 decimals, that error drops to **₱0.01**.
*   **Database Standard**: All quantity and cost columns use `decimal(18, 8)`.
*   **Migration Reference**: [2026_04_04_...](file:///c:/xampp/htdocs/inventory-system/database/migrations/2026_04_04_000001_upgrade_quantity_precision.php)

### 4.2 The Eradication of PHP Floats and "Epsilon"
 Historically, the system relied on PHP's native floating-point math and "Epsilon" thresholds to handle tiny mathematical dust. **This has been completely eradicated.**
 
 *   **The Problem with Floats**: Native PHP floats suffer from IEEE 754 precision loss (`0.1 + 0.2 = 0.30000000000000004`).
 *   **The BCMath Standard**: All arithmetic operations (Add, Subtract, Multiply, Divide, Compare) are now routed exclusively through the `FinancialMath` wrapper which natively utilizes PHP's `BCMath` extension.
 *   **String Strictness**: All mathematical quantities are explicitly typed and passed as `string`. The system enforces a strict "data boundary" preventing native floats from bleeding into precision-critical accumulator loops. If `FinancialMath` detects a PHP `(float)`, it fires an immediate `InvalidArgumentException`.
 *   **Precision-Aware Comparisons**: All system logic (e.g., "Is this PO fully received?") uses `FinancialMath::cmp()` at a strict scale of 8. This ensures that fractional quantities (like 0.5 Boxes) are never truncated to zero during boundary checks, resolving "Integer Blindness" bugs.

---

## 5. UI & Display Rules

To maintain professional financial standards while preserving mathematical integrity, we use a **Layered Display Strategy**:

1.  **Grand Totals & Headers (2DP)**: Always displayed as 2 decimal places to comply with GAAP and standard currency (PHP ₱) formatting. These are calculated on the backend and emitted via `formatted_` accessors (e.g., `formatted_total_amount`).
2.  **Line Item Unit Costs (8DP)**: Exposes the full resolution in the grid to allow managers to see exact average costs (e.g., `₱10,016.66666667 / pcs`).
3.  **Quantities**: 
    *   **Discrete (Pieces)**: Shown as whole numbers via `formatted_` strings.
    *   **Continuous (Kg/Liters)**: Shown with up to 4-8 decimals depending on the UOM context.
4.  **Tilde (~) Marker**: Used when a unit cost is an average that has been rounded for display, signaling to the user that internal math is more precise than visual presentation.

---

## 6. Developer & Auditor Reference

| Logic Component | Path |
| :--- | :--- |
| **Averaging Algorithm** | `App\Services\Inventory\Costing\Traits\ManagesCostLayers` |
| **Precision Core (BCMath)** | `App\Helpers\FinancialMath` |
| **UOM Scaling Engine** | `App\Helpers\UomHelper` |
| **Schema Definition** | `database/migrations/2026_04_11_...` |

---

## 7. Contextual UOM Scaling (Product-Aware)

A major innovation in the system is the **Contextual Counting Layer**. This allows the same unit abbreviation (e.g., "BX" for Box) to represent different absolute quantities depending on the product context.

### 7.1 The Rule Prioritization
When the system calculates the "Base Multiplier" for a product movement, it follows a strict hierarchy to find the correct math:
1.  **Product-Specific Rule**: Search `uom_conversions` where `from_uom_id = TARGET` AND `product_id = CURRENT_PRODUCT`.
2.  **Global Rule**: If no specific product rule exists, search `uom_conversions` where `from_uom_id = TARGET` AND `product_id IS NULL`.
3.  **Default 1:1**: If no rules are found, the system assumes a direct mapping (for same-unit transactions).

### 7.2 Scaling Invariant
For any transaction involving a custom UOM, the resulting atomic stock change is calculated as:
$$ \Delta \text{AtomicQty} = \text{TransactionQty} \times \text{UomHelper::getMultiplierToSmallest}(\text{uom\_id}, \text{product\_id}) $$

> **Data Isolation**: This mechanism ensures that a "Box of 12" for *Bolt A* never conflicts with a "Box of 50" for *Bolt B*, even though both use the same UOM ID. This prevents global rule pollution and keeps the inventory math surgical.

### 7.3 The Dual-Layer Standard (Commercial vs. Atomic)

The system maintains a rigid separation between **Commercial Contracts** and **Atomic Reality**:

1.  **Atomic Layer (Inventory)**: The `inventories` table and Stock Ledger strictly use the **Base Unit** (e.g., Pieces). This ensures warehouse staff always see the lowest possible denominator of stock.
2.  **Commercial Layer (Orders/Invoices)**: Purchase and Sales Orders use **Selected UOMs** (e.g., Boxes, Pallets). This preserves the integrity of the vendor's invoice and prevents rounding errors when applying unit costs to bulk quantities.

The `FinancialMath` engine bridges these layers at 8-decimal precision, ensuring that "0.5 Boxes" correctly translates to "2 Pieces" without losing fractional value in either direction.

> [!CAUTION]
> **Code Sync Constraint**: Any new calculation (Invoicing, Discounts, Tax) **MUST** use the `FinancialMath` wrapper (`FinancialMath::add`, `FinancialMath::mul`, etc.). Using internal PHP operators (`+`, `-`, `*`) or native `round()` will instantly decouple quantities from the String Boundary and is strictly forbidden.

---

## 8. The Calculator Parity Paradox (Rounding Drift)

A common point of confusion for users is the **"Ghost Cent"** discrepancy between a manual calculator and the system dashboard.

### 8.1 The Phenomenon (The ₱0.04 Problem)
If a user multiplies a rounded display price (e.g., ₱10,016.67) by a quantity (e.g., 12), they may get a result (₱120,200.04) that differs from the system's listed total (₱120,200.00).

### 8.2 The Technical Origin: Rounding Increments
This discrepancy is a mathematically inevitable result of **High-Precision Division**:

1.  **The Transaction**: 12 units bought for a total of **₱120,200.00**.
2.  **The Actual Unit Cost**: $120,200.00 \div 12 = \mathbf{10,016.66666667...}$
3.  **The Display Cost**: To make the number "human-readable," we round it UP to **₱10,016.67**.
4.  **The Multiplier Effect**: When we rounded up, we technically added a "rounding increment" of $\approx 0.0033...$ to each unit. Across 12 units, these tiny increments sum up to exactly **₱0.04**.

### 8.3 Our Financial Standard: "The Honest Truth"
To ensure absolute audit integrity, our system prioritizes **Ledger Truth** over **Calculator Parity**:
*   **Ledger Truth (The Total)**: The "Total Value" displayed always reflects the hard assets in the database (₱120,200.00).
*   **Visual Cues**: To signal that unit costs are averages, we use the **Tilde (~)** prefix (e.g., `~ ₱10,016.67 / pcs`).
*   **Precision Hygiene**: We never use the rounded display price for internal accounting; we always use the the high-resolution 8-decimal string stored in the ledger.

---

## 9. Frontend/Backend Handshake (The String Contract)

To ensure that floating-point errors never "bleed" into the system from the user interface, we enforce a strict data contract between the API and the Vue components.

### 9.1 Backend Emission
The backend (Laravel Resources) acts as the **Precision Authority**. It emits two types of numeric data:
- **Raw Strings**: The absolute 8-decimal value from the database (e.g., `"125.50000000"`).
- **Formatted Strings**: Pre-rounded, human-readable values (e.g., `"125.50"` or `"~ 125.50"`).

### 9.2 Frontend Reception
The frontend (Vue 3) follows the **Explicit Casting** protocol:
- **Direct Display**: Always prefers the backend's `formatted_` fields to avoid frontend-side rounding drift.
- **Visual Arithmetic**: When the frontend must perform real-time math (e.g., subtotaling a draft order), it explicitly casts backend strings using `Number()` at the moment of calculation.
- **Form Submission**: Native `InputNumber` components handle high-precision decimals (up to 8 places), and the resulting values are sent back to the API as clean numbers/strings for the `FinancialMath` engine to process.


---

## 10. The "Water Bottle" Example (Non-Technical Walkthrough)

To visualize how the system handles math without using complex jargon, let's look at a simple scenario:

### **Step 1: Where we start**
You have **10 bottles** of water in your fridge. You bought them for **₱10.00** each.
*   **Total Inventory Value**: 10 bottles times ₱10.00 = **₱100.00**

### **Step 2: Buying more (The New Receipt)**
You buy **1 Box** of water from a premium supplier for **₱150.00** (this includes delivery).
*   **Box Size**: 12 bottles per box.

### **Step 3: How the system does the math**

1.  **Opening the Box**: The system treats the box as individual items immediately.
    *   1 Box becomes **12 new bottles**.
2.  **Finding the exact bottle price**: The system calculates the price of a single bottle from that new box.
    *   ₱150.00 divided by 12 bottles = **₱12.50 per bottle**.
3.  **Mixing the stock (The Average)**:
    *   **Old Bottles (10)**: ₱100.00 value.
    *   **New Bottles (12)**: ₱150.00 value.
    *   **Total Money Spent**: ₱100.00 + ₱150.00 = **₱250.00**.
    *   **Total Bottles owned**: 10 old + 12 new = **22 bottles**.
4.  **The new average price**:
    *   ₱250.00 divided by 22 bottles = **11.36363636...**

### **Step 4: How you see this in the app**

| Mode | What you see | Why? |
| :--- | :--- | :--- |
| **Standard Mode** | `~ ₱11.36 / pcs` | Clean and readable for daily sales. |
| **Audit Mode** | `₱11.36363636 / pcs` | **The Honest Truth.** Shows the exact math. |

**Why the "Honest Truth" matters:**
*   **With 8 decimals**: 22 bottles times 11.36363636 = **₱250.00** (Exactly what you spent).
*   **With 2 decimals**: 22 bottles times 11.36 = **₱249.92** (**₱0.08 is missing!**).

This is why we keep all those extra numbers: to make sure not a single centavo is lost when calculating the value of your warehouse.

---

## 11. Atomic Billing Normalization (A/P Standard)

To maintain 100% parity between your checkbook and your warehouse, the system employs **Atomic Normalization** for all vendor bills.

### 11.1 The Pivot to Pieces
When a vendor sends a bill for "10 Boxes," the system performs an immediate pivot:
1.  **Normalization**: 10 Boxes $\times$ 12 pieces/box = **120 pieces**.
2.  **Price Scaling**: Total Bill Amount / 120 pieces = **Atomic Price per Piece**.
3.  **The Result**: Both the `inventories` table and the `bills` table now share the same "Atomic Language."

### 11.2 The Guardrail Formula (Validation)
To prevent accidental over-billing for products with different conversion factors (e.g., Box of 12 vs Box of 24), the backend re-scales pieces back to the original PO unit during validation:

$$ \text{ValidationQty} = \frac{\text{BilledPieces}}{\text{PO\_Line\_Divisor}} $$

*   **Safety**: If `ValidationQty` > `PO_Line_BillableBalance`, the submission is rejected. 
*   **Precision**: This scaling uses 8-decimal `FinancialMath::div()` to ensure that partial units (e.g., 0.5 Boxes) are validated with absolute mathematical parity. 

---

## 12. Bidirectional Fulfillment Standard (Strategy B)

To support real-time warehouse agility, the system distinguishes between **Gross Record** (the historical log) and **Net Physical Truth** (current state).

### 12.1 The "Net Physical Truth" Counter
Unlike systems that keep fulfillment counters static, Nexus employs **Strategy B (Counter Reversal)**. When a return occurs:
1.  **Sales**: The `shipped_qty` on the Sales Order Line is **decremented**.
2.  **Procurement**: The `received_qty` on the Purchase Order Line is **decremented**.

**Mathematical Benefit**:
This naturally "restores" the requirement. If 10 units were ordered and 2 are returned, the `net_shipped_qty` becomes 8. The system automatically detects that 2 units are now "Outstanding," re-enabling the Pick/Pack/Ship buttons in the UI without manual status overrides.

### 12.2 Return Validation (Headroom Check)
To prevent mathematical "Ghosts" (returning more than was shipped), every return transaction must pass the **Headroom Invariant**:
$$ \text{ProposedReturnQty} \le \text{CurrentNetFulfillmentQty} $$

*   **Logic Source**: `SalesOrderReturnController` and `PurchaseOrderController`.
*   **Precision**: Validated at 8-decimal pieces using `FinancialMath::lte()`.

---

## 13. Financial Document Voids & Status Propagation

The system maintains a tight "Status Handshake" between financial documents (Invoices/Bills) and their parent orders.

### 13.1 Automated Requirement Restoration
When a financial adjustment document (Credit Note / Debit Note / Voided Bill) is processed, the system triggers a **Parent Recalculation**:
1.  **Voiding a Credit Note**: Re-evaluates the Sales Order fulfillment state.
2.  **Voiding a Debit Note**: Re-evaluates the Purchase Order fulfillment state.

### 13.2 The `recalculateStatus()` Invariant
The `SalesOrder` and `PurchaseOrder` models contain a centralized `recalculateStatus()` method. This method is the **Single Source of Truth** for the order lifecycle. It compares:
- `ordered_qty` vs. `net_shipped_qty` (Sales)
- `ordered_qty` vs. `net_received_qty` (Procurement)

If the net fulfillment drops below the ordered goal due to a void or return, the order status **automatically reverts** from `closed` to `partially_shipped` (or `partially_received`), ensuring no requirements are ever forgotten by the warehouse staff.

---
