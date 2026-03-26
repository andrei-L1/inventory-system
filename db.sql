-- 1. Identity, Access Control & Partners
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE vendors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    contact_person VARCHAR(80) NULL,
    email VARCHAR(191) NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    country VARCHAR(80) NULL,
    tax_id VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id BIGINT UNSIGNED NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    remember_token VARCHAR(100) NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

CREATE TABLE sessions (
    id VARCHAR(191) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    device_type VARCHAR(30) NULL,
    device_name VARCHAR(100) NULL,
    browser VARCHAR(80) NULL,
    platform VARCHAR(80) NULL,
    country VARCHAR(80) NULL,
    city VARCHAR(80) NULL,
    is_admin_terminated TINYINT(1) DEFAULT 0,
    terminated_by BIGINT UNSIGNED NULL,
    terminated_at TIMESTAMP NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX (user_id),
    INDEX (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (terminated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 2. Physical Storage & Products
CREATE TABLE locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    type ENUM('warehouse', 'zone', 'aisle', 'bin') DEFAULT 'warehouse',
    parent_id BIGINT UNSIGNED NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    country VARCHAR(80) NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES locations(id) ON DELETE SET NULL
);

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    parent_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE units_of_measure (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    abbreviation VARCHAR(10) NOT NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(60) NOT NULL UNIQUE,
    name VARCHAR(191) NOT NULL,
    description TEXT NULL,
    category_id BIGINT UNSIGNED NULL,
    uom_id BIGINT UNSIGNED NULL,
    preferred_vendor_id BIGINT UNSIGNED NULL,
    brand VARCHAR(100) NULL,
    sku VARCHAR(100) NULL UNIQUE,
    barcode VARCHAR(100) NULL UNIQUE,
    costing_method ENUM('fifo', 'lifo', 'average') DEFAULT 'average',
    average_cost DECIMAL(18, 6) DEFAULT 0,
    selling_price DECIMAL(18, 6) DEFAULT 0,
    reorder_point DECIMAL(18, 4) DEFAULT 0,
    reorder_quantity DECIMAL(18, 4) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE SET NULL,
    FOREIGN KEY (preferred_vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Stock Management
CREATE TABLE inventories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity_on_hand DECIMAL(18, 4) DEFAULT 0,
    average_cost DECIMAL(18, 6) DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (product_id, location_id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- 4. Transaction Headers & Lines
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(30) NOT NULL UNIQUE,
    type ENUM('receipt', 'issue', 'transfer', 'adjustment', 'opening_balance') NOT NULL,
    vendor_id BIGINT UNSIGNED NULL,
    status ENUM('draft', 'pending', 'posted', 'cancelled') DEFAULT 'draft',
    from_location_id BIGINT UNSIGNED NULL,
    to_location_id BIGINT UNSIGNED NULL,
    transaction_date DATE NOT NULL,
    notes TEXT NULL,
    reference_doc VARCHAR(100) NULL,
    created_by BIGINT UNSIGNED NULL,
    posted_by BIGINT UNSIGNED NULL,
    posted_at TIMESTAMP NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL,
    FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,

    -- Transaction Integrity Rules (CRITICAL)
    CONSTRAINT chk_transaction_transfer_locations CHECK (
        (type = 'transfer' AND from_location_id IS NOT NULL AND to_location_id IS NOT NULL) OR 
        (type <> 'transfer')
    ),
    CONSTRAINT chk_transaction_receipt_vendor CHECK (
        (type = 'receipt' AND vendor_id IS NOT NULL) OR 
        (type <> 'receipt')
    ),
    CONSTRAINT chk_transaction_issue_no_vendor CHECK (
        (type = 'issue' AND vendor_id IS NULL) OR 
        (type <> 'issue')
    )
);

CREATE TABLE transaction_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(18, 4) NOT NULL,
    unit_cost DECIMAL(18, 6) DEFAULT 0,
    total_cost DECIMAL(18, 6) DEFAULT 0,
    unit_price DECIMAL(18, 6) DEFAULT 0,
    costing_method ENUM('fifo', 'lifo', 'average') NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- 5. Stock Ledger (Immutable Movements)
CREATE TABLE stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    transaction_line_id BIGINT UNSIGNED,
    movement_type ENUM('in', 'out') NOT NULL,
    quantity DECIMAL(18, 4) NOT NULL,
    unit_cost DECIMAL(18, 6) DEFAULT 0,
    total_cost DECIMAL(18, 6) DEFAULT 0,
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (transaction_line_id) REFERENCES transaction_lines(id) ON DELETE SET NULL,
    INDEX idx_stock_movements_query (product_id, location_id, movement_date)
);

-- 6. Cost Layers (FIFO/LIFO Tracking)
CREATE TABLE inventory_cost_layers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    transaction_line_id BIGINT UNSIGNED NULL,
    received_qty DECIMAL(18, 4) NOT NULL,
    remaining_qty DECIMAL(18, 4) NOT NULL,
    unit_cost DECIMAL(18, 6) NOT NULL,
    receipt_date DATE NOT NULL,
    is_exhausted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (transaction_line_id) REFERENCES transaction_lines(id) ON DELETE SET NULL,
    INDEX inv_cost_layers_query_idx (product_id, location_id, is_exhausted, receipt_date)
);

-- 7. Audit & Reports
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    user_snapshot VARCHAR(191) NULL,
    event VARCHAR(60) NOT NULL,
    auditable_type VARCHAR(191) NULL,
    auditable_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    url VARCHAR(500) NULL,
    http_method VARCHAR(10) NULL,
    session_id VARCHAR(191) NULL,
    tags JSON NULL,
    created_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE stock_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(18, 4) NOT NULL,
    unit_cost_avg DECIMAL(18, 6) DEFAULT 0,
    unit_cost_fifo DECIMAL(18, 6) DEFAULT 0,
    unit_cost_lifo DECIMAL(18, 6) DEFAULT 0,
    total_value_avg DECIMAL(18, 6) DEFAULT 0,
    total_value_fifo DECIMAL(18, 6) DEFAULT 0,
    total_value_lifo DECIMAL(18, 6) DEFAULT 0,
    UNIQUE KEY (snapshot_date, product_id, location_id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- 8. General Attachments
CREATE TABLE attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attachable_id BIGINT UNSIGNED NOT NULL,
    attachable_type VARCHAR(191) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(191) NOT NULL,
    file_type VARCHAR(80) NULL,
    file_size BIGINT DEFAULT 0,
    collection_name VARCHAR(50) DEFAULT 'general',
    uploader_id BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (attachable_id, attachable_type)
);

-- 9. Integrity & Reconciliation
CREATE TABLE reconciliation_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    recorded_qty DECIMAL(18, 4) NOT NULL, -- inventories.quantity_on_hand
    calculated_qty DECIMAL(18, 4) NOT NULL, -- SUM(transaction_lines)
    layers_qty DECIMAL(18, 4) NOT NULL, -- SUM(inventory_cost_layers)
    discrepancy DECIMAL(18, 4) NOT NULL,
    is_corrected TINYINT(1) DEFAULT 0,
    run_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);




