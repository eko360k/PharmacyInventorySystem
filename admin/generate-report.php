<?php
// Start session to access user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('includes/init.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Unauthorized access');
}

try {
    // Disable output buffering to ensure clean download
    if (function_exists('apache_setenv')) {
        apache_setenv('no-gzip', 1);
    }
    
    // Get sales statistics
    $salesResult = $fn->query("SELECT COUNT(*) as total_sales, SUM(total_amount) as revenue FROM sales");
    $salesStats = $fn->fetch($salesResult) ?: ['total_sales' => 0, 'revenue' => 0];

    // Get inventory statistics
    $inventoryResult = $fn->query("SELECT COUNT(*) as total_items, SUM(quantity) as total_stock FROM inventory");
    $inventoryStats = $fn->fetch($inventoryResult) ?: ['total_items' => 0, 'total_stock' => 0];

    // Get critical stock items
    $criticalResult = $fn->getLowStock();
    $criticalItems = $fn->fetchAll($criticalResult) ?: [];

    // Get all sales data
    $salesResult = $fn->query("
        SELECT s.sale_id, s.total_amount, s.sale_date, u.full_name
        FROM sales s
        JOIN users u ON s.user_id = u.user_id
        ORDER BY s.sale_date DESC 
        LIMIT 50
    ");
    $allSales = $fn->fetchAll($salesResult) ?: [];

    // Get all inventory data with medicine details
    $inventoryResult = $fn->query("
        SELECT m.name, m.sku, i.quantity, i.expiry_date, i.reorder_level
        FROM inventory i
        JOIN medicines m ON i.medicine_id = m.medicine_id
        ORDER BY m.name ASC
    ");
    $allInventory = $fn->fetchAll($inventoryResult) ?: [];

    // Get count of expired medicines
    $expiredResult = $fn->query("
        SELECT COUNT(*) as count 
        FROM inventory 
        WHERE expiry_date < CURDATE()
    ");
    $expiredData = $fn->fetch($expiredResult);
    $expiredCount = $expiredData['count'] ?? 0;

    // Generate report content
    $reportContent = generateReportContent(
        $salesStats,
        $inventoryStats,
        $criticalItems,
        $allSales,
        $allInventory,
        $expiredCount,
        $fn
    );

    // Generate filename with date and time
    $filename = 'Pharmacy_Report_' . date('Y-m-d_H-i-s') . '.txt';
    
    // Optional: Save to downloads folder for archiving
    $downloadsDir = __DIR__ . '/downloads';
    if (!is_dir($downloadsDir) && is_writable(__DIR__)) {
        @mkdir($downloadsDir, 0755, true);
    }
    if (is_writable($downloadsDir)) {
        $filepath = $downloadsDir . '/' . $filename;
        @file_put_contents($filepath, $reportContent);
    }

    // Set headers for download - these must be before any output
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($reportContent));
    header('Pragma: no-cache');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Expires: 0');

    // Output the file content
    echo $reportContent;
    exit(0);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Error: ' . $e->getMessage());
}

/**
 * Generate professional report content
 */
function generateReportContent($salesStats, $inventoryStats, $criticalItems, $allSales, $allInventory, $expiredCount, $fn)
{
    $report = "";
    $reportDate = date('F d, Y');
    $reportTime = date('h:i A');
    $reportTimestamp = date('Y-m-d H:i:s');
    
    // Header
    $report .= "╔════════════════════════════════════════════════════════════════╗\n";
    $report .= "║                  PHARMACY INVENTORY SYSTEM                     ║\n";
    $report .= "║                      COMPREHENSIVE REPORT                      ║\n";
    $report .= "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    $report .= "Report Generated: " . $reportDate . " | " . $reportTime . "\n";
    $report .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // Executive Summary
    $report .= "┌─ EXECUTIVE SUMMARY ─────────────────────────────────────────┐\n";
    $report .= "├──────────────────────────────────────────────────────────────┤\n";
    $report .= sprintf("│ Total Revenue:              %-40.2f│\n", $salesStats['revenue'] ?? 0);
    $report .= sprintf("│ Total Transactions:         %-40d│\n", $salesStats['total_sales'] ?? 0);
    $report .= sprintf("│ Total Medicines in System:  %-40d│\n", $inventoryStats['total_items'] ?? 0);
    $report .= sprintf("│ Total Stock Units:          %-40d│\n", $inventoryStats['total_stock'] ?? 0);
    $report .= sprintf("│ Critical Stock Items:       %-40d│\n", count($criticalItems));
    $report .= sprintf("│ Expired Medicines:          %-40d│\n", $expiredCount);
    $report .= "└──────────────────────────────────────────────────────────────┘\n\n";

    // Sales Report
    $report .= "┌─ RECENT SALES TRANSACTIONS ────────────────────────────────┐\n";
    $report .= "├──────────────────────────────────────────────────────────────┤\n";
    
    if (count($allSales) > 0) {
        $report .= sprintf("│ %-8s │ %-15s │ %-20s │ %-10s │\n", "Sale ID", "Cashier", "Date", "Amount");
        $report .= "├──────────────────────────────────────────────────────────────┤\n";
        
        foreach ($allSales as $sale) {
            $saleId = substr($sale['sale_id'], 0, 8);
            $cashier = substr($sale['full_name'] ?? 'N/A', 0, 15);
            $date = substr($sale['sale_date'] ?? 'N/A', 0, 20);
            $amount = number_format($sale['total_amount'] ?? 0, 2);
            
            $report .= sprintf("│ %-8s │ %-15s │ %-20s │ $%-9s │\n", $saleId, $cashier, $date, $amount);
        }
    } else {
        $report .= "│ No sales data available                                        │\n";
    }
    $report .= "└──────────────────────────────────────────────────────────────┘\n\n";

    // Inventory Report
    $report .= "┌─ INVENTORY STATUS ──────────────────────────────────────────┐\n";
    $report .= "├──────────────────────────────────────────────────────────────┤\n";
    
    if (count($allInventory) > 0) {
        $report .= sprintf("│ %-25s │ %-10s │ %-10s │ %-10s │\n", "Medicine", "SKU", "Stock", "Expiry");
        $report .= "├──────────────────────────────────────────────────────────────┤\n";
        
        foreach ($allInventory as $item) {
            $name = substr($item['name'] ?? 'N/A', 0, 25);
            $sku = substr($item['sku'] ?? 'N/A', 0, 10);
            $qty = substr((string)($item['quantity'] ?? 0), 0, 10);
            $expiry = substr($item['expiry_date'] ?? 'N/A', 0, 10);
            
            $report .= sprintf("│ %-25s │ %-10s │ %-10s │ %-10s │\n", $name, $sku, $qty, $expiry);
        }
    } else {
        $report .= "│ No inventory data available                                    │\n";
    }
    $report .= "└──────────────────────────────────────────────────────────────┘\n\n";

    // Critical Stock Alert
    if (count($criticalItems) > 0) {
        $report .= "┌─ CRITICAL STOCK ALERT ──────────────────────────────────────┐\n";
        $report .= "├──────────────────────────────────────────────────────────────┤\n";
        $report .= sprintf("│ %-25s │ %-10s │ %-12s │ %-10s │\n", "Medicine", "SKU", "Current", "Min Level");
        $report .= "├──────────────────────────────────────────────────────────────┤\n";
        
        foreach ($criticalItems as $item) {
            // Get medicine details
            $medResult = $fn->query("SELECT name, sku FROM medicines WHERE medicine_id = ?", [$item['medicine_id']]);
            $medicine = $fn->fetch($medResult) ?: ['name' => 'N/A', 'sku' => 'N/A'];
            
            $name = substr($medicine['name'], 0, 25);
            $sku = substr($medicine['sku'], 0, 10);
            $qty = substr((string)($item['quantity'] ?? 0), 0, 12);
            $reorder = substr((string)($item['reorder_level'] ?? 0), 0, 10);
            
            $report .= sprintf("│ %-25s │ %-10s │ %-12s │ %-10s │\n", $name, $sku, $qty, $reorder);
        }
        $report .= "└──────────────────────────────────────────────────────────────┘\n\n";
    }

    // Footer
    $report .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $report .= "Report Generated: " . $reportTimestamp . "\n";
    $report .= "This is an automated report from the Pharmacy Inventory System.\n";
    $report .= "For inquiries or support, please contact the system administrator.\n";
    $report .= "╔════════════════════════════════════════════════════════════════╗\n";
    $report .= "║                      END OF REPORT                            ║\n";
    $report .= "╚════════════════════════════════════════════════════════════════╝\n";

    return $report;
}
?>
