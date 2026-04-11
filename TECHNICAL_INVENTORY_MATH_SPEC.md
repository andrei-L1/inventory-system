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

To keep the system user-friendly, we "mask" the precision in the interface:
1.  **Money**: Always displayed as 2 decimals (e.g., ₱10.50) using `Intl.NumberFormat`.
2.  **Quantities**: 
    *   **Discrete (Pieces)**: Shown as whole numbers.
    *   **Continuous (Kilograms/Liters)**: Shown as up to 4 decimals in most views.
3.  **The Master View**: The **Inventory Center** exposes the full average cost to ensure transparency for managers.

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
