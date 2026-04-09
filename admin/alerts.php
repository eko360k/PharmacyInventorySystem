<?php
// Define page CSS
$page_css = 'alerts.css';

// Include header (which includes init.php and checks session)
include('./includes/header.php');

// Get all active alerts from database
$alerts_result = $fn->getActiveAlerts();
$all_alerts = $fn->fetchAll($alerts_result);

// Separate alerts by type
$low_stock_alerts = [];
$expiry_alerts = [];

foreach ($all_alerts as $alert) {
    if ($alert['alert_type'] === 'low_stock') {
        $low_stock_alerts[] = $alert;
    } elseif ($alert['alert_type'] === 'expiry') {
        $expiry_alerts[] = $alert;
    }
}

// Get inventory data for better display
$low_stock_medicines = [];
$expiring_medicines = [];

// Process low stock alerts
foreach ($low_stock_alerts as $alert) {
    $inventory = $fn->getInventoryByMedicineId($alert['medicine_id']);
    $medicine = $fn->getMedicineById($alert['medicine_id']);
    
    if ($inventory && $medicine) {
        $low_stock_medicines[] = [
            'alert_id' => $alert['alert_id'],
            'medicine_id' => $alert['medicine_id'],
            'name' => $medicine['name'],
            'sku' => $medicine['sku'],
            'quantity' => $inventory['quantity'],
            'reorder_level' => $inventory['reorder_level'],
            'message' => $alert['message'],
            'created_at' => $alert['created_at']
        ];
    }
}

// Process expiry alerts
foreach ($expiry_alerts as $alert) {
    $inventory = $fn->getInventoryByMedicineId($alert['medicine_id']);
    $medicine = $fn->getMedicineById($alert['medicine_id']);
    
    if ($inventory && $medicine) {
        $days_to_expiry = $fn->daysToExpiry($inventory['expiry_date']);
        
        $expiring_medicines[] = [
            'alert_id' => $alert['alert_id'],
            'medicine_id' => $alert['medicine_id'],
            'name' => $medicine['name'],
            'sku' => $medicine['sku'],
            'expiry_date' => $inventory['expiry_date'],
            'days_remaining' => $days_to_expiry,
            'message' => $alert['message'],
            'created_at' => $alert['created_at']
        ];
    }
}

// Sort by urgency
usort($low_stock_medicines, function($a, $b) {
    $a_percent = ($a['quantity'] / $a['reorder_level']) * 100;
    $b_percent = ($b['quantity'] / $b['reorder_level']) * 100;
    return $a_percent <=> $b_percent;
});

usort($expiring_medicines, function($a, $b) {
    return $a['days_remaining'] <=> $b['days_remaining'];
});
?>

<div class="content-9348">
    <!-- Page Header -->
    <div class="page-intro-9348">
        <div class="page-title-9348">
            <h1><i class="fas fa-bell"></i> Alerts & Notifications</h1>
            <p>Monitor low stock and expiring medicines</p>
        </div>
    </div>

    <!-- Statistics Bar -->
    <div class="stats-bar-9348">
        <div class="stat-card-9348">
            <div class="stat-icon-9348" style="background: #fee2e2; color: var(--danger-9348);">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-info-9348">
                <h4><?php echo count($low_stock_medicines); ?></h4>
                <p>Low Stock</p>
            </div>
        </div>

        <div class="stat-card-9348">
            <div class="stat-icon-9348" style="background: #fef3c7; color: var(--warning-9348);">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="stat-info-9348">
                <h4><?php echo count($expiring_medicines); ?></h4>
                <p>Expiring Soon</p>
            </div>
        </div>

        <div class="stat-card-9348">
            <div class="stat-icon-9348" style="background: #dcfce7; color: var(--success-9348);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info-9348">
                <h4><?php echo count($low_stock_medicines) + count($expiring_medicines); ?></h4>
                <p>Total Alerts</p>
            </div>
        </div>
    </div>

    <!-- Low Stock Section -->
    <div class="alert-section-9348">
        <div class="section-header-9348">
            <div class="section-title-9348">
                <i class="fas fa-arrow-trending-down low-stock-icon-9348"></i>
                <h2>Low Stock Medicines</h2>
            </div>
            <?php if (count($low_stock_medicines) > 0): ?>
                <button class="btn-restock-all-9348" onclick="window.restockAll('low_stock')">
                    <i class="fas fa-truck"></i> Restock All
                </button>
            <?php endif; ?>
        </div>

        <?php if (count($low_stock_medicines) > 0): ?>
            <div class="table-container-9348">
                <table class="alert-table-9348">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>SKU</th>
                            <th>Current Stock</th>
                            <th>Min. Level</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_medicines as $medicine): ?>
                            <?php 
                            $stock_percent = ($medicine['quantity'] / $medicine['reorder_level']) * 100;
                            $is_critical = $stock_percent <= 25;
                            ?>
                            <tr>
                                <td>
                                    <div class="medicine-name-9348">
                                        <div class="med-avatar-9348">
                                            <?php echo strtoupper(substr($medicine['name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                            <p><?php echo htmlspecialchars($medicine['sku']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($medicine['sku']); ?></td>
                                <td>
                                    <span class="stock-badge-9348 badge-warning-9348">
                                        <?php echo $medicine['quantity']; ?> units
                                    </span>
                                </td>
                                <td><?php echo $medicine['reorder_level']; ?> units</td>
                                <td>
                                    <div class="progress-bar-9348" style="width: 100px; height: 24px; background: #f3f4f6; border-radius: 4px; overflow: hidden; position: relative;">
                                        <div style="width: <?php echo min($stock_percent, 100); ?>%; height: 100%; background: <?php echo $is_critical ? 'var(--danger-9348)' : 'var(--warning-9348)'; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: 600;">
                                            <?php echo round($stock_percent); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="restock-btn-9348" onclick="window.restockMedicine(<?php echo $medicine['medicine_id']; ?>, 'low_stock')">
                                        <i class="fas fa-redo"></i> Restock
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted-9348);">
                <i class="fas fa-check-circle" style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--success-9348);"></i>
                <p>No low stock alerts. All medicines are well-stocked!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Expiry Alerts Section -->
    <div class="alert-section-9348">
        <div class="section-header-9348">
            <div class="section-title-9348">
                <i class="fas fa-hourglass-end expiry-icon-9348"></i>
                <h2>Expiring Medicines</h2>
            </div>
            <?php if (count($expiring_medicines) > 0): ?>
                <button class="btn-restock-all-9348" onclick="window.restockAll('expiry')">
                    <i class="fas fa-trash"></i> Mark All as Resolved
                </button>
            <?php endif; ?>
        </div>

        <?php if (count($expiring_medicines) > 0): ?>
            <div class="table-container-9348">
                <table class="alert-table-9348">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>SKU</th>
                            <th>Expiry Date</th>
                            <th>Days Remaining</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring_medicines as $medicine): ?>
                            <?php 
                            $days = $medicine['days_remaining'];
                            $is_critical = $days <= 7;
                            $expiry_class = $days <= 0 ? 'critical-9348' : ($is_critical ? 'badge-danger-9348' : 'badge-warning-9348');
                            $expiry_label = $days <= 0 ? 'EXPIRED' : ($days <= 7 ? 'CRITICAL' : 'CAUTION');
                            ?>
                            <tr>
                                <td>
                                    <div class="medicine-name-9348">
                                        <div class="med-avatar-9348">
                                            <?php echo strtoupper(substr($medicine['name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                            <p><?php echo htmlspecialchars($medicine['sku']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($medicine['sku']); ?></td>
                                <td>
                                    <span class="expiry-date-9348">
                                        <?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="days-remaining-9348 <?php echo $days <= 0 ? 'critical-9348' : ''; ?>">
                                        <?php 
                                        if ($days <= 0) {
                                            echo 'EXPIRED';
                                        } elseif ($days == 1) {
                                            echo '1 day';
                                        } else {
                                            echo $days . ' days';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="stock-badge-9348 <?php echo $expiry_class; ?>">
                                        <?php echo $expiry_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="restock-btn-9348" onclick="window.resolveAlert(<?php echo $medicine['alert_id']; ?>)">
                                        <i class="fas fa-check"></i> Resolve
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: var(--text-muted-9348);">
                <i class="fas fa-smile" style="font-size: 2.5rem; margin-bottom: 1rem; color: var(--success-9348);"></i>
                <p>No expiring medicines. Everything looks good!</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Toast Notification -->
<div class="toast-9348" id="alert-toast-9348">
    <i class="fas fa-check-circle" style="color: var(--success-9348); font-size: 1.5rem;"></i>
    <div>
        <div style="font-weight: 700;" id="toast-title-9348">Success</div>
        <div style="font-size: 0.8rem; color: var(--text-muted-9348);" id="toast-message-9348">Action completed successfully.</div>
    </div>
</div>

<?php include('./includes/footer.php'); ?>

<script>
(function() {
    'use strict';

    // DOM Elements
    const toast = document.getElementById('alert-toast-9348');
    const toastTitle = document.getElementById('toast-title-9348');
    const toastMessage = document.getElementById('toast-message-9348');

    // Show Toast Notification
    function showToast(title, message, type = 'success') {
        toastTitle.textContent = title;
        toastMessage.textContent = message;
        
        // Adjust icon and color based on type
        const icon = toast.querySelector('i');
        if (type === 'success') {
            icon.className = 'fas fa-check-circle';
            icon.style.color = 'var(--success-9348)';
        } else if (type === 'warning') {
            icon.className = 'fas fa-exclamation-circle';
            icon.style.color = 'var(--warning-9348)';
        } else if (type === 'error') {
            icon.className = 'fas fa-times-circle';
            icon.style.color = 'var(--danger-9348)';
        }
        
        toast.classList.add('active-9348');
        setTimeout(() => toast.classList.remove('active-9348'), 3000);
    }

    // Restock Medicine - Update inventory and resolve alert
    window.restockMedicine = async (medicineId, alertType) => {
        try {
            const response = await fetch('process-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'restock',
                    medicine_id: medicineId,
                    alert_type: alertType
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Restock Initiated', `Medicine restocking order created`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', result.error || 'Failed to process restock', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        }
    };

    // Resolve Alert
    window.resolveAlert = async (alertId) => {
        try {
            const response = await fetch('process-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'resolve',
                    alert_id: alertId
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Alert Resolved', 'Alert has been marked as resolved', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', result.error || 'Failed to resolve alert', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        }
    };

    // Restock All Medicines of a Type
    window.restockAll = async (alertType) => {
        const action = alertType === 'expiry' ? 'resolve_all' : 'restock_all';
        
        try {
            const response = await fetch('process-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    alert_type: alertType
                })
            });

            const result = await response.json();

            if (result.success) {
                const message = alertType === 'expiry' 
                    ? 'All expiry alerts have been resolved' 
                    : 'Restock orders created for all low stock items';
                showToast('Batch Action Complete', message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error', result.error || 'Failed to process batch action', 'error');
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
        }
    };

})();
</script>
