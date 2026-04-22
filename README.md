<p align="center">
  <strong>Nexus Inventory System</strong><br/>
  Enterprise-grade ERP inventory management built on Laravel 12 + Vue 3
</p>

<p align="center">
  <a href="https://github.com/andrei-L1/inventory-system/actions/workflows/ci.yml">
    <img src="https://github.com/andrei-L1/inventory-system/actions/workflows/ci.yml/badge.svg" alt="CI Pipeline Status"/>
  </a>
  <a href="https://opensource.org/licenses/MIT">
    <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License"/>
  </a>
  <img src="https://img.shields.io/badge/Tests-43%20passing-brightgreen" alt="Tests"/>
  <img src="https://img.shields.io/badge/phase-5.7%20of%2010-blue" alt="Phase"/>
</p>

---

## What is this?

**Nexus** is a production-ready, ERP-grade inventory management system. It tracks every unit of stock from the moment it is ordered from a vendor to the moment it is sold to a customer — with mathematically guaranteed accuracy via ACID transactions, pessimistic row locking, real-time FIFO/LIFO/Weighted-Average costing, and a complete audit trail.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 · Laravel 12 |
| Frontend | Vue 3 · Inertia.js · PrimeVue 4 |
| Build | Vite |
| Database | MySQL / MariaDB |
| Auth | Laravel Sanctum · Google OAuth |
| Testing | PHPUnit · Pest (43 tests, 134 assertions) |

---

## Quick Start

```bash
# 1. Install dependencies
composer install
npm ci

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env — set DB_* credentials

# 3. Migrate & seed
php artisan migrate
php artisan db:seed

# 4. Run locally
php artisan serve       # Backend  → http://localhost:8000
npm run dev             # Frontend → http://localhost:5173
```

---

## Project Status

| Phase | Domain | Status |
|---|---|---|
| 0 | Core Stock Engine (FIFO/LIFO/WAC, Atomic Ledger, Locking) | ✅ Complete |
| 1 | Master Data & Auth (Products, Vendors, Locations, UOM, RBAC) | ✅ Complete |
| 2 | Warehouse Operations (Stock Movements, Intelligence Grid) | ✅ Complete |
| 3 | Dashboard & KPIs | ✅ Complete |
| 4 | Procurement (Purchase Orders, GRN, Returns, Replenishment) | ✅ Complete |
| 5 | Sales Orders (Mission Control Fulfill, CRM, Returns) | ✅ Complete |
| 5.5 | Finance (Invoicing, Payments, Atomic A/R & A/P) | ✅ Complete |
| 6 | Logistics & Serial Tracking | ⬜ Not started |
| 7 | Pricing & Discounts | ⬜ Not started |
| 8 | Reporting & Financial Analysis | ⬜ Not started |
| 9 | Admin & Security UI | 🚧 ~25% |
| 10 | Production Hardening | ⬜ Not started |

---

## Key Design Decisions

- **Atomic Piece Ledger** — All quantities stored in the absolute smallest unit (Pieces). UOM conversions (Box → Pieces) happen inside `StockService` before any DB write, eliminating floating-point drift.
- **Layered Costing Engine** — Physical `inventory_cost_layers` rows are created on every receipt and consumed on every issue. FIFO, LIFO, and Weighted Average all consume layers for perfect ledger↔valuation synchronisation.
- **Multi-Layer Pessimistic Locking** — Seven distinct `lockForUpdate()` targets cover inventory rows, cost layers, transaction headers, PO headers, and PO lines. Race conditions are structurally impossible within the service layer.
- **Draft / Posted Enforcement** — Inventory is only touched when a transaction transitions to `posted`. Drafts are full records that leave stock untouched until approved.
- **Contextual Atomic Billing** — Bridges the gap between logistical bulk (Boxes) and financial pieces. Standardizes the General Ledger to the absolute base unit while preserving audit context for accountants.
- **Three-Way Match Engine** — Biology-inspired validation that strictly links every Vendor Bill to a verified physical Warehouse Receipt (GRN) and its parent PO.

---

## Documentation

| File | Purpose |
|---|---|
| [`DOCS.md`](./DOCS.md) | Technical architecture overview |
| [`DEVELOPMENT_PLAN_AND_ROADMAP.md`](./DEVELOPMENT_PLAN_AND_ROADMAP.md) | Full phase-by-phase roadmap with live status |
| [`INVENTORY_ARCHITECTURE.md`](./INVENTORY_ARCHITECTURE.md) | Deep-dive: locking, costing engine, data lifecycle |
| [`SYSTEM_CAPABILITIES.md`](./SYSTEM_CAPABILITIES.md) | Complete feature capability map |
| [`PROGRESS_LOG.md`](./PROGRESS_LOG.md) | Chronological development log & milestones |
| [`USER_GUIDE.md`](./USER_GUIDE.md) | End-user guide for the live UI modules |

---

## Running Tests

```bash
php artisan test
# → 43 tests, 134 assertions, 0 failures
```

---

## License

MIT
