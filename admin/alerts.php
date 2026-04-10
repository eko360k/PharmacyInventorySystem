<?php
// Define page CSS
$page_css = 'alerts.css';

// Include header (which includes init.php and checks session)
include('./includes/header.php');

// ✅ GET CURRENT LOW STOCK MEDICINES (Using batch-aware system)
// Fetches medicines where total active batch quantity <= reorder_level
$low_stock_result = $fn->getCurrentLowStockMedicines();
$low_stock_medicines = $fn->fetchAll($low_stock_result) ?: [];

// ✅ GET CRITICAL EXPIRY ALERTS (Two-tier system: EXPIRING_SOON and EXPIRED)
// Uses new getCriticalExpiryAlerts() which queries batches instead of inventory table
$critical_alerts_result = $fn->getCriticalExpiryAlerts();
$critical_alerts = $fn->fetchAll($critical_alerts_result) ?: [];

// Separate alerts by type (EXPIRING_SOON vs EXPIRED)
$expiring_soon_medicines = [];
$expired_medicines = [];

foreach ($critical_alerts as $alert) {
    $alert_data = [
        'batch_id' => $alert['batch_id'] ?? null,
        'medicine_id' => $alert['medicine_id'],
        'name' => $alert['medicine_name'],
        'sku' => $alert['sku'] ?? '',
        'expiry_date' => $alert['expiry_date'],
        'batch_quantity' => $alert['batch_quantity'],
        'days_to_expiry' => $alert['days_to_expiry'],
        'alert_level' => $alert['alert_level'] ?? 'EXPIRED'
    ];
    
    if ($alert['alert_level'] === 'EXPIRED') {
        $expired_medicines[] = $alert_data;
    } else {
        $expiring_soon_medicines[] = $alert_data;
    }
}

// ✅ SORT ALERTS BY URGENCY
// Low stock: Sort by stock percentage (lowest first)
usort($low_stock_medicines, function($a, $b) {
    $a_percent = ($a['quantity'] / $a['reorder_level']) * 100;
    $b_percent = ($b['quantity'] / $b['reorder_level']) * 100;
    return $a_percent <=> $b_percent;
});

// Expiring soon: Sort by days to expiry (soonest first)
usort($expiring_soon_medicines, function($a, $b) {
    return $a['days_to_expiry'] <=> $b['days_to_expiry'];
});

// Expired: Sort by days overdue (oldest first)
usort($expired_medicines, function($a, $b) {
    return $a['days_to_expiry'] <=> $b['days_to_expiry'];
});

// Combine for total count
$all_expiring_medicines = array_merge($expiring_soon_medicines, $expired_medicines);
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
                <i class="fas fa-hourglass-start"></i>
            </div>
            <div class="stat-info-9348">
                <h4><?php echo count($expiring_soon_medicines); ?></h4>
                <p>Expiring Soon (0-300 days)</p>
            </div>
        </div>

        <div class="stat-card-9348">
            <div class="stat-icon-9348" style="background: #fee2e2; color: var(--danger-9348);">
                <i class="fas fa-skull-crossbones"></i>
            </div>
            <div class="stat-info-9348">
                <h4><?php echo count($expired_medicines); ?></h4>
                <p>Already Expired</p>
            </div>
        </div>

        <div class="stat-card-9348">
            <div class="stat-icon-9348" style="background: #dcfce7; color: var(--success-9348);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info-9348">
                <h4><?php echo count($low_stock_medicines) + count($all_expiring_medicines); ?></h4>
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
                            <tr data-medicine-id="<?php echo $medicine['medicine_id']; ?>">
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
                                    <button class="restock-btn-9348" onclick="window.restockMedicine(<?php echo $medicine['medicine_id']; ?>, '<?php echo htmlspecialchars($medicine['name']); ?>')">
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

    <!-- Expiry Alerts Section: EXPIRING SOON -->
    <div class="alert-section-9348">
        <div class="section-header-9348">
            <div class="section-title-9348">
                <i class="fas fa-hourglass-start expiry-icon-9348" style="color: var(--warning-9348);"></i>
                <h2>Expiring Soon (0-300 days remaining)</h2>
            </div>
        </div>

        <?php if (count($expiring_soon_medicines) > 0): ?>
            <div class="table-container-9348">
                <table class="alert-table-9348">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>SKU</th>
                            <th>Batch Qty</th>
                            <th>Expiry Date</th>
                            <th>Days Remaining</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiring_soon_medicines as $medicine): ?>
                            <?php 
                            $days = $medicine['days_to_expiry'];
                            $is_critical = $days <= 30;
                            $expiry_class = $is_critical ? 'badge-danger-9348' : 'badge-warning-9348';
                            $expiry_label = $is_critical ? 'URGENT' : 'CAUTION';
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
                                    <span class="stock-badge-9348 badge-info-9348">
                                        <?php echo $medicine['batch_quantity']; ?> units
                                    </span>
                                </td>
                                <td>
                                    <span class="expiry-date-9348">
                                        <?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="days-remaining-9348">
                                        <?php 
                                        if ($days == 1) {
                                            echo '<strong>1 day</strong>';
                                        } elseif ($days <= 0) {
                                            echo '<strong>' . abs($days) . ' days ago</strong>';
                                        } else {
                                            echo '<strong>' . $days . ' days</strong>';
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
                                    <button class="restock-btn-9348" onclick="window.restockMedicine(<?php echo $medicine['medicine_id']; ?>, '<?php echo htmlspecialchars($medicine['name']); ?>')">
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
                <p>Great! No medicines expiring within 300 days.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Expiry Alerts Section: ALREADY EXPIRED -->
    <div class="alert-section-9348">
        <div class="section-header-9348">
            <div class="section-title-9348">
                <i class="fas fa-skull-crossbones expiry-icon-9348" style="color: var(--danger-9348);"></i>
                <h2>Already Expired (0+ days overdue) - MUST REMOVE</h2>
            </div>
        </div>

        <?php if (count($expired_medicines) > 0): ?>
            <div class="table-container-9348">
                <table class="alert-table-9348">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>SKU</th>
                            <th>Batch Qty</th>
                            <th>Expired Date</th>
                            <th>Days Overdue</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expired_medicines as $medicine): ?>
                            <?php 
                            $days_overdue = abs($medicine['days_to_expiry']);
                            $is_critical_overdue = $days_overdue >= 300;
                            $expiry_class = $is_critical_overdue ? 'critical-9348' : 'badge-danger-9348';
                            $expiry_label = $is_critical_overdue ? 'CRITICAL (10+ months)' : 'EXPIRED';
                            ?>
                            <tr style="background-color: <?php echo $is_critical_overdue ? '#fee2e2' : 'transparent'; ?>;">
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
                                    <span class="stock-badge-9348 badge-danger-9348">
                                        <?php echo $medicine['batch_quantity']; ?> units
                                    </span>
                                </td>
                                <td>
                                    <span class="expiry-date-9348" style="color: var(--danger-9348);">
                                        <?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="days-remaining-9348" style="color: var(--danger-9348);">
                                        <strong><?php echo $days_overdue; ?> days ago</strong>
                                    </span>
                                </td>
                                <td>
                                    <span class="stock-badge-9348 <?php echo $expiry_class; ?>">
                                        <?php echo $expiry_label; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="restock-btn-9348" style="background-color: var(--danger-9348);" onclick="alert('This batch is expired and must be removed from inventory. Please contact supervisor.')">
                                        <i class="fas fa-trash"></i> Remove
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
                <p>Excellent! No expired medicines in inventory.</p>
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

<!-- Single Restock Modal -->
<div class="modal-overlay-9348" id="restock-modal-overlay">
    <div class="modal-9348" id="restock-modal">
        <div class="modal-header-9348">
            <h2>Restock Medicine</h2>
            <button class="modal-close-9348" onclick="window.closeRestockModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body-9348">
            <p id="medicine-name-display" style="font-weight: 600; color: var(--text-main-9348); margin-bottom: 1rem;"></p>
            <div class="form-group-9348">
                <label for="restock-quantity">Quantity to Restock</label>
                <input type="number" id="restock-quantity" placeholder="Enter quantity" min="1">
            </div>
            <div class="form-group-9348">
                <label for="restock-expiry">Expiry Date of This Batch</label>
                <input type="date" id="restock-expiry" required>
            </div>
        </div>
        <div class="modal-footer-9348">
            <button class="btn-cancel-9348" onclick="window.closeRestockModal()">Cancel</button>
            <button class="btn-confirm-9348" onclick="window.submitSingleRestock()">Restock</button>
        </div>
    </div>
</div>

<?php include('./includes/footer.php'); ?>

<style>
/* Modal Styling */
.modal-overlay-9348 {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.modal-overlay-9348.active-9348 {
    display: flex;
}

.modal-9348 {
    background: var(--surface-9348);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header-9348 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-9348);
}

.modal-header-9348 h2 {
    margin: 0;
    font-size: 1.25rem;
    color: var(--text-main-9348);
}

.modal-close-9348 {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--text-muted-9348);
    transition: var(--transition-9348);
}

.modal-close-9348:hover {
    color: var(--text-main-9348);
}

.modal-body-9348 {
    padding: 1.5rem;
}

.form-group-9348 {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.form-group-9348 label {
    font-weight: 600;
    color: var(--text-main-9348);
    font-size: 0.9rem;
}

.form-group-9348 input {
    padding: 0.75rem;
    border: 1px solid var(--border-9348);
    border-radius: 6px;
    font-size: 1rem;
    font-family: inherit;
}

.form-group-9348 input:focus {
    outline: none;
    border-color: var(--primary-9348);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.batch-item-9348 {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-9348);
    border-radius: 8px;
    margin-bottom: 1rem;
}

.batch-item-info-9348 {
    flex: 1;
    min-width: 0;
}

.batch-item-name-9348 {
    font-weight: 600;
    color: var(--text-main-9348);
}

.batch-item-details-9348 {
    font-size: 0.8rem;
    color: var(--text-light-9348);
    margin-top: 0.25rem;
}

.batch-item-input-9348 {
    width: 100px;
}

.batch-item-input-9348 input {
    padding: 0.5rem;
    border: 1px solid var(--border-9348);
    border-radius: 6px;
    width: 100%;
}

.modal-footer-9348 {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--border-9348);
    justify-content: flex-end;
}

.btn-cancel-9348,
.btn-confirm-9348 {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-9348);
    font-size: 0.9rem;
}

.btn-cancel-9348 {
    background: var(--bg-9348);
    color: var(--text-main-9348);
    border: 1px solid var(--border-9348);
}

.btn-cancel-9348:hover {
    background: var(--border-9348);
}

.btn-confirm-9348 {
    background: var(--primary-9348);
    color: white;
}

.btn-confirm-9348:hover {
    background: #4338ca;
}
</style>

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

    // Restock Medicine - Show modal for quantity input
    window.restockMedicine = (medicineId, medicineName) => {
        const medicineNameDisplay = document.getElementById('medicine-name-display');
        const quantityInput = document.getElementById('restock-quantity');
        const modal = document.getElementById('restock-modal-overlay');
        
        medicineNameDisplay.textContent = `Restocking: ${medicineName}`;
        quantityInput.value = '';
        quantityInput.focus();
        
        // Store medicine ID for later use
        window.currentRestockMedicineId = medicineId;
        
        modal.classList.add('active-9348');
    };

    // Close restock modal
    window.closeRestockModal = () => {
        document.getElementById('restock-modal-overlay').classList.remove('active-9348');
    };

    // Submit single restock
    window.submitSingleRestock = async () => {
        const quantity = parseInt(document.getElementById('restock-quantity').value);
        const expiryDate = document.getElementById('restock-expiry').value;
        const medicineId = window.currentRestockMedicineId;
        
        if (!quantity || quantity <= 0) {
            showToast('Invalid Input', 'Please enter a valid quantity', 'warning');
            return;
        }
        
        if (!expiryDate) {
            showToast('Invalid Input', 'Please select an expiry date for this batch', 'warning');
            return;
        }
        
        try {
            const response = await fetch('process-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'restock_single',
                    medicine_id: medicineId,
                    quantity: quantity,
                    expiry_date: expiryDate
                }),
                credentials: 'include'
            });

            // ✅ Check if response is OK before parsing JSON
            if (!response.ok) {
                throw new Error(`Server error: ${response.status} ${response.statusText}`);
            }

            const responseText = await response.text();
            
            // ✅ Validate JSON response
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('Invalid JSON response:', responseText);
                throw new Error('Server returned invalid JSON: ' + responseText.substring(0, 100));
            }

            // ✅ Check for success
            if (result.success === true) {
                showToast('Success', 'Medicine restocked successfully with expiry date: ' + expiryDate, 'success');
                window.closeRestockModal();
                // Auto-refresh after 1.5 seconds
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                // Show the actual error from server
                const errorMessage = result.error || result.message || 'Unknown error occurred';
                showToast('Restock Failed', errorMessage, 'error');
                console.error('Restock error:', result);
            }
        } catch (error) {
            showToast('Error', 'Network error: ' + error.message, 'error');
            console.error('Restock exception:', error);
        }
    };

})();
</script>
