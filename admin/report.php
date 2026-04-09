<?php 
$page_css = "reports.css";
include('includes/header.php'); 
include('includes/sidebar.php');

// Get sales statistics
$salesResult = $fn->query("SELECT COUNT(*) as total_sales, SUM(total_amount) as revenue FROM sales");
$salesStats = $fn->fetch($salesResult) ?: ['total_sales' => 0, 'revenue' => 0];

// Get inventory statistics
$inventoryResult = $fn->query("SELECT COUNT(*) as total_items, SUM(quantity) as total_stock FROM inventory");
$inventoryStats = $fn->fetch($inventoryResult) ?: ['total_items' => 0, 'total_stock' => 0];

// Get critical stock items (using database.php helper method)
$criticalResult = $fn->getLowStock();
$criticalItems = $fn->fetchAll($criticalResult) ?: [];

// Get all sales data for report
$salesResult = $fn->query("
    SELECT sale_id, total_amount, sale_date 
    FROM sales 
    ORDER BY sale_date DESC 
    LIMIT 10
");
$allSales = $fn->fetchAll($salesResult) ?: [];

// Get all inventory data with medicine details
$inventoryResult = $fn->query("
    SELECT m.name, m.sku, i.quantity, i.expiry_date
    FROM inventory i
    JOIN medicines m ON i.medicine_id = m.medicine_id
    LIMIT 10
");
$allInventory = $fn->fetchAll($inventoryResult) ?: [];
?>

<div class="main-9348">
    <div class="content-9348">
        <!-- Page Header -->
        <div class="page-intro-9348">
            <div class="page-title-9348">
                <h1>Reports &amp; Analytics</h1>
                <p>Track your pharmacy's sales and inventory performance.</p>
            </div>
            <div class="actions-9348">
                <button class="btn-9348"><i class="fas fa-calendar-alt"></i> Last 30 Days</button>
                <button class="btn-9348 btn-primary-9348" id="export-trigger-9348"><i class="fas fa-download"></i> Export</button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid-9348">
            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Total Revenue</span>
                    <div class="stat-icon-9348"><i class="fas fa-dollar-sign"></i></div>
                </div>
                <div class="stat-value-9348">$<?php echo number_format($salesStats['revenue'] ?? 0, 2); ?></div>
                <div class="stat-footer-9348">
                    <span class="trend-up-9348"><i class="fas fa-arrow-up"></i> <?php echo $salesStats['total_sales'] ?? 0; ?></span>
                    <span style="color: var(--text-light-9348); font-weight: 400;">total transactions</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Total Medicines</span>
                    <div class="stat-icon-9348"><i class="fas fa-pills"></i></div>
                </div>
                <div class="stat-value-9348"><?php echo $inventoryStats['total_items'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span class="trend-up-9348"><i class="fas fa-arrow-up"></i> <?php echo $inventoryStats['total_stock'] ?? 0; ?></span>
                    <span style="color: var(--text-light-9348); font-weight: 400;">total stock</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Active Inventory</span>
                    <div class="stat-icon-9348" style="background: #ecfdf5; color: var(--success-9348);"><i class="fas fa-boxes"></i></div>
                </div>
                <div class="stat-value-9348"><?php echo $inventoryStats['total_items'] ?? 0; ?></div>
                <div class="stat-footer-9348">
                    <span class="trend-up-9348"><i class="fas fa-arrow-up"></i> In Stock</span>
                    <span style="color: var(--text-light-9348); font-weight: 400;">available items</span>
                </div>
            </div>

            <div class="stat-card-9348">
                <div class="stat-header-9348">
                    <span class="stat-label-9348">Critical Stock</span>
                    <div class="stat-icon-9348" style="background: #fffbeb; color: var(--warning-9348);"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-value-9348"><?php echo count($criticalItems); ?></div>
                <div class="stat-footer-9348">
                    <span class="trend-down-9348"><i class="fas fa-arrow-down"></i> Need Reorder</span>
                    <span style="color: var(--text-light-9348); font-weight: 400;">items below threshold</span>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="dashboard-grid-9348">
            <!-- Sales Report -->
            <div class="card-9348">
                <div class="card-header-9348">
                    <h3 class="card-title-9348">Sales Report</h3>
                    <div style="font-size: 0.75rem; color: var(--text-muted-9348); font-weight: 500;">Recent Transactions</div>
                </div>
                <div class="card-body-9348">
                    <div class="table-responsive-9348">
                        <table class="data-table-9348">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($allSales) > 0) {
                                    foreach ($allSales as $sale) {
                                        $amount = isset($sale['total_amount']) ? number_format($sale['total_amount'], 2) : '0.00';
                                        $date = isset($sale['sale_date']) ? $sale['sale_date'] : 'N/A';
                                        echo "<tr>
                                            <td style=\"font-weight: 600;\">{$sale['sale_id']}</td>
                                            <td>\${$amount}</td>
                                            <td>{$date}</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan=\"3\" style=\"text-align: center; color: var(--text-light-9348);\">No sales data available</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Inventory Report -->
            <div class="card-9348">
                <div class="card-header-9348">
                    <h3 class="card-title-9348">Inventory Overview</h3>
                    <div style="font-size: 0.75rem; color: var(--text-muted-9348); font-weight: 500;">Stock Status</div>
                </div>
                <div class="card-body-9348">
                    <div class="table-responsive-9348">
                        <table class="data-table-9348">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Stock</th>
                                    <th>Expiry</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($allInventory) > 0) {
                                    foreach ($allInventory as $item) {
                                        $name = isset($item['name']) ? $item['name'] : 'N/A';
                                        $qty = isset($item['quantity']) ? $item['quantity'] : '0';
                                        $expiry = isset($item['expiry_date']) ? $item['expiry_date'] : 'N/A';
                                        echo "<tr>
                                            <td style=\"font-weight: 600;\">{$name}</td>
                                            <td>{$qty} units</td>
                                            <td>{$expiry}</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan=\"3\" style=\"text-align: center; color: var(--text-light-9348);\">No inventory data available</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Critical Stock Alert -->
            <div class="card-9348" style="grid-column: 1 / -1;">
                <div class="card-header-9348">
                    <h3 class="card-title-9348">Critical Stock Alert</h3>
                    <a href="#" style="font-size: 0.75rem; color: var(--primary-9348); font-weight: 600; text-decoration: none;">View All</a>
                </div>
                <div class="table-responsive-9348">
                    <table class="data-table-9348">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>SKU</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (count($criticalItems) > 0) {
                                foreach ($criticalItems as $item) {
                                    // Get medicine name from the inventory join
                                    $medResult = $fn->query("SELECT name, sku FROM medicines WHERE medicine_id = ?", [$item['medicine_id']]);
                                    $medicine = $fn->fetch($medResult) ?: ['name' => 'N/A', 'sku' => 'N/A'];
                                    
                                    $name = $medicine['name'];
                                    $sku = $medicine['sku'];
                                    $qty = isset($item['quantity']) ? $item['quantity'] : '0';
                                    $reorder = isset($item['reorder_level']) ? $item['reorder_level'] : '0';
                                    
                                    $status = ($qty <= 0) ? 'badge-danger-9348' : 'badge-warning-9348';
                                    $statusText = ($qty <= 0) ? 'Out of Stock' : 'Low Stock';
                                    
                                    echo "<tr>
                                        <td style=\"font-weight: 600;\">{$name}</td>
                                        <td>{$sku}</td>
                                        <td>{$qty} units</td>
                                        <td>{$reorder} units</td>
                                        <td><span class=\"badge-9348 {$status}\">{$statusText}</span></td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan=\"5\" style=\"text-align: center; color: var(--text-light-9348);\">No critical stock items</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const exportBtn = document.getElementById('export-trigger-9348');
    
    if (exportBtn) {
        exportBtn.addEventListener('click', async () => {
            const originalContent = exportBtn.innerHTML;
            const originalDisabled = exportBtn.disabled;
            
            try {
                // Show loading state
                exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Report...';
                exportBtn.disabled = true;
                exportBtn.style.opacity = '0.7';

                // Send request to generate report
                const response = await fetch('generate-report.php', {
                    method: 'GET',
                    headers: {
                        'Accept': 'text/plain'
                    },
                    credentials: 'include'  // Include session cookies
                });

                // Check if response is ok
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Server error (${response.status}): ${errorText}`);
                }

                // Get the Content-Type to verify it's a text file
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('text/plain')) {
                    const bodyText = await response.text();
                    throw new Error(`Invalid response type. Expected text/plain, got ${contentType}. Response: ${bodyText.substring(0, 200)}`);
                }

                // Get the file content as blob
                const blob = await response.blob();
                
                if (blob.size === 0) {
                    throw new Error('Generated file is empty');
                }

                // Create a temporary download link
                const downloadUrl = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = downloadUrl;
                
                // Extract filename from Content-Disposition header
                const contentDisposition = response.headers.get('content-disposition');
                let filename = 'Pharmacy_Report_' + new Date().toISOString().slice(0, 19).replace(/T/, '_').replace(/:/g, '-') + '.txt';
                
                if (contentDisposition && contentDisposition.includes('filename')) {
                    const filenameMatch = contentDisposition.match(/filename="?([^"]+)"?/);
                    if (filenameMatch && filenameMatch[1]) {
                        filename = filenameMatch[1];
                    }
                }
                
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Clean up object URL after a delay
                setTimeout(() => {
                    window.URL.revokeObjectURL(downloadUrl);
                }, 100);

                // Show success message
                exportBtn.innerHTML = '<i class="fas fa-check-circle"></i> Export Successful!';
                
                // Reset button after 3 seconds
                setTimeout(() => {
                    exportBtn.innerHTML = originalContent;
                    exportBtn.disabled = originalDisabled;
                    exportBtn.style.opacity = '1';
                }, 3000);

            } catch (error) {
                console.error('Export error:', error);
                exportBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Export Failed!';
                exportBtn.style.color = 'var(--danger-9348)';
                
                // Log detailed error for debugging
                console.error('Full error details:', {
                    message: error.message,
                    stack: error.stack,
                    timestamp: new Date().toISOString()
                });
                
                // Reset after 3 seconds
                setTimeout(() => {
                    exportBtn.innerHTML = originalContent;
                    exportBtn.disabled = originalDisabled;
                    exportBtn.style.opacity = '1';
                    exportBtn.style.color = '';
                }, 3000);
            }
        });
    }
})();
</script>

<?php include('includes/footer.php'); ?>