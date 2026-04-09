# Nexus Inventory System: User Guide (v1.0)

Welcome to the **Nexus Inventory System**, an enterprise-grade platform built on modern technologies to handle your stock logic effortlessly. This guide covers all currently implemented and active modules according to the project roadmap. 

---

## 1. System Access & Authentication
The platform provides a secure and accelerated login gateway.
* **Credentials:** Enter your administrative Email Address and Password to sign in.
* **Google OAuth:** Alternatively, securely sign in via Single Sign-On (SSO) using your Google Workspace/Gmail account by clicking the "Sign in with Google" button.

> [!NOTE]
> *User role management and registration are scheduled for a future development phase. Currently, only seeded administrative accounts exist.*

---

## 2. Command Center (Dashboard)
The Dashboard serves as the central control node of your warehouse, giving you a top-level telemetry view of your entire inventory status at a glance.

* **KPI Modules:** Track your top-line statistics instantly, such as `Assets Managed`, total financial `Capital Deployed` (your total inventory value calculated via the product's assigned costing method: FIFO, LIFO, or Weighted Average), and `Today's Movements`.
* **Live Activity Feed:** Keep a pulse on all physical operations. Every single stock transaction (receipts, issues, transfers, or adjustments) appears here immediately as they are posted.
* **Critical Low Stock Alerts:** The right-hand intelligence panel prominently monitors items whose current quantity on hand falls below their designated `Reorder Point`, and highlights the exact amount of the shortage. 

---

## 3. Master Data Hub
Before you move physical stock, the system requires foundational data.

### The Catalog (Product Management)
Navigate to **Catalog** via the left-hand navigation sidebar to manage what items technically exist in your database.
* **Listing Details:** Here you define the properties of items: Name, SKU code, Reorder Point, Unit Options (UOM), and the **Costing Method** (FIFO, LIFO, or Weighted Average).
* **Categories:** Add detailed attributes by assigning items to groups like `Raw Materials`, `Components`, or `Finished Goods`.

### 3.1. Advanced Units of Measure (UOM)
The system features a **Contextual Counting Engine** that supports complex packaging rules tailored to individual products.

*   **Product-Specific Rules**: You can define custom conversion rules directly on a product (e.g., "Box of 12" for SKU-A) in the Catalog. 
*   **Custom Badges**: When drafting a Purchase Order or Sales Order, units that are governed by product-specific rules are highlighted with a pink **[CUSTOM]** badge in the dropdown.
*   **Atomic Breakdowns**: To prevent purchasing errors, the system displays the "Base Unit" equivalent (e.g., `= 12 pcs`) directly underneath the selected unit in all transaction forms.
*   **Filtered Selection**: The system intelligently filters UOM dropdowns. You will only see units that have a valid mathematical conversion path for the specific product you are transacting.

### Vendor Center
Navigate to **Vendors** to manage your supply chain partners.
* **Entity Records:** Register suppliers by assigning Vendor Codes, Phone Numbers, and physical address details. 
* **Transaction History:** On the right-hand panel of the Vendor Center, you will see a unified list of every inbound receipt provided by that vendor over time.

### Unit of Measure (UOM) Configuration
Navigate to **Settings > Configurations** to oversee how stock is technically counted.
* Allows you to manage standard base metrics (like `Pieces`, `Kilograms`, or `Liters`) and set mathematical conversion ratios.

---

## 4. Core Inventory Operations
This is the core engine where actual warehouse movements take effect.

### 4.1. Location Center
Navigate here to model the physical topology of your facility.
* **Zones & Bins:** Define Warehouse zones (e.g., `Main Warehouse`, `Cold Storage`) and exact Bin locations (`Aisle 4, Shelf B`). 
* *Inventory can only be received into active location bins.*

### 4.2. Stock Movements
When stock shifts physically or financially, you must log a transaction document. Navigate from the `Inventory Center` by selecting an item and clicking the respective Action tool:
* **Receipt (Inbound):** Brings new stock into a location from a Vendor. Increases global quantity and updates cost layers. Supports real-time "Cost-to-Unit" scaling (e.g., buying in Boxes handles the price-per-piece math automatically).
* **Issue (Outbound):** Removes stock from a location (e.g., for manufacturing or sales). Deducts from FIFO cost layers.
* **Transfer (Internal):** Moves stock seamlessly from Bin A to Bin B without affecting global quantity or cost valuation.
* **Adjustment (Reconciliation):** Adds or subtracts items manually to account for shrinkage, damage, audit corrections, or discovered stock. 

> [!TIP]
> **Multi-Unit Transactions**: You can receive or issue stock in any valid UOM (Boxes, Cases, Pieces). The system's Atomic Storage Model will automatically convert the quantity into the base unit before writing to the ledger, ensuring 100% mathematical integrity.

> [!IMPORTANT]
> *Transactions use an immutable ledger. Once a movement is successfully posted, it permanently records time, user, and financial cost. Alert toasts will notify you of success or constraints (like trying to issue more stock than exists).*

### 4.3. Inventory Center
The deepest analytical view available in the application for an individual asset. 
* **Search & Select:** Find a specific product via the left lookup bar.
* **Intelligence Grid:** Reviews the current status of that item, breaking down exactly where it physically sits across all locations (`Storage Breakdown`).
* **Cost Layers:** Shows the remaining undepleted batches of that stock, their specific unit costs, and the original receipt dates. For Weighted Average products, all active layers are automatically synchronized to the current moving average cost.
* **Entity Ledger:** The unified transactional ledger showing every time this specific component was touched, where it went, and who authorized it.

---

## 5. Hyper-Navigation Shortcuts
The interface is designed for rapid traversal across modules so you don't have to use the sidebar.

1. **Dashboard to Inventory:** Click any "Critical Low Stock" alert to jump immediately to its Inventory profile.
2. **Catalog to Inventory:** Click *anywhere* on a product row within the Catalog module to instantly boot up its physical Inventory data and begin transacting.
3. **Inventory to Vendor:** When inspecting transaction history inside the Inventory Center, clicking the highlighted name of the Supplier under the `Entity / Vendor` column will cross-link you straight into the Vendor Center specifically loaded onto that partner's profile!
