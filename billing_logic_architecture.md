# Architecture: Contextual Atomic Billing (Three-Way Match)

This document explains the "Contextual Atomic" model implemented to ensure absolute mathematical integrity across the Procurement-Finance chain.

## 🗝️ The Core Challenge: Unit Drift
When billing against a Purchase Order, the system must reconcile three potentially different units:
1.  **PO Unit**: e.g., "10 Boxes" (Logistical Intent)
2.  **GRN Unit**: e.g., "120 Pieces" (Physical Reality)
3.  **Bill Unit**: e.g., "10 Boxes" (Vendor Invoice)

In "Simple" systems, billing in Boxes leads to hidden rounding errors in the Average Cost (WAC) calculations. 

## 🛡️ The Contextual Atomic Solution
We have standardized the entire financial ledger on the **Base Unit (Pieces)** while preserving the **Logistical Context** in the UI.

### 1. Atomic Standardization (Calculative Truth)
- **Quantities**: All quantities sent to the `BillController` are converted to **Pieces**.
- **Pricing**: All unit prices are normalized to **Price per Piece** using `BCMath` (8-decimal precision).
- **Result**: The "Total Inventory Value" in the ledger will always perfectly match the sum of the physical piece values.

### 2. Contextual UI Bridge (User Experience)
To keep the UI intuitive for accountants, we display the **"Reference UOM"** as labels:
*   Instead of just "120", the UI shows: `120 pcs (equivalent to 10 Boxes)`.
*   This allows the user to cross-reference the vendor's physical invoice while the system processes the "Atomic Truth".

### 3. Backend Scaling (Validation Safeguard)
The backend performs **Real-time UOM Scaling** during validation:
1.  Receives `quantity` in **Pieces**.
2.  Fetches the PO Line's `divisor` (e.g., 12).
3.  Calculates `Pieces / Divisor = Order Unit Qty`.
4.  Validates that `Order Unit Qty ≤ PO Billable Balance`.
5.  **Status**: This prevents over-billing even if the bill is submitted in a different unit than the PO line.

---

## ✅ Best Practices Enforced
- **Zero-Quantity Pruning**: The frontend automatically strips out rows where the billed quantity is `0` (Capped Items) before submission to ensure payload cleanliness and prevent DB-level noise.
- **BCMath Enforcement**: Intermediate scaling calculations are performed at `decimal(18, 8)` to prevent precision truncation during UOM translation.
- **Three-Way Match**: A Bill is biologically tied to the **Receipt ID** and the **PO ID**, ensuring a contiguous audit trail from cash-out back to the original order.
