<?php
$page_css = "index.css";
include('includes/header.php');
include('includes/sidebar.php');

// Get dashboard statistics
$medicinesResult = $fn->query("SELECT COUNT(*) as count FROM medicines");
$medicinesStats = $fn->fetch($medicinesResult) ?? ['count' => 0];

$salesResult = $fn->query("SELECT COUNT(*) as count, SUM(total_amount) as total FROM sales");
$salesStats = $fn->fetch($salesResult) ?? ['count' => 0, 'total' => 0];

$lowStockResult = $fn->getLowStock();
$lowStockCount = $fn->count($lowStockResult);

$expiringResult = $fn->getExpiringMedicines();
$expiringCount = $fn->count($expiringResult);

// Get recent sales for table
$recentSalesResult = $fn->query("
    SELECT s.sale_id, s.total_amount, s.sale_date, u.full_name
    FROM sales s
    LEFT JOIN users u ON s.user_id = u.user_id
    ORDER BY s.sale_date DESC
    LIMIT 10
");
$recentSales = $fn->fetchAll($recentSalesResult) ?? [];

// Get low stock items
$lowStockDetailsResult = $fn->query("
    SELECT m.name, m.sku, i.quantity, i.reorder_level
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    WHERE i.quantity <= i.reorder_level
    LIMIT 5
");
$lowStockItems = $fn->fetchAll($lowStockDetailsResult) ?? [];
?>

<div class="main-9348">

    <!-- Main Content -->
    <main class="main-content-9348">
        <div class="content-9348">
            <!-- Welcome Header -->
            <div class="welcome-header-9348">
                <h1>Dashboard</h1>
                <p>Welcome back! Here's your pharmacy inventory overview.</p>
            </div>

            <!-- KPI Cards -->
            <div class="kpi-grid-9348">
                <!-- Total Medicines -->
                <div class="kpi-card-9348">
                    <div class="kpi-header-9348">
                        <span class="kpi-label-9348">Total Medicines</span>
                        <div class="kpi-icon-wrapper-9348">
                            <i class="fas fa-pills"></i>
                        </div>
                    </div>
                    <div class="kpi-value-9348"><?php echo $medicinesStats['count'] ?? 0; ?></div>
                    <div class="kpi-trend-9348">
                        <span class="trend-up-9348"><i class="fas fa-arrow-up"></i> 5.2%</span>
                        <span style="color: var(--text-light-9348); font-weight: 400;">from last month</span>
                    </div>
                </div>

                <!-- Total Sales -->
                <div class="kpi-card-9348">
                    <div class="kpi-header-9348">
                        <span class="kpi-label-9348">Total Sales</span>
                        <div class="kpi-icon-wrapper-9348" style="background: #fef3c7; color: var(--warning-9348);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="kpi-value-9348"><?php echo $salesStats['count'] ?? 0; ?></div>
                    <div class="kpi-trend-9348">
                        <span class="trend-up-9348"><i class="fas fa-arrow-up"></i> 12.5%</span>
                        <span style="color: var(--text-light-9348); font-weight: 400;">from last month</span>
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="kpi-card-9348">
                    <div class="kpi-header-9348">
                        <span class="kpi-label-9348">Total Revenue</span>
                        <div class="kpi-icon-wrapper-9348" style="background: #dcfce7; color: var(--success-9348);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="kpi-value-9348">₵<?php echo number_format($salesStats['total'] ?? 0, 2); ?></div>
                    <div class="kpi-trend-9348">
                        <span class="trend-up-9348"><i class="fas fa-arrow-up"></i> 8.3%</span>
                        <span style="color: var(--text-light-9348); font-weight: 400;">from last month</span>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="kpi-card-9348">
                    <div class="kpi-header-9348">
                        <span class="kpi-label-9348">Low Stock Items</span>
                        <div class="kpi-icon-wrapper-9348" style="background: #fee2e2; color: var(--danger-9348);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="kpi-value-9348"><?php echo $lowStockCount; ?></div>
                    <div class="kpi-trend-9348">
                        <span class="trend-down-9348"><i class="fas fa-arrow-down"></i> Need attention</span>
                        <span style="color: var(--text-light-9348); font-weight: 400;">requires action</span>
                    </div>
                </div>
            </div>

            <!-- Charts & Tables Grid -->
            <div class="chart-grid-9348">
                <!-- Recent Sales Table -->
                <div class="table-section-9348">
                    <div class="table-header-9348">
                        <h3>Recent Sales</h3>
                        <a href="report.php" class="btn-9348 btn-outline-9348">
                            <i class="fas fa-arrow-right"></i> View All
                        </a>
                    </div>

                    <div class="table-wrapper-9348">
                        <table class="table-9348">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Cashier</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($recentSales) > 0) {
                                    foreach ($recentSales as $sale) {
                                        $saleId = $sale['sale_id'] ?? 'N/A';
                                        $cashier = $sale['full_name'] ?? 'Unknown';
                                        $amount = isset($sale['total_amount']) ? number_format($sale['total_amount'], 2) : '0.00';
                                        $date = isset($sale['sale_date']) ? date('M d, Y', strtotime($sale['sale_date'])) : 'N/A';
                                        echo "<tr>
                                            <td>#$saleId</td>
                                            <td>$cashier</td>
                                            <td>₵$amount</td>
                                            <td>$date</td>
                                            <td><span class='status-pill-9348 pill-success-9348'>Completed</span></td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' style='text-align: center; padding: 2rem;'>No recent sales</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="table-section-9348">
                    <div class="table-header-9348">
                        <h3>Low Stock Alert</h3>
                        <a href="inventory.php" class="btn-9348 btn-outline-9348">
                            <i class="fas fa-arrow-right"></i> View
                        </a>
                    </div>

                    <div class="table-wrapper-9348">
                        <table class="table-9348">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>SKU</th>
                                    <th>Current</th>
                                    <th>Reorder</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($lowStockItems) > 0) {
                                    foreach ($lowStockItems as $item) {
                                        $name = $item['name'] ?? 'Unknown';
                                        $sku = $item['sku'] ?? 'N/A';
                                        $current = $item['quantity'] ?? 0;
                                        $reorder = $item['reorder_level'] ?? 10;
                                        
                                        $status = ($current <= 0) ? 'pill-danger-9348' : 'pill-warning-9348';
                                        $statusText = ($current <= 0) ? 'Critical' : 'Low';
                                        
                                        echo "<tr>
                                            <td>$name</td>
                                            <td>$sku</td>
                                            <td>$current</td>
                                            <td>$reorder</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' style='text-align: center; padding: 2rem;'>All items in stock</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include('includes/footer.php'); ?>