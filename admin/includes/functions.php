<?php
require_once('database.php');

class Functions extends Database
{
    // ✅ Generate unique ID (for sales, transactions)
    public function generateUniqueId()
    {
        return bin2hex(random_bytes(16)); // more secure than md5
    }

    // ❌ Removed sanitize() → NOT needed with prepared statements
    // (Your database class already uses prepared queries)

    // ✅ Generate Medicine SKU
    public function generateSKU($prefix = 'MED')
    {
        $uniquePart = strtoupper(substr(uniqid(), -6));
        return $prefix . '-' . $uniquePart;
    }

    // ✅ Validate price
    public function validatePrice($price)
    {
        return is_numeric($price) && $price >= 0;
    }

    // ✅ Validate stock quantity
    public function validateStock($stock)
    {
        return is_numeric($stock) && $stock >= 0;
    }

    // ✅ Validate expiry date
    public function validateExpiryDate($date)
    {
        return strtotime($date) !== false;
    }

    // ✅ Validate email or phone (used in users/suppliers)
    public function validateUserInput($input)
    {
        $input = trim($input);

        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return "email";
        }

        if (preg_match('/^\+?[0-9]{7,15}$/', $input)) {
            return "phone";
        }

        return "invalid";
    }

    // ✅ Format currency (Ghana Cedi)
    public function formatCurrency($amount)
    {
        return CURRENCY_SYMBOL . number_format($amount, 2);
    }

    // ✅ Truncate long text (for UI tables)
    public function truncateText($text, $limit = 20)
    {
        if (strlen($text) > $limit) {
            return substr($text, 0, $limit) . '...';
        }
        return $text;
    }

    // ✅ Determine stock status (used in inventory.html UI)
    public function getStockStatus($quantity, $reorder_level)
    {
        if ($quantity <= 0) {
            return "out_of_stock";
        } elseif ($quantity <= $reorder_level) {
            return "low_stock";
        } else {
            return "in_stock";
        }
    }

    // ✅ Check if medicine is expired
    public function isExpired($expiry_date)
    {
        return strtotime($expiry_date) < time();
    }

    // ✅ Days remaining before expiry
    public function daysToExpiry($expiry_date)
    {
        $today = time();
        $expiry = strtotime($expiry_date);
        return floor(($expiry - $today) / (60 * 60 * 24));
    }

    // ✅ Generate alert message
    public function generateAlertMessage($type, $medicine_name)
    {
        switch ($type) {
            case 'low_stock':
                return "$medicine_name is low in stock";
            case 'expiry':
                return "$medicine_name is about to expire";
            default:
                return "Alert for $medicine_name";
        }
    }

    // ✅ Add a new medicine
    public function addMedicine($data)
    {
        return $this->insert('medicines', $data);
    }

    // ✅ Edit existing medicine
    public function editMedicine($medicine_id, $data)
    {
        return $this->update('medicines', $data, 'medicine_id = ?', [$medicine_id]);
    }

    // ✅ Delete medicine
    public function deleteMedicine($medicine_id)
    {
        try {
            // First delete inventory records
            $this->deleteSafe('inventory', 'medicine_id = ?', [$medicine_id]);
            
            // Then delete medicine record
            return $this->deleteSafe('medicines', 'medicine_id = ?', [$medicine_id]);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ Get medicine by ID
    public function getMedicineById($medicine_id)
    {
        $result = $this->query("SELECT * FROM medicines WHERE medicine_id = ?", [$medicine_id]);
        return $this->fetch($result);
    }

    // ✅ Get inventory by medicine ID
    public function getInventoryByMedicineId($medicine_id)
    {
        $result = $this->query("SELECT * FROM inventory WHERE medicine_id = ?", [$medicine_id]);
        return $this->fetch($result);
    }

    // ✅ Get all expired medicines
    public function getExpiredMedicines()
    {
        $sql = "SELECT m.*, i.quantity, i.expiry_date 
                FROM medicines m 
                JOIN inventory i ON m.medicine_id = i.medicine_id 
                WHERE i.expiry_date < CURDATE()";
        return $this->query($sql);
    }

    // ✅ Update inventory stock
    public function updateStock($medicine_id, $quantity)
    {
        return $this->update(
            'inventory', 
            ['quantity' => $quantity], 
            'medicine_id = ?', 
            [$medicine_id]
        );
    }

    // ✅ Update inventory expiry date
    public function updateExpiryDate($medicine_id, $expiry_date)
    {
        return $this->update(
            'inventory', 
            ['expiry_date' => $expiry_date], 
            'medicine_id = ?', 
            [$medicine_id]
        );
    }

    // ✅ AUTHENTICATION METHODS

    // Check if email already exists
    public function emailExists($email)
    {
        return $this->recordExists('users', 'email', $email);
    }

    // Validate password strength
    public function validatePasswordStrength($password)
    {
        // Must contain at least one uppercase, one number, one special character
        $has_upper = preg_match('/[A-Z]/', $password);
        $has_lower = preg_match('/[a-z]/', $password);
        $has_number = preg_match('/[0-9]/', $password);
        $has_special = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]/', $password);

        return $has_upper && $has_lower && $has_number && $has_special;
    }

    // Register a new user
    public function registerUser($full_name, $email, $password, $role = 'pharmacist')
    {
        try {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Prepare data
            $data = [
                'full_name' => $full_name,
                'email' => $email,
                'password_hash' => $password_hash,
                'role' => $role
            ];

            // Insert user using safe method
            $result = $this->insertSafe('users', $data);

            if ($result['success']) {
                return [
                    'success' => true,
                    'user_id' => $result['insert_id'],
                    'message' => 'User registered successfully'
                ];
            } else {
                // Check if it's a duplicate email error
                if (isset($result['type']) && $result['type'] === 'duplicate') {
                    return [
                        'success' => false,
                        'error' => 'Email already registered. Please try another email or login.'
                    ];
                }
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to register user'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Registration error: ' . $e->getMessage()
            ];
        }
    }

    // Login user
    public function loginUser($email, $password)
    {
        try {
            // Query user by email
            $sql = "SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1";
            $result = $this->query($sql, [$email]);

            if ($this->count($result) === 0) {
                return [
                    'success' => false,
                    'error' => 'No account found with this email address.'
                ];
            }

            $user = $this->fetch($result);

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'error' => 'Incorrect password. Please try again.'
                ];
            }

            // Login successful
            return [
                'success' => true,
                'user_id' => $user['user_id'],
                'user_name' => $user['full_name'],
                'user_email' => $user['email'],
                'user_role' => $user['role']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Login error: ' . $e->getMessage()
            ];
        }
    }

    // Get user by ID
    public function getUserById($user_id)
    {
        $result = $this->query(
            "SELECT user_id, full_name, email, role, created_at FROM users WHERE user_id = ?", 
            [$user_id]
        );
        return $this->fetch($result);
    }

    // Update user profile
    public function updateUserProfile($user_id, $full_name, $email)
    {
        $data = [
            'full_name' => $full_name,
            'email' => $email
        ];
        return $this->updateSafe('users', $data, 'user_id = ?', [$user_id]);
    }

    // Change user password
    public function changePassword($user_id, $old_password, $new_password)
    {
        try {
            // Get user's current password hash
            $result = $this->query("SELECT password_hash FROM users WHERE user_id = ?", [$user_id]);
            $user = $this->fetch($result);

            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            // Verify old password
            if (!password_verify($old_password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            // Hash new password
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);

            // Update password
            $update_result = $this->updateSafe(
                'users',
                ['password_hash' => $new_hash],
                'user_id = ?',
                [$user_id]
            );

            if ($update_result['success']) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            } else {
                return ['success' => false, 'error' => $update_result['error']];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ SALES METHODS

    // Get all sales with user info
    public function getAllSales($limit = 10, $offset = 0)
    {
        $sql = "SELECT s.*, u.full_name as cashier_name FROM sales s 
                JOIN users u ON s.user_id = u.user_id 
                ORDER BY s.sale_date DESC 
                LIMIT ? OFFSET ?";
        return $this->query($sql, [(string)$limit, (string)$offset]);
    }

    // Get sale by ID with items
    public function getSaleById($sale_id)
    {
        $sql = "SELECT s.*, u.full_name as cashier_name FROM sales s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE s.sale_id = ?";
        $result = $this->query($sql, [$sale_id]);
        return $this->fetch($result);
    }

    // Get sale items for a sale
    public function getSaleItems($sale_id)
    {
        $sql = "SELECT si.*, m.name, m.sku FROM sale_items si 
                JOIN medicines m ON si.medicine_id = m.medicine_id 
                WHERE si.sale_id = ? 
                ORDER BY si.sale_item_id";
        return $this->query($sql, [$sale_id]);
    }

    // Get sales by date range
    public function getSalesByDateRange($start_date, $end_date)
    {
        $sql = "SELECT s.*, u.full_name as cashier_name FROM sales s 
                JOIN users u ON s.user_id = u.user_id 
                WHERE DATE(s.sale_date) >= ? AND DATE(s.sale_date) <= ? 
                ORDER BY s.sale_date DESC";
        return $this->query($sql, [$start_date, $end_date]);
    }

    // Get total sales for a cashier
    public function getCashierSalesTotal($user_id, $date = null)
    {
        $sql = "SELECT SUM(total_amount) as total_sales, COUNT(*) as total_transactions FROM sales WHERE user_id = ?";
        $params = [$user_id];

        if ($date) {
            $sql .= " AND DATE(sale_date) = ?";
            $params[] = $date;
        }

        $result = $this->query($sql, $params);
        return $this->fetch($result);
    }

    // Get daily sales report
    public function getDailySalesReport($date = null)
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $sql = "SELECT DATE(s.sale_date) as sale_date, 
                SUM(s.total_amount) as daily_total, 
                COUNT(*) as transaction_count,
                COUNT(DISTINCT s.user_id) as cashiers
                FROM sales s 
                WHERE DATE(s.sale_date) = ?
                GROUP BY DATE(s.sale_date)";
        $result = $this->query($sql, [$date]);
        return $this->fetch($result);
    }

    // Get monthly sales
    public function getMonthlySalesReport($year = null, $month = null)
    {
        if (!$year) {
            $year = date('Y');
        }
        if (!$month) {
            $month = date('m');
        }

        $sql = "SELECT SUM(total_amount) as monthly_total, COUNT(*) as transaction_count
                FROM sales 
                WHERE YEAR(sale_date) = ? AND MONTH(sale_date) = ?";
        $result = $this->query($sql, [$year, $month]);
        return $this->fetch($result);
    }

    // Get top selling medicines
    public function getTopSellingMedicines($limit = 10)
    {
        $sql = "SELECT m.medicine_id, m.name, m.sku, 
                SUM(si.quantity) as total_sold,
                SUM(si.quantity * si.unit_price) as total_revenue
                FROM sale_items si 
                JOIN medicines m ON si.medicine_id = m.medicine_id 
                GROUP BY m.medicine_id, m.name, m.sku
                ORDER BY total_sold DESC 
                LIMIT ?";
        return $this->query($sql, [$limit]);
    }

    // Get sales by payment method
    public function getSalesByPaymentMethod($payment_method)
    {
        $sql = "SELECT * FROM sales WHERE payment_method = ? ORDER BY sale_date DESC";
        return $this->query($sql, [$payment_method]);
    }

    // Get payment method statistics
    public function getPaymentMethodStats()
    {
        $sql = "SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
                FROM sales 
                GROUP BY payment_method";
        return $this->query($sql);
    }

    // Count total sales
    public function getTotalSalesCount()
    {
        $sql = "SELECT COUNT(*) as total FROM sales";
        $result = $this->query($sql);
        $data = $this->fetch($result);
        return $data['total'] ?? 0;
    }

    // Get total revenue
    public function getTotalRevenue()
    {
        $sql = "SELECT SUM(total_amount) as total_revenue FROM sales";
        $result = $this->query($sql);
        $data = $this->fetch($result);
        return $data['total_revenue'] ?? 0;
    }

    // ✅ COMPLETE SALES PROCESSING - Main orchestration method
    public function processSale($user_id, $cart_items, $payment_method, $total_amount)
    {
        try {
            // 1️⃣ VALIDATE INPUT DATA
            $validation = $this->validateSaleData($cart_items, $total_amount);
            if (!$validation['success']) {
                return $validation;
            }

            // 2️⃣ VALIDATE STOCK AVAILABILITY
            $stock_check = $this->validateStockAvailability($cart_items);
            if (!$stock_check['success']) {
                return $stock_check;
            }

            // 3️⃣ VALIDATE MEDICINE EXPIRY
            $expiry_check = $this->validateMedicineExpiry($cart_items);
            if (!$expiry_check['success']) {
                return $expiry_check;
            }

            // 4️⃣ CREATE SALE RECORD
            $sale_data = [
                'user_id' => $user_id,
                'total_amount' => $total_amount,
                'payment_method' => $payment_method
            ];

            $sale_result = $this->insertSafe('sales', $sale_data);
            if (!$sale_result['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to create sale record: ' . $sale_result['error']
                ];
            }

            $sale_id = $sale_result['insert_id'];

            // 5️⃣ ADD SALE ITEMS AND UPDATE INVENTORY
            foreach ($cart_items as $item) {
                // Add item to sale_items table
                $item_data = [
                    'sale_id' => $sale_id,
                    'medicine_id' => $item['medicine_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price']
                ];

                $item_result = $this->insertSafe('sale_items', $item_data);
                if (!$item_result['success']) {
                    return [
                        'success' => false,
                        'error' => 'Failed to add sale item: ' . $item_result['error'],
                        'sale_id' => $sale_id
                    ];
                }

                // 6️⃣ UPDATE INVENTORY STOCK
                $stock_update = $this->updateSoldStock($item['medicine_id'], $item['quantity']);
                if (!$stock_update['success']) {
                    return [
                        'success' => false,
                        'error' => 'Failed to update inventory: ' . $stock_update['error'],
                        'sale_id' => $sale_id
                    ];
                }

                // 7️⃣ RECORD STOCK MOVEMENT
                $movement_result = $this->recordStockMovement(
                    $item['medicine_id'],
                    'sale',
                    $item['quantity'],
                    $sale_id
                );

                if (!$movement_result['success']) {
                    return [
                        'success' => false,
                        'error' => 'Failed to record stock movement: ' . $movement_result['error'],
                        'sale_id' => $sale_id
                    ];
                }
            }

            // ✅ SALE COMPLETED SUCCESSFULLY
            return [
                'success' => true,
                'sale_id' => $sale_id,
                'message' => "Sale #{$sale_id} completed successfully",
                'total_amount' => $total_amount,
                'items_count' => count($cart_items)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception during sale processing: ' . $e->getMessage()
            ];
        }
    }

    // ✅ VALIDATE SALE DATA
    private function validateSaleData($cart_items, $total_amount)
    {
        // Check cart is not empty
        if (empty($cart_items) || !is_array($cart_items)) {
            return ['success' => false, 'error' => 'Cart is empty. Please add items before completing sale.'];
        }

        // Check total amount is valid
        if (!is_numeric($total_amount) || $total_amount <= 0) {
            return ['success' => false, 'error' => 'Invalid total amount.'];
        }

        // Validate each cart item
        foreach ($cart_items as $item) {
            // Check required fields
            if (!isset($item['medicine_id'], $item['quantity'], $item['unit_price'])) {
                return ['success' => false, 'error' => 'Invalid cart item data.'];
            }

            // Check medicine_id is valid
            if (!is_numeric($item['medicine_id']) || $item['medicine_id'] <= 0) {
                return ['success' => false, 'error' => 'Invalid medicine ID in cart.'];
            }

            // Check quantity is valid
            if (!is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                return ['success' => false, 'error' => 'Invalid quantity in cart.'];
            }

            // Check unit price is valid
            if (!is_numeric($item['unit_price']) || $item['unit_price'] < 0) {
                return ['success' => false, 'error' => 'Invalid unit price in cart.'];
            }
        }

        return ['success' => true];
    }

    // ✅ VALIDATE STOCK AVAILABILITY
    private function validateStockAvailability($cart_items)
    {
        foreach ($cart_items as $item) {
            // Get current inventory
            $sql = "SELECT quantity FROM inventory WHERE medicine_id = ?";
            $result = $this->query($sql, [$item['medicine_id']]);

            if ($this->count($result) === 0) {
                $med = $this->getMedicineById($item['medicine_id']);
                return [
                    'success' => false,
                    'error' => "Medicine '{$med['name']}' not found in inventory."
                ];
            }

            $inventory = $this->fetch($result);

            // Check if sufficient stock exists
            if ($inventory['quantity'] < $item['quantity']) {
                $med = $this->getMedicineById($item['medicine_id']);
                return [
                    'success' => false,
                    'error' => "Insufficient stock for '{$med['name']}'. Available: {$inventory['quantity']}, Requested: {$item['quantity']}"
                ];
            }
        }

        return ['success' => true];
    }

    // ✅ VALIDATE MEDICINE EXPIRY
    private function validateMedicineExpiry($cart_items)
    {
        foreach ($cart_items as $item) {
            // Get medicine expiry date
            $sql = "SELECT i.expiry_date, m.name FROM inventory i 
                    JOIN medicines m ON i.medicine_id = m.medicine_id 
                    WHERE i.medicine_id = ?";
            $result = $this->query($sql, [$item['medicine_id']]);

            if ($this->count($result) === 0) {
                continue; // Skip if not found (already validated in stock check)
            }

            $inventory = $this->fetch($result);

            // Check if medicine is expired
            if ($this->isExpired($inventory['expiry_date'])) {
                return [
                    'success' => false,
                    'error' => "Cannot sell expired medicine '{$inventory['name']}'. Expiry date: {$inventory['expiry_date']}"
                ];
            }

            // Warn if expiring soon (within 7 days)
            $days_left = $this->daysToExpiry($inventory['expiry_date']);
            if ($days_left <= 7 && $days_left >= 0) {
                // Still allow sale but will create an alert
                error_log("WARNING: Medicine {$inventory['name']} expires in {$days_left} days");
            }
        }

        return ['success' => true];
    }

    // ✅ UPDATE SOLD STOCK (Reduce inventory after sale)
    private function updateSoldStock($medicine_id, $quantity_sold)
    {
        try {
            // Get current quantity
            $sql = "SELECT quantity FROM inventory WHERE medicine_id = ?";
            $result = $this->query($sql, [$medicine_id]);
            $inventory = $this->fetch($result);

            // Calculate new quantity
            $new_quantity = $inventory['quantity'] - $quantity_sold;

            // Update inventory
            $update_data = ['quantity' => $new_quantity];
            $update_result = $this->updateSafe('inventory', $update_data, 'medicine_id = ?', [$medicine_id]);

            if ($update_result['success']) {
                return ['success' => true, 'new_quantity' => $new_quantity];
            } else {
                return ['success' => false, 'error' => $update_result['error']];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ RECORD STOCK MOVEMENT
    private function recordStockMovement($medicine_id, $change_type, $quantity, $reference_id = null)
    {
        try {
            $movement_data = [
                'medicine_id' => $medicine_id,
                'change_type' => $change_type,
                'quantity' => $quantity
            ];

            // Add reference_id if provided (sale_id, purchase_id, etc.)
            if ($reference_id !== null) {
                $movement_data['reference_id'] = $reference_id;
            }

            $result = $this->insertSafe('stock_movements', $movement_data);

            if ($result['success']) {
                return ['success' => true, 'movement_id' => $result['insert_id']];
            } else {
                return ['success' => false, 'error' => $result['error']];
            }

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ✅ GET STOCK MOVEMENTS (Audit trail)
    public function getStockMovements($medicine_id = null, $limit = 50, $offset = 0)
    {
        $sql = "SELECT sm.*, m.name, m.sku FROM stock_movements sm 
                JOIN medicines m ON sm.medicine_id = m.medicine_id";
        $params = [];

        if ($medicine_id !== null) {
            $sql .= " WHERE sm.medicine_id = ?";
            $params[] = $medicine_id;
        }

        $sql .= " ORDER BY sm.movement_date DESC LIMIT ? OFFSET ?";
        $params[] = (string)$limit;
        $params[] = (string)$offset;

        return $this->query($sql, $params);
    }

    // ✅ GET AUDIT TRAIL (Complete stock history for a medicine)
    public function getMedicineAuditTrail($medicine_id)
    {
        $sql = "SELECT sm.*, m.name, m.sku FROM stock_movements sm 
                JOIN medicines m ON sm.medicine_id = m.medicine_id 
                WHERE sm.medicine_id = ? 
                ORDER BY sm.movement_date DESC";
        return $this->query($sql, [$medicine_id]);
    }

    // ✅ CREATE ALERT FOR LOW STOCK
    public function createLowStockAlert($medicine_id, $current_quantity, $reorder_level)
    {
        if ($current_quantity <= $reorder_level) {
            $med = $this->getMedicineById($medicine_id);
            $message = "Stock for '{$med['name']}' is low ({$current_quantity} units). Reorder level: {$reorder_level}";

            $alert_data = [
                'medicine_id' => $medicine_id,
                'alert_type' => 'low_stock',
                'message' => $message,
                'is_resolved' => 0
            ];

            return $this->insertSafe('alerts', $alert_data);
        }
        return ['success' => true];
    }

    // ✅ CREATE ALERT FOR EXPIRING MEDICINE
    public function createExpiryAlert($medicine_id, $days_to_expiry)
    {
        if ($days_to_expiry <= 30 && $days_to_expiry >= 0) {
            $med = $this->getMedicineById($medicine_id);
            $message = "Medicine '{$med['name']}' expires in {$days_to_expiry} days";

            $alert_data = [
                'medicine_id' => $medicine_id,
                'alert_type' => 'expiry',
                'message' => $message,
                'is_resolved' => 0
            ];

            return $this->insertSafe('alerts', $alert_data);
        }
        return ['success' => true];
    }

    // ✅ GET ALL ACTIVE ALERTS
    public function getActiveAlerts()
    {
        $sql = "SELECT a.*, m.name, m.sku FROM alerts a 
                JOIN medicines m ON a.medicine_id = m.medicine_id 
                WHERE a.is_resolved = 0 
                ORDER BY a.created_at DESC";
        return $this->query($sql);
    }

    // ✅ RESOLVE ALERT
    public function resolveAlert($alert_id)
    {
        $update_data = ['is_resolved' => 1];
        return $this->updateSafe('alerts', $update_data, 'alert_id = ?', [$alert_id]);
    }

    // ✅ GET SALE INVOICE (Complete sale data for printing/display)
    public function getSaleInvoice($sale_id)
    {
        try {
            $sale = $this->getSaleById($sale_id);
            if (!$sale) {
                return ['success' => false, 'error' => 'Sale not found'];
            }

            $items = $this->getSaleItems($sale_id);
            if ($this->count($items) === 0) {
                return ['success' => false, 'error' => 'No items found for this sale'];
            }

            $items_array = $this->fetchAll($items);

            return [
                'success' => true,
                'invoice' => [
                    'sale_id' => $sale['sale_id'],
                    'cashier_name' => $sale['cashier_name'],
                    'sale_date' => $sale['sale_date'],
                    'payment_method' => $sale['payment_method'] ?? 'Cash',
                    'total_amount' => $sale['total_amount'],
                    'items' => $items_array,
                    'item_count' => count($items_array)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>