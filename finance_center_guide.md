# Finance Center User Guide

Welcome to the **Finance Center**! This guide covers the full lifecycle of your company's cash management, from customer receipts to vendor settlements.

## 🧭 Navigation: Switch Between Receivable & Payable
The Finance Center operates in two primary modes:
1.  **Cyan Mode (Receivables)**: Managed via the **"Invoices & Credit Notes"** tab. Handles money coming IN from customers.
2.  **Amber Mode (Payables)**: Managed via the **"Vendor Bills & Settlements"** tab. Handles money going OUT to vendors.

---

## 🧾 Part 1: Accounts Payable (A/P) - NEW

### 1.1 Generating a Vendor Bill
To ensure financial integrity, bills are strictly tied to physical receipts in the warehouse.

1.  Navigate to **Vendor Bills & Settlements**.
2.  Click **New Vendor Bill**.
3.  Select a **Purchase Order (PO)**.
4.  The system will pre-fill all lines based on what has been **Received** but not yet **Billed**.
5.  **Understanding "Pieces"**: To prevent rounding errors, the system standardizes all billing data to the product's base unit (Pieces). 
    - *Example*: If you received 1 Box of 12, you will see `12 pieces`.
    - *Label*: Look for the context label (e.g., `≈ 1 Box`) to verify against your vendor's physical invoice.
6.  Click **Create Draft Bill**.

### 1.2 Recording a Disbursement (Payment out)
Once a bill is `POSTED`, you can record a payment to settle it.

1.  Click **Record Disbursement**.
2.  Select the **Vendor**. The system will instantly fetch all their unpaid bills.
3.  Enter the **Total Amount Paid** and the **Payment Method** (Check, Cash, etc.).
4.  **Auto-Allocate**: Click this button to automatically apply the funds from the oldest bill to the newest.
5.  **Confirm**: Once the "Remaining" balance is zero, click **Confirm Disbursement**.

---

## 💰 Part 2: Accounts Receivable (A/R)

### 2.1 Generating a Customer Invoice
Invoices are coupled to warehouse shipments to prevent billing for goods not yet delivered.

1.  Click **New Invoice**.
2.  Select a **Sales Order (SO)**. 
3.  The system displays lines where items have been **Shipped**.
4.  Enter the `Invoice Qty` and click **Generate Draft Invoice**.

### 2.3 Receiving Payments (Money In)
1.  Click **Record Payment**.
2.  Select the **Customer** and enter the **Total Amount Received**.
3.  Allocate the funds across their open invoices using the "Apply Amount" column.

---

## 🛡️ Financial Safeguards
- **BCMath Integrity**: All calculations are performed to 8 decimal places.
- **Strict Matching**: You cannot bill for quantities greater than what the warehouse has physically received.
- **Reference Persistence**: The system remembers if you were in "Payables" or "Receivables" mode as you move between documents.
