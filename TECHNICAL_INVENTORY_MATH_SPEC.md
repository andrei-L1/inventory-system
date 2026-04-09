# Technical Specification: Inventory Mathematics & Financial Precision
*Version 2.1: Enterprise-Grade Accuracy (8-Decimal Standard)*

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

### 4.2 The "Infinity Zero" (Epsilon)
Computers can sometimes have tiny math "dust" (e.g., 0.333333334). To prevent "Ghost Stock" (records of 0.000000001 items that you can't sell), we use a threshold called **Epsilon**.

*   **Threshold**: `0.00000001` ($10^{-8}$)
*   **The Rule**: Any stock quantity smaller than this threshold is mathematically treated as **Zero**.
*   **Trigger**: This is used whenever the system checks if a stock layer is "Exhausted."

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
| **Precision Constants** | `App\Services\Inventory\StockService::QTY_EPSILON` |
| **UOM Scaling Engine** | `App\Helpers\UomHelper` |
| **Schema Definition** | `database/migrations/2026_04_04_000001_upgrade_quantity_precision.php` |

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

> [!IMPORTANT]
> **Data Isolation**: This mechanism ensures that a "Box of 12" for *Bolt A* never conflicts with a "Box of 50" for *Bolt B*, even though both use the same UOM ID. This prevents global rule pollution and keeps the inventory math surgical.

> [!CAUTION]
> **Code Sync Constraint**: Any new calculation (Invoicing, Discounts, Tax) **MUST** be wrapped in `round(..., 8)` in PHP. Failing to do this will cause "Rounding Drift" and corrupt the inventory ledger over time.
