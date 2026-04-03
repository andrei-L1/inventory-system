> [!WARNING]
> **DEPRECATED** — This file is an older duplicate of [`DOCS.md`](./DOCS.md), which is the canonical technical reference. Please use `DOCS.md` going forward. This file is retained only for historical reference and will be removed in a future cleanup pass.

---

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

## 3. Core Architecture & Workflow
The system uses a **Service-Based Architecture** for all inventory movements.

### A. Inventory Engine
- **Service**: `App\Services\Inventory\StockService`
- **Validation**: `App\Services\Inventory\TransactionValidator`
- **Strategy**: Pessimistic Locking + Database Transactions to ensure 100% data integrity.

### B. Costing Engine
- Supports **FIFO**, **LIFO**, and **Weighted Average** methods.
- Logic is managed via `inventory_cost_layers`, enabling precise margin tracking even when prices fluctuate.

### C. Data Traceability
- **Audit Logs**: Every change to master data is logged.
- **Transaction History**: Every stock movement has a header and detailed lines.
- **Soft Deletes**: Used across products, orders, and vendors to preserve historical references.

## 4. Module breakdown
- **Master Data**: `products`, `categories`, `users`, `customers`, `vendors`.
- **Reference Data**: Lookup tables for statuses and types.
- **Operational Data**: `transactions`, `sales_orders`, `purchase_orders`, `shipments`.

---
*Last Updated: 2026-03-27*
