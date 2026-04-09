-- ============================================
-- DATABASE SCHEMA UPDATES FOR SALES SYSTEM
-- ============================================
-- Run these SQL statements to enable complete sales functionality

USE rosano;

-- ✅ ADD payment_method COLUMN TO sales TABLE (if not exists)
-- This captures the payment method used for each transaction
ALTER TABLE sales ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash' 
AFTER total_amount;

-- ✅ VERIFY sales TABLE STRUCTURE (Optional - for reference)
-- Expected columns: sale_id, user_id, total_amount, payment_method, sale_date
-- DESCRIBE sales;

-- ✅ CREATE INDEX ON sales TABLE for better query performance
CREATE INDEX idx_sales_user_date ON sales(user_id, sale_date);
CREATE INDEX idx_sales_payment ON sales(payment_method);
CREATE INDEX idx_sale_items_sale_id ON sale_items(sale_id);
CREATE INDEX idx_stock_movements_medicine ON stock_movements(medicine_id);

-- ✅ VERIFY inventory TABLE STRUCTURE (should have reorder_level)
-- If reorder_level doesn't exist, add it:
-- ALTER TABLE inventory ADD COLUMN reorder_level INT DEFAULT 10;

-- ============================================
-- SUMMARY OF CHANGES
-- ============================================
-- ✅ Added payment_method column to sales table
-- ✅ Created indexes for better performance
-- ✅ System now captures payment methods: Cash, Card, Cheque, Mobile Money

-- ============================================
-- SAMPLE QUERIES TO TEST
-- ============================================

-- Get all sales with payment method breakdown
-- SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
-- FROM sales
-- GROUP BY payment_method;

-- Get sales by date and payment method
-- SELECT DATE(sale_date) as date, payment_method, COUNT(*) as count, SUM(total_amount) as total
-- FROM sales
-- WHERE DATE(sale_date) = CURDATE()
-- GROUP BY DATE(sale_date), payment_method;
