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
}
?>