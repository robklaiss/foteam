-- Add missing columns to orders table
BEGIN TRANSACTION;

-- Disable foreign key constraints
PRAGMA foreign_keys=off;

-- Helper function to check if a column exists
CREATE TEMPORARY TABLE IF NOT EXISTS pragma_table_info(tbl_name, name, type, notnull, dflt_value, pk);

-- Check and rename columns if they exist
CREATE TEMPORARY VIEW column_check AS 
SELECT name FROM pragma_table_info('orders') 
WHERE name IN ('customer_name', 'customer_email', 'customer_phone');

-- Rename columns if they exist
UPDATE column_check 
SET name = CASE 
    WHEN name = 'customer_name' THEN 'name'
    WHEN name = 'customer_email' THEN 'email'
    WHEN name = 'customer_phone' THEN 'phone'
END
WHERE name IS NOT NULL;

-- Add new columns if they don't exist
CREATE TABLE IF NOT EXISTS temp_orders AS SELECT * FROM orders LIMIT 0;

-- Get the list of columns we need to add
CREATE TEMPORARY TABLE columns_to_add AS
SELECT 'address' AS col_name, 'TEXT' AS col_type, '' AS default_value
UNION ALL SELECT 'payment_method', 'TEXT', 'credit_card'
UNION ALL SELECT 'subtotal', 'REAL', '0.0'
UNION ALL SELECT 'tax', 'REAL', '0.0';

-- Add columns that don't exist
INSERT INTO temp_orders SELECT * FROM orders WHERE 1=0;

-- Create a new table with the updated schema
CREATE TABLE orders_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    address TEXT DEFAULT '',
    payment_method TEXT NOT NULL DEFAULT 'credit_card',
    subtotal REAL NOT NULL DEFAULT 0.0,
    tax REAL NOT NULL DEFAULT 0.0,
    total_amount REAL NOT NULL,
    status TEXT CHECK(status IN ('pending', 'processing', 'completed', 'cancelled', 'refunded')) DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Copy data from old table to new table
INSERT INTO orders_new (
    id, 
    user_id, 
    name, 
    email, 
    phone, 
    address,
    payment_method,
    subtotal,
    tax,
    total_amount, 
    status, 
    order_date
)
SELECT 
    o.id, 
    o.user_id, 
    COALESCE(o.name, o.customer_name, '') as name, 
    COALESCE(o.email, o.customer_email, '') as email, 
    COALESCE(o.phone, o.customer_phone, '') as phone,
    COALESCE(o.address, '') as address,
    COALESCE(o.payment_method, 'credit_card') as payment_method,
    COALESCE(o.subtotal, o.total_amount, 0.0) as subtotal,
    COALESCE(o.tax, 0.0) as tax,
    o.total_amount, 
    COALESCE(o.status, 'pending') as status, 
    COALESCE(o.order_date, CURRENT_TIMESTAMP) as order_date
FROM orders o;

-- Drop the old table
DROP TABLE IF EXISTS orders_old;

-- Rename the new table to the original name
ALTER TABLE orders RENAME TO orders_old;
ALTER TABLE orders_new RENAME TO orders;

-- Recreate any indexes
CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);

-- Clean up
DROP TABLE IF EXISTS orders_old;
DROP VIEW IF EXISTS column_check;
DROP TABLE IF EXISTS temp_orders;
DROP TABLE IF EXISTS columns_to_add;

-- Re-enable foreign key constraints
PRAGMA foreign_keys=on;

-- Drop the old table
DROP TABLE orders;

-- Rename the new table to the original name
ALTER TABLE orders_new RENAME TO orders;

-- Recreate any indexes
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);

COMMIT;
