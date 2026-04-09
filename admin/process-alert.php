<?php
/**
 * ALERTS PROCESSING ENDPOINT
 * Handles AJAX POST requests from alerts.php
 * Processes alert resolution and restock operations
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
if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request data. Action required.'
    ]);
    exit;
}

try {
    $action = $data['action'];

    // ============================================
    // RESOLVE SINGLE ALERT
    // ============================================
    if ($action === 'resolve') {
        if (!isset($data['alert_id'])) {
            throw new Exception('Alert ID is required');
        }

        $alert_id = intval($data['alert_id']);

        // Resolve the alert
        $result = $fn->resolveAlert($alert_id);

        if ($result['success']) {
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
    // RESOLVE ALL ALERTS BY TYPE
    // ============================================
    elseif ($action === 'resolve_all') {
        if (!isset($data['alert_type'])) {
            throw new Exception('Alert type is required');
        }

        $alert_type = $data['alert_type']; // 'low_stock' or 'expiry'

        // Get all alerts of this type
        $alerts_result = $fn->getActiveAlerts();
        $all_alerts = $fn->fetchAll($alerts_result);

        $resolved_count = 0;
        $failed_count = 0;
        $type_alerts = [];

        // Filter alerts by type
        foreach ($all_alerts as $alert) {
            if ($alert['alert_type'] === $alert_type) {
                $type_alerts[] = $alert;
            }
        }

        // If no alerts of this type, return success
        if (empty($type_alerts)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'No ' . $alert_type . ' alerts to resolve',
                'resolved' => 0,
                'failed' => 0
            ]);
            exit;
        }

        // Resolve each alert
        foreach ($type_alerts as $alert) {
            $result = $fn->resolveAlert($alert['alert_id']);
            if ($result['success']) {
                $resolved_count++;
            } else {
                $failed_count++;
                error_log("Failed to resolve alert ID {$alert['alert_id']}: " . $result['error']);
            }
        }

        // Return results
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Resolved {$resolved_count} alerts" . ($failed_count > 0 ? " ({$failed_count} failed)" : ''),
            'resolved' => $resolved_count,
            'failed' => $failed_count,
            'total_processed' => count($type_alerts)
        ]);
        exit;

    // ============================================
    // RESTOCK SINGLE MEDICINE
    // ============================================
    elseif ($action === 'restock') {
        if (!isset($data['medicine_id'])) {
            throw new Exception('Medicine ID is required');
        }

        $medicine_id = intval($data['medicine_id']);
        $alert_type = $data['alert_type'] ?? 'low_stock';

        // Get medicine and inventory info
        $medicine = $fn->getMedicineById($medicine_id);
        if (!$medicine) {
            throw new Exception('Medicine not found');
        }

        $inventory = $fn->getInventoryByMedicineId($medicine_id);
        if (!$inventory) {
            throw new Exception('Inventory record not found');
        }

        // Calculate suggested restock quantity
        $suggested_qty = max(
            $inventory['reorder_level'] * 2,  // 2x reorder level
            50  // Minimum 50 units
        );

        // Log restock request with calculated quantity
        $movement_result = $fn->recordStockMovement(
            $medicine_id,
            'purchase',
            $suggested_qty,
            null
        );

        if ($movement_result['success']) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => "Restock request created for {$medicine['name']}",
                'medicine_id' => $medicine_id,
                'medicine_name' => $medicine['name'],
                'current_stock' => $inventory['quantity'],
                'suggested_qty' => $suggested_qty
            ]);
            exit;
        } else {
            throw new Exception('Failed to create restock request: ' . $movement_result['error']);
        }
    }

    // ============================================
    // RESTOCK ALL LOW STOCK MEDICINES
    // ============================================
    elseif ($action === 'restock_all') {
        try {
            // Get all low stock alerts
            $alerts_result = $fn->getActiveAlerts();
            $all_alerts = $fn->fetchAll($alerts_result);

            $restock_count = 0;
            $failed_count = 0;
            $low_stock_alerts = [];

            // Filter and collect low stock alerts
            foreach ($all_alerts as $alert) {
                if ($alert['alert_type'] === 'low_stock') {
                    $low_stock_alerts[] = $alert;
                }
            }

            // If no low stock alerts, return success message
            if (empty($low_stock_alerts)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'No low stock items requiring restock at this time',
                    'restock_count' => 0,
                    'failed' => 0
                ]);
                exit;
            }

            // Process each low stock alert
            foreach ($low_stock_alerts as $alert) {
                $medicine = $fn->getMedicineById($alert['medicine_id']);
                
                if (!$medicine) {
                    $failed_count++;
                    error_log("Warning: Medicine not found for alert ID {$alert['alert_id']}");
                    continue;
                }

                // Get inventory to determine reorder quantity
                $inventory = $fn->getInventoryByMedicineId($alert['medicine_id']);
                if (!$inventory) {
                    $failed_count++;
                    error_log("Warning: Inventory not found for medicine ID {$alert['medicine_id']}");
                    continue;
                }

                // Calculate suggested reorder quantity
                $suggested_qty = max(
                    $inventory['reorder_level'] * 2,  // 2x reorder level
                    50  // Minimum 50 units
                );

                // Record restock request with calculated quantity
                $movement_result = $fn->recordStockMovement(
                    $alert['medicine_id'],
                    'purchase',
                    $suggested_qty,
                    null
                );
                
                if ($movement_result['success']) {
                    $restock_count++;
                } else {
                    $failed_count++;
                    error_log("Failed to create restock movement for medicine ID {$alert['medicine_id']}: " . $movement_result['error']);
                }
            }

            // Return results
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => "Restock requests created for {$restock_count} medicines" . ($failed_count > 0 ? " ({$failed_count} failed)" : ''),
                'restock_count' => $restock_count,
                'failed' => $failed_count,
                'total_processed' => count($low_stock_alerts)
            ]);
            exit;

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Error processing batch restock: ' . $e->getMessage()
            ]);
            exit;
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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;
?>
