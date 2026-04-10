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

        // Validate inputs
        if ($medicine_id <= 0 || $restock_quantity <= 0) {
            throw new Exception('Invalid medicine ID or quantity');
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

        // Update inventory in database
        $update_result = $fn->updateStock($medicine_id, $new_quantity);

        if (!$update_result) {
            throw new Exception('Failed to update inventory');
        }

        // Record stock movement
        $movement_result = $fn->recordStockMovement(
            $medicine_id,
            'restock',
            $restock_quantity,
            null
        );

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
    // RESTOCK BATCH (MULTIPLE ITEMS)
    // ============================================
    elseif ($action === 'restock_batch') {
        if (!isset($data['items']) || !is_array($data['items'])) {
            throw new Exception('Items array is required');
        }

        $items = $data['items'];
        
        if (empty($items)) {
            throw new Exception('No items provided for restock');
        }

        $updated_count = 0;
        $errors = [];
        $details = [];

        foreach ($items as $item) {
            try {
                $medicine_id = intval($item['medicine_id'] ?? 0);
                $restock_quantity = intval($item['quantity'] ?? 0);

                if ($medicine_id <= 0 || $restock_quantity <= 0) {
                    $errors[] = "Invalid medicine ID or quantity for item";
                    continue;
                }

                // Get medicine
                $medicine = $fn->getMedicineById($medicine_id);
                if (!$medicine) {
                    $errors[] = "Medicine ID $medicine_id not found";
                    continue;
                }

                // Get current inventory
                $inventory = $fn->getInventoryByMedicineId($medicine_id);
                if (!$inventory) {
                    $errors[] = "Inventory not found for {$medicine['name']}";
                    continue;
                }

                // Calculate new quantity
                $current_qty = intval($inventory['quantity']);
                $new_quantity = $current_qty + $restock_quantity;

                // Update inventory
                $update_result = $fn->updateStock($medicine_id, $new_quantity);

                if (!$update_result) {
                    $errors[] = "Failed to update {$medicine['name']}";
                    continue;
                }

                // Record stock movement
                $fn->recordStockMovement(
                    $medicine_id,
                    'restock',
                    $restock_quantity,
                    null
                );

                // Auto-resolve low stock alert
                if ($new_quantity > intval($inventory['reorder_level'])) {
                    $alerts_result = $fn->query(
                        "SELECT alert_id FROM alerts WHERE medicine_id = ? AND alert_type = 'low_stock' AND is_resolved = 0",
                        [$medicine_id]
                    );
                    
                    while ($alert = $fn->fetch($alerts_result)) {
                        $fn->resolveAlert($alert['alert_id']);
                    }
                }

                $updated_count++;
                $details[] = [
                    'medicine_name' => $medicine['name'],
                    'previous_qty' => $current_qty,
                    'restock_qty' => $restock_quantity,
                    'new_qty' => $new_quantity
                ];

            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $response = [
            'success' => true,
            'updated_count' => $updated_count,
            'total_items' => count($items),
            'message' => "Successfully restocked $updated_count of " . count($items) . " items",
            'details' => $details
        ];

        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }

        http_response_code(200);
        echo json_encode($response);
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


try {
    $action = $data['action'];

    // ============================================
    // RESTOCK SINGLE MEDICINE WITH USER INPUT
    // ============================================
    if ($action === 'restock_single') {
        if (!isset($data['medicine_id']) || !isset($data['quantity'])) {
            throw new Exception('Medicine ID and quantity are required');
        }

        $medicine_id = intval($data['medicine_id']);
        $restock_qty = intval($data['quantity']);

        // Validate quantity
        if ($restock_qty <= 0) {
            throw new Exception('Quantity must be greater than 0');
        }

        // Get medicine and inventory info
        $medicine = $fn->getMedicineById($medicine_id);
        if (!$medicine) {
            throw new Exception('Medicine not found');
        }

        $inventory = $fn->getInventoryByMedicineId($medicine_id);
        if (!$inventory) {
            throw new Exception('Inventory record not found');
        }

        $previous_qty = $inventory['quantity'];
        $new_qty = $previous_qty + $restock_qty;

        // Update inventory with new quantity
        $update_result = $fn->updateStock($medicine_id, $new_qty);
        if (!$update_result['success']) {
            throw new Exception('Failed to update inventory: ' . $update_result['error']);
        }

        // Record stock movement for audit trail
        $movement_result = $fn->recordStockMovement(
            $medicine_id,
            'purchase',
            $restock_qty,
            null
        );

        if (!$movement_result['success']) {
            throw new Exception('Failed to record stock movement: ' . $movement_result['error']);
        }

        // Auto-resolve low stock alert if applicable
        if ($new_qty > $inventory['reorder_level']) {
            $resolve_query = "
                UPDATE alerts 
                SET is_resolved = 1, resolved_at = NOW()
                WHERE medicine_id = ? 
                AND alert_type = 'low_stock' 
                AND is_resolved = 0
            ";
            $fn->query($resolve_query, [(string)$medicine_id]);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Successfully restocked {$medicine['name']}",
            'medicine_id' => $medicine_id,
            'medicine_name' => $medicine['name'],
            'previous_qty' => $previous_qty,
            'restock_qty' => $restock_qty,
            'new_qty' => $new_qty
        ]);
        exit;
    }

    // ============================================
    // RESTOCK BATCH (MULTIPLE ITEMS)
    // ============================================
    elseif ($action === 'restock_batch') {
        if (!isset($data['items']) || !is_array($data['items'])) {
            throw new Exception('Items array is required');
        }

        $items = $data['items'];
        if (empty($items)) {
            throw new Exception('At least one item must be provided');
        }

        $updated_count = 0;
        $warning_count = 0;
        $details = [];
        $warnings = [];

        foreach ($items as $item) {
            if (!isset($item['medicine_id']) || !isset($item['quantity'])) {
                $warning_count++;
                $warnings[] = 'Skipped item: medicine_id or quantity missing';
                continue;
            }

            $medicine_id = intval($item['medicine_id']);
            $restock_qty = intval($item['quantity']);

            // Validate quantity
            if ($restock_qty <= 0) {
                $warning_count++;
                $warnings[] = "Skipped medicine ID {$medicine_id}: quantity must be greater than 0";
                continue;
            }

            // Get medicine and inventory info
            $medicine = $fn->getMedicineById($medicine_id);
            if (!$medicine) {
                $warning_count++;
                $warnings[] = "Skipped medicine ID {$medicine_id}: medicine not found";
                continue;
            }

            $inventory = $fn->getInventoryByMedicineId($medicine_id);
            if (!$inventory) {
                $warning_count++;
                $warnings[] = "Skipped {$medicine['name']}: inventory record not found";
                continue;
            }

            $previous_qty = $inventory['quantity'];
            $new_qty = $previous_qty + $restock_qty;

            // Update inventory
            $update_result = $fn->updateStock($medicine_id, $new_qty);
            if (!$update_result['success']) {
                $warning_count++;
                $warnings[] = "Failed to update {$medicine['name']}: " . $update_result['error'];
                continue;
            }

            // Record stock movement
            $movement_result = $fn->recordStockMovement(
                $medicine_id,
                'purchase',
                $restock_qty,
                null
            );

            if (!$movement_result['success']) {
                $warning_count++;
                $warnings[] = "Failed to record movement for {$medicine['name']}: " . $movement_result['error'];
                continue;
            }

            // Auto-resolve low stock alert if applicable
            if ($new_qty > $inventory['reorder_level']) {
                $resolve_query = "
                    UPDATE alerts 
                    SET is_resolved = 1, resolved_at = NOW()
                    WHERE medicine_id = ? 
                    AND alert_type = 'low_stock' 
                    AND is_resolved = 0
                ";
                $fn->query($resolve_query, [(string)$medicine_id]);
            }

            $updated_count++;
            $details[] = [
                'medicine_id' => $medicine_id,
                'medicine_name' => $medicine['name'],
                'previous_qty' => $previous_qty,
                'restock_qty' => $restock_qty,
                'new_qty' => $new_qty
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Restocked {$updated_count} medicines" . ($warning_count > 0 ? " ({$warning_count} warnings)" : ''),
            'updated_count' => $updated_count,
            'warning_count' => $warning_count,
            'details' => $details,
            'warnings' => $warnings
        ]);
        exit;
    }

    // ============================================
    // INVALID ACTION
    // ============================================
    else {
        throw new Exception("Unknown action: {$action}");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
?>
