# Technical Documentation: Inventory Management System (ERP Core)

This document provides a comprehensive overview of the technical architecture, implementation details, and development standards for the inventory management system.

## 1. Project Overview
The system is a production-ready ERP-grade inventory management solution built with Laravel. It supports end-to-end supply chain workflows, from automated replenishment and purchasing to advanced sales and logistics tracking.

## 2. Tech Stack
- **Framework**: Laravel 12.x
- **Language**: PHP 8.2+
- **Database**: MySQL/MariaDB (48 Tables)
- **Frontend**: Vue.js / Inertia.js (Vite-based)
- **Tooling**: Composer, NPM, Git

## 3. Installation & Setup
1. **Repository**: Clone the repository and switch to the development branch:
   ```bash
   git checkout feat/inventory-standardization
   ```
2. **Backend Dependencies**:
   ```bash
   composer install
   ```
3. **Frontend Dependencies**:
   ```bash
   npm ci
   npm run build
   ```
4. **Environment**: Configure `.env` with database credentials and run migrations:
   ```bash
   php artisan migrate
   php artisan db:seed --class=SalesOrderStatusSeeder
   ```

## 4. Module Architecture

### A. Core Inventory & Costing
- **Costing Engine**: Supports FIFO, LIFO, and Average Costing via `inventory_cost_layers`.
- **Traceability**: Multi-level tracking via **Batch/Lot Numbers** and **Serial Numbers**.
- **Unit Management**: Support for base units and **UOM Conversions** (e.g., 1 Box = 12 Pieces).

### B. Procurement (Purchasing)
- **Vendors**: Comprehensive supplier management.
- **Purchase Orders**: Full PO lifecycle tracking (Draft -> Approved -> Received).
- **Replenishment**: Automated reorder points and demand-based suggestions.

### C. Sales & Distribution
- **Customers**: Comprehensive buyer management with credit limits.
- **Pricing Strategy**: Multi-tiered **Price Lists** and **Discount Rules** (Product, Category, or Customer-specific).
- **Sales Orders**: Full order lifecycle (Draft -> Approved -> Processing -> Shipped).

### D. Logistics & Traceability
- **Shipments**: Integration with multiple **Carriers** and real-time tracking numbers.
- **Packaging**: Support for multi-package shipments with weights and dimensions.
- **Audit Logs**: Full traceability of all data changes and system activities via `audit_logs` and `activity_logs`.

## 5. Database Schema (Summary)
The database contains **48 tables**, highly normalized and strictly constrained with foreign keys to ensure data integrity.

- **Master Data**: `products`, `categories`, `users`, `roles`, `customers`, `vendors`.
- **Reference Data**: `location_types`, `transaction_types`, `transaction_statuses`, `sales_order_statuses`, `purchase_order_statuses`.
- **Operational Data**: `transactions`, `sales_orders`, `purchase_orders`, `shipments`.

## 6. Git Workflow
The standardization and ERP enhancements are contained within the `feat/inventory-standardization` branch, following a clean feature-branch development model.

---
*Last Updated: 2026-03-27*
