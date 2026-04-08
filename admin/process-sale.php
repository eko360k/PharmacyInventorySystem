<?php
/**
 * SALES PROCESSING ENDPOINT
 * Handles AJAX POST requests from sales.php/addSales.php
 * Processes complete sales transactions with validation and database capture
 */

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./includes/init.php');

// ✅ AUTHENTICATION CHECK
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Please log in first'
    ]);
    exit;
}

// ✅ VALIDATE REQUEST METHOD
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// ✅ PARSE JSON DATA
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ✅ VALIDATE REQUEST DATA
if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON data received'
    ]);
    exit;
}

if (empty($data['cart']) || !is_array($data['cart'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Cart is empty or invalid'
    ]);
    exit;
}

try {
    // Extract data from request
    $cart = $data['cart'];
    $total_amount = isset($data['total']) ? (float)$data['total'] : 0;
    $payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'Cash';
    $user_id = $_SESSION['user_id'];

    // ✅ PROCESS COMPLETE SALE using new comprehensive method
    $sale_result = $fn->processSale(
        $user_id,
        $cart,
        $payment_method,
        $total_amount
    );

    // ✅ HANDLE RESPONSE
    if ($sale_result['success']) {
        // Create low stock alerts for items with reduced quantities
        foreach ($cart as $item) {
            $inventory = $fn->getInventoryByMedicineId($item['medicine_id']);
            if ($inventory) {
                $fn->createLowStockAlert($item['medicine_id'], $inventory['quantity'], $inventory['reorder_level']);
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'sale_id' => $sale_result['sale_id'],
            'message' => $sale_result['message'],
            'total_amount' => $sale_result['total_amount'],
            'items_count' => $sale_result['items_count']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $sale_result['error']
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

exit;
?>
