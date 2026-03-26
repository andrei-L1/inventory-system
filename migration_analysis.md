# Migration-Specific Production-Readiness Analysis

> Analyzing all 20 migration files, their execution order, the final schema state they produce, and every bug introduced — especially by the multi-step refactoring.

---

## Execution Order & Dependency Chain

```
0001_01_01_000000  →  roles, users, password_reset_tokens, sessions
0001_01_01_000001  →  cache, cache_locks
0001_01_01_000002  →  jobs, job_batches, failed_jobs
2026_03_26_000001  →  locations  (ENUM type — temporary state)
2026_03_26_000002  →  categories, units_of_measure, products, product_images
2026_03_26_000003  →  inventories, inventory_cost_layers  (no issued_qty yet)
2026_03_26_000004  →  transactions (ENUM type/status), transaction_lines, adds FK to cost_layers
2026_03_26_000005  →  audit_logs, activity_logs
2026_03_26_000006  →  reports, report_runs, stock_snapshots
2026_03_26_131929  →  vendors
2026_03_26_131942  →  adds preferred_vendor_id → products, vendor_id → transactions
2026_03_26_132801  →  attachments
2026_03_26_143459  →  reconciliation_logs
2026_03_26_220000  →  stock_movements
2026_03_26_220500  →  adds issued_qty → inventory_cost_layers
2026_03_26_220800  →  adds is_active + deleted_at → units_of_measure
2026_03_26_230000  →  location_types, transaction_types, transaction_statuses  (+ seeds)
2026_03_26_230005  →  REFACTOR — drops ENUMs, adds FK columns to locations & transactions
2026_03_26_231000  →  purchase_order_statuses, purchase_orders, purchase_order_lines  (+ seeds)
2026_03_26_231005  →  adds purchase_order_id → transactions
```

**✅ Dependency order is correct.** No circular dependencies. All FK targets exist before they are referenced. This is one of the strongest aspects of the migration design.

---

## 🔴 Critical Bugs (Will Cause Data Corruption or Crashes)

### BUG 1 — `inventory_cost_layers.remaining_qty` is a plain column
**File:** `2026_03_26_000003_create_inventories_table.php`

```php
// Current — plain writable column
$table->decimal('remaining_qty', 18, 4);

// What it should be — enforced by the DB engine
$table->decimal('remaining_qty', 18, 4)->storedAs('received_qty - issued_qty');
```

`remaining_qty` is supposed to always equal `received_qty - issued_qty`. As a plain column, any application bug, direct DB write, or botched migration can make these drift silently. **Your entire FIFO/LIFO costing engine depends on this being accurate.** If it drifts, you get wrong cost calculations with no error — the worst kind of bug.

**Fix:** Change to a `STORED` generated column. Also requires removing `issued_qty` from the `after()` placement since you can't `storedAs` a column that references one added in a later migration — combine `issued_qty` and the generated `remaining_qty` into a single migration.

---

### BUG 2 — `attachments` has a DUPLICATE INDEX
**File:** `2026_03_26_132801_create_attachments_table.php`

```php
$table->morphs('attachable');  // ← automatically creates index on (attachable_id, attachable_type)
// ...
$table->index(['attachable_id', 'attachable_type']);  // ← creates an IDENTICAL second index!
```

`morphs()` in Laravel **automatically** creates a composite index on `(attachable_id, attachable_type)`. The explicit `->index()` call below it adds a **second, identical index** to the same columns. You now have two indexes doing the same job — wasting ~2× the disk/write overhead on every insert, and confusing `EXPLAIN` output.

**Fix:** Remove the explicit `$table->index(['attachable_id', 'attachable_type'])` line — `morphs()` already handles it.

---

### BUG 3 — The refactor migration's `down()` is fundamentally broken
**File:** `2026_03_26_230005_refactor_enum_to_lookup_tables.php`

The `up()` does three things per table:
1. Add new FK column (nullable)
2. Populate it from old ENUM data
3. Drop old ENUM column, make FK column NOT NULL, add FK constraint

The `down()` only does:
- Drop FK constraint
- Add back the ENUM column

**It never drops `location_type_id`, `transaction_type_id`, or `transaction_status_id`.** Running `migrate:rollback` leaves the table with:
- ✅ Old ENUM column restored
- ❌ New FK columns still present
- ❌ Data in FK columns never migrated back to ENUMs
- ❌ Table is in a broken hybrid state

Any CI pipeline that runs `migrate:rollback` then `migrate` again (e.g., test refresh) will fail or produce corrupt data.

**Fix:** The `down()` must explicitly drop the FK columns AND repopulate the ENUM from the FK data.

---

### BUG 4 — `dropColumn` + `change()` + `foreign()` in the SAME `Schema::table()` call
**File:** `2026_03_26_230005_refactor_enum_to_lookup_tables.php`

```php
Schema::table('locations', function (Blueprint $table) {
    $table->dropColumn('type');                                    // ← structural change
    $table->unsignedBigInteger('location_type_id')->nullable(false)->change(); // ← change
    $table->foreign('location_type_id')->references('id')->on('location_types'); // ← FK
});
```

Combining `dropColumn`, `->change()`, and `->foreign()` in one `Schema::table()` block is unreliable across Laravel versions. In Laravel 10, `->change()` requires `doctrine/dbal`. In Laravel 11, it uses native SQL. Either way, combining a column drop, a column change, and a FK addition in one block can cause:

- Doctrine DBAL conflicts (simultaneous ALTER TABLE operations)
- The `->change()` failing silently if Doctrine ignores it
- FK constraint added before column type finalised

**Fix:** Split into **three separate** `Schema::table()` calls — one to drop the old column, one to `->change()` the new column, one to add the FK.

---

## 🟡 High-Impact Issues (Serious Performance or Architecture Problems)

### ISSUE 5 — Composite index on `(type, status)` is LOST after the refactor and never replaced
**File:** `2026_03_26_000004_create_transactions_table.php` + `2026_03_26_230005_refactor_enum_to_lookup_tables.php`

Migration `000004` creates:
```php
$table->index(['type', 'status']);       // ← dropped automatically when columns are removed
$table->index('transaction_date');       // ✅ still exists
$table->index('created_by');            // ✅ still exists
```

When the refactor migration (`230005`) drops `type` and `status` columns, **MySQL silently drops the `(type, status)` composite index** with them. The refactor adds FK constraints (which each get a single-column index) but **never adds a `(transaction_type_id, transaction_status_id)` composite index**.

`transactions` is the most heavily filtered table in the system. Every list view, every report, every dashboard filters by type AND status together. Without this composite index every such query does a full table scan.

**Fix:** Add to migration `230005` after the FKs are added:
```php
$table->index(['transaction_type_id', 'transaction_status_id', 'transaction_date']);
```

---

### ISSUE 6 — Seed data inside migrations (antipattern)
**Files:** `2026_03_26_230000_create_lookup_tables.php`, `2026_03_26_231000_create_purchase_order_tables.php`

```php
DB::table('location_types')->insert([...]);       // seeds in migration
DB::table('transaction_types')->insert([...]);    // seeds in migration
DB::table('purchase_order_statuses')->insert([...]); // seeds in migration
```

Problems this causes:
- **Testing:** `RefreshDatabase` re-runs migrations → seeds execute again → if tests also call `db:seed`, you get duplicate data conflicts
- **Re-seeding:** You can't run `php artisan db:seed --class=TransactionTypeSeeder` independently — the data already exists from the migration, causing unique constraint failures
- **Rollback:** Rolling back the migration deletes the table AND the seed data. Rolling back then re-migrating re-seeds fresh, losing any user edits to those lookup records
- **Environment separation:** You can't have different lookup values per environment (e.g., staging vs prod) without modifying the migration file itself

**Fix:** Move all `DB::table()->insert()` calls to proper Seeder classes. Use `$this->callOnce(SeederClass::class)` inside `DatabaseSeeder`.

---

### ISSUE 7 — `products.costing_method` and `transaction_lines.costing_method` still ENUM
**Files:** `2026_03_26_000002_create_products_table.php`, `2026_03_26_000004_create_transactions_table.php`

```php
$table->enum('costing_method', ['fifo', 'lifo', 'average'])->default('average'); // products
$table->enum('costing_method', ['fifo', 'lifo', 'average'])->nullable();         // transaction_lines
```

You correctly refactored `location.type`, `transaction.type`, and `transaction.status` away from ENUMs to lookup tables. But `costing_method` on two tables was left behind. Adding a new method (e.g., `standard_cost`) now requires `ALTER TABLE products MODIFY COLUMN costing_method ENUM(...)` in production — a long-lock operation on a large table.

---

### ISSUE 8 — No CHECK constraints anywhere in the schema
Laravel's Schema Builder has no native `CHECK` constraint support. You must use `DB::statement()`. None of the migrations do this, meaning:

| Table | Column | Missing constraint | Risk |
|---|---|---|---|
| `inventories` | `quantity_on_hand` | `CHECK (quantity_on_hand >= 0)` | Negative stock accepted silently |
| `transaction_lines` | `quantity` | `CHECK (quantity > 0)` | Zero/negative quantities accepted |
| `inventory_cost_layers` | `received_qty` | `CHECK (received_qty > 0)` | Zero-quantity layers corrupt FIFO |
| `inventory_cost_layers` | `issued_qty` | `CHECK (issued_qty >= 0)` | Negative issuance accepted |
| `purchase_order_lines` | `ordered_qty` | `CHECK (ordered_qty > 0)` | Zero-qty PO lines accepted |

**Fix:** Add after each `Schema::create()`:
```php
DB::statement('ALTER TABLE inventories ADD CONSTRAINT chk_qty_non_negative CHECK (quantity_on_hand >= 0)');
```

---

### ISSUE 9 — `reconciliation_logs` uses `cascadeOnDelete()` on both FKs
**File:** `2026_03_26_143459_create_reconciliation_logs_table.php`

```php
$table->foreignId('product_id')->constrained('products')->cascadeOnDelete();  // ← WRONG
$table->foreignId('location_id')->constrained('locations')->cascadeOnDelete(); // ← WRONG
```

Reconciliation logs are **historical integrity records**. If a product or location is hard-deleted, all its reconciliation history is silently wiped. Even though `products` and `locations` use soft deletes (so hard-delete is rare), an accidental `forceDelete()` on a product would cascade-delete every reconciliation record associated with it.

**Fix:** Change both to `->restrictOnDelete()` to preserve history.

---

## 🟡 Medium Issues

### ISSUE 10 — `stock_movements.created_at` is manually nullable but `updated_at` doesn't exist
**File:** `2026_03_26_220000_create_stock_movements_table.php`

```php
$table->timestamp('movement_date')->useCurrent();
$table->timestamp('created_at')->nullable();  // ← manual, only created_at
// no updated_at — intentional for immutable ledger
```

This is correct in intent (immutable ledger should not have `updated_at`) but the **implementation is fragile**. Laravel's Eloquent model will try to set both `created_at` AND `updated_at` by default. Your `StockMovement` model **must** have:
```php
public $timestamps = false;
const CREATED_AT = 'created_at';
// or even simpler:
public $timestamps = false;
// and manually set created_at on every insert
```
If this is not set, every `StockMovement::create()` call will throw a `Column not found: 1054 Unknown column 'updated_at'` error.

---

### ISSUE 11 — `units_of_measure.is_active` added as `tinyInteger()`, not `boolean()`
**File:** `2026_03_26_220800_add_soft_deletes_to_uom.php`

```php
$table->tinyInteger('is_active')->default(1)->after('abbreviation');
// Every other is_active in the schema uses:
$table->boolean('is_active')->default(true);
```

Both produce `TINYINT(1)` in MySQL, so there's no functional difference. But it's inconsistent in migration code. When cast in Eloquent, `boolean` casts return `true`/`false`; `tinyInteger` returns `0`/`1`. If the `UnitOfMeasure` model doesn't cast `is_active` explicitly, comparisons like `if ($uom->is_active)` behave differently from other models.

---

### ISSUE 12 — `roles` table has no soft deletes and no `is_active`
**File:** `0001_01_01_000000_create_users_table.php`

Every master data table in the system has `softDeletes()` and `is_active`: `users`, `vendors`, `products`, `locations`, `categories`, `units_of_measure`. But `roles` has neither. If you delete a role (even accidentally), all users assigned to it hit a RESTRICT FK error. You can never clean up bad/test roles without first reassigning every user.

Same issue applies to `location_types`, `transaction_types`, `transaction_statuses`, `purchase_order_statuses` — these are lookup tables that may need to be deactivated in production without being deleted.

---

### ISSUE 13 — `inventories` has a redundant index
**File:** `2026_03_26_000003_create_inventories_table.php`

```php
$table->unique(['product_id', 'location_id']); // composite unique — covers product_id as prefix
$table->index('location_id');                   // useful ✅
$table->index('product_id');                    // REDUNDANT — unique key already covers this
```

MySQL uses the composite unique key `(product_id, location_id)` for any query filtering by `product_id` alone (since `product_id` is the leading key). The standalone `index('product_id')` is therefore never used and wastes write overhead.

---

## 🟢 Low-Priority Issues

### ISSUE 14 — `stock_movements` composite index is auto-named, doesn't match intent
**File:** `2026_03_26_220000_create_stock_movements_table.php`

```php
// Generates: stock_movements_product_id_location_id_movement_date_index
$table->index(['product_id', 'location_id', 'movement_date']);

// Should be explicit to match the original intent:
$table->index(['product_id', 'location_id', 'movement_date'], 'idx_stock_movements_query');
```

---

### ISSUE 15 — `purchase_orders.status_id` FK has no explicit `ON DELETE`
**File:** `2026_03_26_231000_create_purchase_order_tables.php`

```php
$table->foreignId('status_id')->constrained('purchase_order_statuses');
// Missing: ->restrictOnDelete()
```

Defaults to `RESTRICT` (correct intent) but intent is not explicit. Document it or add `->restrictOnDelete()`.

---

### ISSUE 16 — `transactions` refactor drops old indexes but `created_by` index survives correctly
**Status:** ✅ Not an issue — confirmed `created_by` and `transaction_date` indexes survive the refactor since neither column is dropped. Only `(type, status)` is lost (see Issue 5 above).

---

## Final Migration Scorecard

| Dimension | Score | Key Finding |
|---|---|---|
| **Execution Order / Dependencies** | 10/10 | Perfect. No circular deps, every FK target exists before it's needed |
| **Final Schema Correctness** | 7/10 | Major: `remaining_qty` not generated, ENUM leftovers |
| **Data Integrity Guards** | 4/10 | No CHECK constraints, wrong cascade on reconciliation_logs |
| **Index Strategy** | 6/10 | Critical index lost after refactor, duplicate index in attachments |
| **Rollback Safety** | 3/10 | Refactor `down()` is broken beyond recovery |
| **Code Quality** | 6/10 | Seeding in migrations, inconsistent `tinyInteger` vs `boolean` |
| **Performance Readiness** | 6/10 | Missing `(type_id, status_id)` composite on transactions |

**Overall: ~6/10 — The happy path works, but failure modes and data integrity are weak.**

---

## Priority Fix List (By Migration File)

| File | Fix |
|---|---|
| `000003_create_inventories_table` | Make `remaining_qty` a `storedAs` generated column; remove redundant `index('product_id')` |
| `000003_create_inventories_table` | Add `issued_qty` here (not in a later migration) so `storedAs` can reference it |
| `000004_create_transactions_table` | Add `DB::statement()` CHECK on `transaction_lines.quantity > 0` |
| `000003_create_inventories_table` | Add `DB::statement()` CHECK on `inventories.quantity_on_hand >= 0` |
| `132801_create_attachments_table` | Remove the duplicate explicit `->index(['attachable_id', 'attachable_type'])` |
| `143459_create_reconciliation_logs` | Change both FKs from `cascadeOnDelete()` to `restrictOnDelete()` |
| `220000_create_stock_movements_table` | Name the composite index explicitly as `idx_stock_movements_query` |
| `230000_create_lookup_tables` | Move all `DB::table()->insert()` to dedicated Seeders |
| `230005_refactor_enum_to_lookup_tables` | Fix `down()` to also drop FK columns and repopulate ENUMs |
| `230005_refactor_enum_to_lookup_tables` | Split `dropColumn + change + foreign` into 3 separate `Schema::table()` blocks |
| `230005_refactor_enum_to_lookup_tables` | Add composite index `(transaction_type_id, transaction_status_id, transaction_date)` after refactor |
| `231000_create_purchase_order_tables` | Move all `DB::table()->insert()` to dedicated Seeders |
| `220800_add_soft_deletes_to_uom` | Change `tinyInteger('is_active')` to `boolean('is_active')` for consistency |
| `000000_create_users_table` | Add `softDeletes()` + `is_active` to `roles` table |
