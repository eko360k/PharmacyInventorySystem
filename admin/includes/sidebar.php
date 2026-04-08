<aside class="sidebar">
    <div class="brand">
        <div class="logo">R</div>
        <h2>Rosano</h2>
    </div>

    <ul class="nav">
        <li><a href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li><a href="inventory.php"><i class="fas fa-pills"></i> Inventory</a></li>
        <li><a href="sales.php"><i class="fas fa-shopping-cart"></i> Sales</a></li>
        <li><a href="alerts.php"><i class="fas fa-bell"></i> Alerts</a></li>
        <li><a href="report.php"><i class="fas fa-file-alt"></i> Reports</a></li>
    </ul>

    <!-- User section at bottom of sidebar -->
    <div class="sidebar-user-section">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                    $initials = '';
                    if (isset($_SESSION['user_name'])) {
                        $parts = explode(' ', $_SESSION['user_name']);
                        foreach ($parts as $part) {
                            $initials .= strtoupper($part[0]);
                        }
                    }
                    echo substr($initials, 0, 2);
                ?>
            </div>
            <div class="user-details">
                <p class="user-name"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'User'; ?></p>
                <p class="user-role"><?php echo isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role']) : 'Staff'; ?></p>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</aside>