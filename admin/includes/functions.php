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
}
?>