<?php
$page_css = "inventory.css";
include('includes/header.php');
include('includes/sidebar.php');

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$query = "
    SELECT i.inventory_id, m.medicine_id, m.name, m.sku, i.quantity, i.reorder_level, i.expiry_date
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    WHERE 1=1
";

$params = [];

// Search filter
if (!empty($search)) {
    $query .= " AND (m.name LIKE ? OR m.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Status filter
if ($filter_status !== 'all') {
    if ($filter_status === 'in_stock') {
        $query .= " AND i.quantity > i.reorder_level";
    } elseif ($filter_status === 'low_stock') {
        $query .= " AND i.quantity <= i.reorder_level AND i.quantity > 0";
    } elseif ($filter_status === 'out_of_stock') {
        $query .= " AND i.quantity <= 0";
    } elseif ($filter_status === 'expired') {
        $query .= " AND i.expiry_date < CURDATE()";
    }
}

$query .= " ORDER BY m.name ASC";

$inventoryResult = $fn->query($query, $params);
$inventoryItems = $fn->fetchAll($inventoryResult) ?? [];

// Get inventory statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN quantity > reorder_level THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN quantity <= reorder_level AND quantity > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM inventory
";
$statsResult = $fn->query($statsQuery);
$stats = $fn->fetch($statsResult) ?? ['total_items' => 0, 'in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
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

        <!-- Statistics Cards -->
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
                        <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </form>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="inventory-card-9348">
            <div class="card-header-9348">
                <h3 class="card-title-9348">Medicine Inventory</h3>
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
                            <th>Quantity</th>
                            <th>Reorder Level</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (count($inventoryItems) > 0) {
                            foreach ($inventoryItems as $item) {
                                $name = $item['name'] ?? 'N/A';
                                $sku = $item['sku'] ?? 'N/A';
                                $quantity = $item['quantity'] ?? 0;
                                $reorder = $item['reorder_level'] ?? 0;
                                $expiry = $item['expiry_date'] ?? 'N/A';
                                $inventory_id = $item['inventory_id'] ?? '';

                                // Determine status
                                $isExpired = false;
                                if ($expiry !== 'N/A' && strtotime($expiry) < time()) {
                                    $statusClass = 'status-expired-9348';
                                    $statusText = 'Expired';
                                    $isExpired = true;
                                } elseif ($quantity <= 0) {
                                    $statusClass = 'status-outofstock-9348';
                                    $statusText = 'Out of Stock';
                                } elseif ($quantity <= $reorder) {
                                    $statusClass = 'status-lowstock-9348';
                                    $statusText = 'Low Stock';
                                } else {
                                    $statusClass = 'status-instock-9348';
                                    $statusText = 'In Stock';
                                }

                                $expiryDisplay = ($expiry !== 'N/A') ? date('M d, Y', strtotime($expiry)) : 'N/A';

                                echo "<tr>
                                    <td class='medicine-name-9348'>
                                        <strong>$name</strong>
                                    </td>
                                    <td>$sku</td>
                                    <td>
                                        <strong>$quantity</strong> units
                                    </td>
                                    <td>$reorder units</td>
                                    <td>$expiryDisplay</td>
                                    <td>
                                        <span class='status-pill-9348 $statusClass'>$statusText</span>
                                    </td>
                                    <td class='action-cell-9348'>
                                        <button class='btn-icon-9348' title='Edit' onclick=\"alert('Edit functionality to be implemented')\">
                                            <i class='fas fa-edit'></i>
                                        </button>
                                        <button class='btn-icon-9348 btn-icon-danger-9348' title='Delete' onclick=\"if(confirm('Delete this item?')) { alert('Delete functionality to be implemented'); }\">
                                            <i class='fas fa-trash'></i>
                                        </button>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr>
                                <td colspan='7' style='text-align: center; padding: 2rem; color: var(--text-muted-9348);'>
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

<?php include('includes/footer.php'); ?>
