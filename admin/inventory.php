<?php
$page_css = "inventory.css";
include('includes/header.php');
include('includes/sidebar.php');

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$error_message = '';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'summary'; // 'summary' or 'batch'

// ✅ GET INVENTORY DATA WITH BATCH-LEVEL INFORMATION
// Use subquery to calculate batch totals per medicine to avoid nested aggregates
$baseQuery = "
    SELECT 
        m.medicine_id,
        m.name,
        m.sku,
        COALESCE(i.reorder_level, 0) as reorder_level,
        COALESCE(i.expiry_date, '') as summary_expiry_date,
        COALESCE(bd.total_active_qty, i.quantity) as total_active_qty,
        COALESCE(bd.active_batch_count, 0) as active_batch_count,
        COALESCE(bd.expired_batch_count, 0) as expired_batch_count,
        COALESCE(bd.sold_out_batch_count, 0) as sold_out_batch_count,
        COALESCE(bd.earliest_active_expiry, i.expiry_date) as earliest_active_expiry,
        COALESCE(bd.total_batches, 0) as total_batches,
        COALESCE(bd.min_days_to_expiry, 999) as min_days_to_expiry
    FROM medicines m
    LEFT JOIN inventory i ON m.medicine_id = i.medicine_id
    LEFT JOIN (
        SELECT 
            medicine_id,
            SUM(CASE WHEN batch_status != 'sold_out' THEN batch_quantity ELSE 0 END) as total_active_qty,
            COUNT(CASE WHEN batch_status = 'active' THEN 1 END) as active_batch_count,
            COUNT(CASE WHEN batch_status = 'expired' THEN 1 END) as expired_batch_count,
            COUNT(CASE WHEN batch_status = 'sold_out' THEN 1 END) as sold_out_batch_count,
            MIN(CASE WHEN batch_status != 'sold_out' THEN expiry_date ELSE NULL END) as earliest_active_expiry,
            COUNT(DISTINCT batch_id) as total_batches,
            MIN(DATEDIFF(expiry_date, CURDATE())) as min_days_to_expiry
        FROM batches_inventory
        GROUP BY medicine_id
    ) bd ON m.medicine_id = bd.medicine_id
    WHERE 1=1
";

$params = [];

// Search filter
if (!empty($search)) {
    $baseQuery .= " AND (m.name LIKE ? OR m.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Status filter (applied directly in WHERE)
if ($filter_status !== 'all') {
    if ($filter_status === 'in_stock') {
        $baseQuery .= " AND COALESCE(bd.total_active_qty, i.quantity) > COALESCE(i.reorder_level, 0)";
    } elseif ($filter_status === 'low_stock') {
        $baseQuery .= " AND COALESCE(bd.total_active_qty, i.quantity) <= COALESCE(i.reorder_level, 0) AND COALESCE(bd.total_active_qty, i.quantity) > 0";
    } elseif ($filter_status === 'out_of_stock') {
        $baseQuery .= " AND COALESCE(bd.total_active_qty, i.quantity) <= 0";
    } elseif ($filter_status === 'expiring') {
        $baseQuery .= " AND COALESCE(bd.min_days_to_expiry, 999) <= 300 AND COALESCE(bd.min_days_to_expiry, 999) > 0";
    } elseif ($filter_status === 'expired') {
        $baseQuery .= " AND COALESCE(bd.min_days_to_expiry, 999) <= 0";
    }
}

$baseQuery .= " ORDER BY m.name ASC";

$query = $baseQuery;
$inventoryResult = $fn->query($query, $params);
$inventoryItems = $fn->fetchAll($inventoryResult) ?? [];

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['medicine_id'])) {
    $medicine_id = intval($_GET['medicine_id']);
    $deleteResult = $fn->deleteMedicine($medicine_id);
    
    if (is_array($deleteResult) && $deleteResult['success']) {
        header('Location: inventory.php?status=' . $filter_status . '&message=deleted');
        exit;
    } else {
        $error_message = (is_array($deleteResult) && isset($deleteResult['error'])) ? $deleteResult['error'] : 'Failed to delete medicine';
    }
}

// ✅ GET UPDATED INVENTORY STATISTICS (using batch system with subquery)
$statsQuery = "
    SELECT 
        COUNT(DISTINCT medicine_id) as total_items,
        SUM(CASE WHEN total_active_qty > reorder_level THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN total_active_qty <= reorder_level AND total_active_qty > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN total_active_qty <= 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN min_days_to_expiry <= 0 AND min_days_to_expiry IS NOT NULL THEN 1 ELSE 0 END) as expired_medicines,
        SUM(CASE WHEN min_days_to_expiry <= 300 AND min_days_to_expiry > 0 THEN 1 ELSE 0 END) as expiring_medicines
    FROM (
        SELECT
            m.medicine_id,
            COALESCE(i.reorder_level, 0) as reorder_level,
            COALESCE(b.total_active_qty, i.quantity, 0) as total_active_qty,
            COALESCE(b.min_days_to_expiry, 999) as min_days_to_expiry
        FROM medicines m
        LEFT JOIN inventory i ON m.medicine_id = i.medicine_id
        LEFT JOIN (
            SELECT 
                medicine_id,
                SUM(CASE WHEN batch_status != 'sold_out' THEN batch_quantity ELSE 0 END) as total_active_qty,
                MIN(DATEDIFF(expiry_date, CURDATE())) as min_days_to_expiry
            FROM batches_inventory
            GROUP BY medicine_id
        ) b ON m.medicine_id = b.medicine_id
    ) stats
";
$statsResult = $fn->query($statsQuery);
$stats = $fn->fetch($statsResult) ?? ['total_items' => 0, 'in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'expired_medicines' => 0, 'expiring_medicines' => 0];

// ✅ FUNCTION TO DETERMINE MEDICINE STATUS
function getMedicineStatus($item) {
    $qty = $item['total_active_qty'] ?? 0;
    $reorder = $item['reorder_level'] ?? 0;
    $days_to_expiry = $item['min_days_to_expiry'] ?? 999;
    
    if ($days_to_expiry <= 0) {
        return ['status' => 'EXPIRED', 'class' => 'status-critical-9348', 'icon' => 'fas fa-skull-crossbones'];
    } elseif ($days_to_expiry <= 300 && $days_to_expiry > 0) {
        return ['status' => 'EXPIRING_SOON', 'class' => 'status-warning-9348', 'icon' => 'fas fa-hourglass-start'];
    } elseif ($qty <= 0) {
        return ['status' => 'OUT_OF_STOCK', 'class' => 'status-critical-9348', 'icon' => 'fas fa-times-circle'];
    } elseif ($qty <= $reorder) {
        return ['status' => 'LOW_STOCK', 'class' => 'status-warning-9348', 'icon' => 'fas fa-exclamation-triangle'];
    } else {
        return ['status' => 'GOOD', 'class' => 'status-instock-9348', 'icon' => 'fas fa-check-circle'];
    }
}
?>

<div class="main-9348">
    <div class="content-9348">
        <!-- Page Header -->
        <div class="page-intro-9348">
            <div class="page-title-9348">
                <h1>Inventory Management</h1>
                <p>Manage your pharmacy's medicine inventory and stock levels.</p>
            </div>
            <div class="actions-9348">
                <button class="btn-9348 btn-primary-9348" onclick="window.location.href='addMedicine.php'">
                    <i class="fas fa-plus-circle"></i> Add Medicine
                </button>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
            <div class="alert-9348 alert-success-9348" style="margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i>
                <div class="alert-message-9348">
                    <strong>Success!</strong>
                    <p>Medicine deleted successfully.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="alert-9348 alert-error-9348" style="margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-message-9348">
                    <strong>Error!</strong>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards (Batch-Aware) -->
        <div class="stats-grid-9348">
            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Total Items</span>
                    <div class="stat-icon-9348">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                <div class="stat-value-9348"><?php echo $stats['total_items'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span style="color: var(--text-light-9348); font-weight: 400;">tracked medicines</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">In Stock</span>
                    <div class="stat-icon-9348" style="background: #dcfce7; color: var(--success-9348);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-value-9348"><?php echo $stats['in_stock'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span style="color: var(--text-light-9348); font-weight: 400;">adequate stock</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Low Stock</span>
                    <div class="stat-icon-9348" style="background: #fef3c7; color: var(--warning-9348);">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="stat-value-9348"><?php echo $stats['low_stock'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span style="color: var(--text-light-9348); font-weight: 400;">need reorder</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Out of Stock</span>
                    <div class="stat-icon-9348" style="background: #fee2e2; color: var(--danger-9348);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div class="stat-value-9348"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span style="color: var(--text-light-9348); font-weight: 400;">not available</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Expiring Soon</span>
                    <div class="stat-icon-9348" style="background: #fef3c7; color: var(--warning-9348);">
                        <i class="fas fa-hourglass-start"></i>
                    </div>
                </div>
                <div class="stat-value-9348"><?php echo $stats['expiring_medicines'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span style="color: var(--text-light-9348); font-weight: 400;">0-300 days</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Expired</span>
                    <div class="stat-icon-9348" style="background: #fee2e2; color: var(--danger-9348);">
                        <i class="fas fa-skull-crossbones"></i>
                    </div>
                </div>
                <div class="stat-value-9348"><?php echo $stats['expired_medicines'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span style="color: var(--text-light-9348); font-weight: 400;">must remove</span>
                </div>
            </div>
        </div>

        <!-- Inventory Controls -->
        <div class="inventory-controls-9348">
            <div class="control-group-9348">
                <label class="control-label-9348">Search Medicine</label>
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <input type="text" name="search" placeholder="Search by name or SKU..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" style="flex: 1; padding: 0.75rem; border: 1px solid var(--border-9348); border-radius: 6px;">
                    <button type="submit" class="btn-9348 btn-primary-9348">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <div class="control-group-9348">
                <label class="control-label-9348">Filter by Status</label>
                <form method="GET" style="display: flex; gap: 0.5rem;">
                    <select name="status" class="select-input-9348" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Items</option>
                        <option value="in_stock" <?php echo $filter_status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="low_stock" <?php echo $filter_status === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out_of_stock" <?php echo $filter_status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="expiring" <?php echo $filter_status === 'expiring' ? 'selected' : ''; ?>>Expiring Soon (0-300 days)</option>
                        <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Expired (0+ days)</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Inventory Table (Batch-Aware) -->
        <div class="inventory-card-9348">
            <div class="card-header-9348">
                <h3 class="card-title-9348">Medicine Inventory Overview</h3>
                <div style="font-size: 0.75rem; color: var(--text-muted-9348); font-weight: 500;">
                    <?php echo count($inventoryItems); ?> items found
                </div>
            </div>
            <div class="table-responsive-9348">
                <table class="data-table-9348">
                    <thead>
                        <tr>
                            <th>Medicine Name</th>
                            <th>SKU</th>
                            <th>Total Qty</th>
                            <th>Batches</th>
                            <th>Earliest Expiry</th>
                            <th>Days to Expiry</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($inventoryItems) > 0) {
                            foreach ($inventoryItems as $item) {
                                $medicine_id = $item['medicine_id'] ?? '';
                                $name = $item['name'] ?? 'N/A';
                                $sku = $item['sku'] ?? 'N/A';
                                $total_qty = $item['total_active_qty'] ?? 0;
                                $reorder = $item['reorder_level'] ?? 0;
                                $active_batches = $item['active_batch_count'] ?? 0;
                                $expired_batches = $item['expired_batch_count'] ?? 0;
                                $total_batches = $item['total_batches'] ?? 0;
                                $earliest_expiry = $item['earliest_active_expiry'] ?? 'N/A';
                                $days_to_expiry = $item['min_days_to_expiry'] ?? 999;

                                // Get status info
                                $status_info = getMedicineStatus($item);
                                $status_text = $status_info['status'];
                                $status_class = $status_info['class'];
                                $status_icon = $status_info['icon'];

                                // Format expiry display
                                $expiry_display = ($earliest_expiry !== 'N/A' && $earliest_expiry !== null) 
                                    ? date('M d, Y', strtotime($earliest_expiry))
                                    : 'N/A';

                                // Batch summary
                                $batch_summary = $active_batches . ' active';
                                if ($expired_batches > 0) {
                                    $batch_summary .= ', ' . $expired_batches . ' expired';
                                }

                                // Days to expiry badge
                                $days_badge = 'N/A';
                                $days_badge_class = '';
                                if ($days_to_expiry !== null && $days_to_expiry !== 999) {
                                    if ($days_to_expiry <= 0) {
                                        $days_badge = abs($days_to_expiry) . ' days ago';
                                        $days_badge_class = 'days-expired-9348';
                                    } elseif ($days_to_expiry <= 30) {
                                        $days_badge = $days_to_expiry . ' days (URGENT)';
                                        $days_badge_class = 'days-urgent-9348';
                                    } elseif ($days_to_expiry <= 300) {
                                        $days_badge = $days_to_expiry . ' days';
                                        $days_badge_class = 'days-warning-9348';
                                    } else {
                                        $days_badge = $days_to_expiry . ' days';
                                        $days_badge_class = 'days-good-9348';
                                    }
                                }

                                echo "<tr>
                                    <td class='medicine-name-9348'>
                                        <strong>$name</strong>
                                    </td>
                                    <td>$sku</td>
                                    <td>
                                        <strong>$total_qty</strong> units
                                        <br><small style='color: var(--text-muted-9348);'>Min: $reorder</small>
                                    </td>
                                    <td>
                                        <span style='font-size: 0.85rem; padding: 0.35rem 0.6rem; background: var(--bg-9348); border-radius: 4px;'>
                                            $batch_summary
                                        </span>
                                    </td>
                                    <td>
                                        <span style='font-size: 0.85rem;'>$expiry_display</span>
                                    </td>
                                    <td>
                                        <span class='$days_badge_class' style='font-size: 0.85rem; padding: 0.35rem 0.6rem; border-radius: 4px; font-weight: 600;'>
                                            $days_badge
                                        </span>
                                    </td>
                                    <td>
                                        <span class='status-pill-9348 $status_class'>
                                            <i class='$status_icon' style='margin-right: 0.4rem;'></i>$status_text
                                        </span>
                                    </td>
                                    <td class='action-cell-9348'>
                                        <a href='editMedicine.php?id=$medicine_id' class='btn-icon-9348' title='Edit' style='margin-right: 0.5rem;'>
                                            <i class='fas fa-edit'></i>
                                        </a>
                                        <button class='btn-icon-9348 btn-icon-danger-9348' title='Delete' onclick=\"if(confirm('Are you sure you want to delete this medicine? This action cannot be undone.')) { window.location.href='inventory.php?action=delete&medicine_id=$medicine_id&status=$filter_status'; }\">
                                            <i class='fas fa-trash'></i>
                                        </button>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr>
                                <td colspan='8' style='text-align: center; padding: 2rem; color: var(--text-muted-9348);'>
                                    <i class='fas fa-box-open' style='font-size: 2rem; opacity: 0.5; display: block; margin-bottom: 1rem;'></i>
                                    No inventory items found
                                </td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Days to Expiry Badge Styles */
.days-expired-9348 {
    background-color: #fee2e2;
    color: var(--danger-9348);
    font-weight: 600;
}

.days-urgent-9348 {
    background-color: #fee2e2;
    color: var(--danger-9348);
    font-weight: 600;
}

.days-warning-9348 {
    background-color: #fef3c7;
    color: #ca8a04;
    font-weight: 600;
}

.days-good-9348 {
    background-color: #dcfce7;
    color: var(--success-9348);
    font-weight: 600;
}

.status-pill-9348 {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-instock-9348 {
    background-color: #dcfce7;
    color: var(--success-9348);
}

.status-warning-9348 {
    background-color: #fef3c7;
    color: #ca8a04;
}

.status-critical-9348 {
    background-color: #fee2e2;
    color: var(--danger-9348);
}

.medicine-name-9348 {
    font-weight: 500;
    color: var(--text-main-9348);
}
</style>

<?php include('includes/footer.php'); ?>
