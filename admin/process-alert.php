<?php
/**
 * ALERTS PROCESSING ENDPOINT
 * Handles AJAX POST requests from alerts.php
 * Processes alert resolution and restock operations with quantity input
 */

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('./includes/init.php');

// ✅ AUTHENTICATION CHECK
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in first']);
    exit;
}

// ✅ VALIDATE REQUEST METHOD
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

// ✅ SET RESPONSE HEADER
header('Content-Type: application/json');

// ✅ PARSE JSON DATA
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// ✅ VALIDATE REQUEST DATA
if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data. Action required.']);
    exit;
}

try {
    $action = $data['action'];

    // ============================================
    // RESTOCK SINGLE MEDICINE WITH QUANTITY
    // ============================================
    if ($action === 'restock_single') {
        if (!isset($data['medicine_id']) || !isset($data['quantity'])) {
            throw new Exception('Medicine ID and quantity are required');
        }

        $medicine_id = intval($data['medicine_id']);
        $restock_quantity = intval($data['quantity']);
        $expiry_date = isset($data['expiry_date']) ? trim($data['expiry_date']) : null;

        // Validate inputs
        if ($medicine_id <= 0 || $restock_quantity <= 0) {
            throw new Exception('Invalid medicine ID or quantity');
        }

        // Validate expiry date format
        if ($expiry_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry_date)) {
            throw new Exception('Invalid expiry date format. Use YYYY-MM-DD');
        }

        // Get medicine and verify it exists
        $medicine = $fn->getMedicineById($medicine_id);
        if (!$medicine) {
            throw new Exception('Medicine not found');
        }

        // Get current inventory
        $inventory = $fn->getInventoryByMedicineId($medicine_id);
        if (!$inventory) {
            throw new Exception('Inventory record not found');
        }

        // Calculate new quantity
        $current_qty = intval($inventory['quantity']);
        $new_quantity = $current_qty + $restock_quantity;

        // Update inventory quantity in database
        $update_result = $fn->updateStock($medicine_id, $new_quantity);

        if (!$update_result || (is_array($update_result) && !$update_result['success'])) {
            throw new Exception('Failed to update inventory');
        }

        // Update expiry date if provided
        if ($expiry_date) {
            $expiry_result = $fn->updateExpiryDate($medicine_id, $expiry_date);
            if (!$expiry_result || (is_array($expiry_result) && !$expiry_result['success'])) {
                throw new Exception('Failed to update expiry date');
            }
        }

        // Record stock movement for audit trail
        $movement_result = $fn->recordStockMovement(
            $medicine_id,
            'purchase',
            $restock_quantity,
            null
        );

        if (!$movement_result || (is_array($movement_result) && !$movement_result['success'])) {
            throw new Exception('Failed to record stock movement');
        }

        // ✅ RECORD RESTOCK IN DEDICATED RESTOCKS TABLE with expiry date
        $user_id = $_SESSION['user_id'] ?? null;
        $restock_record = $fn->recordRestock(
            $medicine_id,
            $restock_quantity,
            $current_qty,
            $new_quantity,
            $user_id,
            null,
            $expiry_date
        );

        if (!$restock_record || (is_array($restock_record) && !$restock_record['success'])) {
            throw new Exception('Failed to record restock: ' . (isset($restock_record['error']) ? $restock_record['error'] : 'Unknown error'));
        }

        // ✅ CREATE BATCH RECORD (for batch-level inventory tracking)
        $restock_id = isset($restock_record['insert_id']) ? $restock_record['insert_id'] : null;
        $batch_record = $fn->createBatch(
            $medicine_id,
            $restock_quantity,
            $expiry_date,
            $restock_id,
            "Restocked by {$fn->getUserById($user_id)['full_name']}"
        );

        if (!$batch_record || (is_array($batch_record) && !$batch_record['success'])) {
            throw new Exception('Failed to create batch record. Please contact admin.');
        }

        // Auto-resolve low stock alert if stock is now adequate
        if ($new_quantity > intval($inventory['reorder_level'])) {
            $alerts_result = $fn->query(
                "SELECT alert_id FROM alerts WHERE medicine_id = ? AND alert_type = 'low_stock' AND is_resolved = 0",
                [$medicine_id]
            );
            
            while ($alert = $fn->fetch($alerts_result)) {
                $fn->resolveAlert($alert['alert_id']);
            }
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Successfully restocked {$medicine['name']}",
            'medicine_id' => $medicine_id,
            'medicine_name' => $medicine['name'],
            'previous_quantity' => $current_qty,
            'restock_quantity' => $restock_quantity,
            'new_quantity' => $new_quantity
        ]);
        exit;
    }

    // ============================================
    // RESOLVE SINGLE EXPIRY ALERT
    // ============================================
    elseif ($action === 'resolve') {
        if (!isset($data['alert_id'])) {
            throw new Exception('Alert ID is required');
        }

        $alert_id = intval($data['alert_id']);
        $result = $fn->resolveAlert($alert_id);

        if ($result && $result['success']) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Alert has been resolved successfully',
                'alert_id' => $alert_id
            ]);
            exit;
        } else {
            throw new Exception($result['error'] ?? 'Failed to resolve alert');
        }
    }

    // ============================================
    // INVALID ACTION
    // ============================================
    else {
        throw new Exception("Unknown action: {$action}");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

exit;
?>
