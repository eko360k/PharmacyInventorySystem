<?php
$page_css = "addMedicine.css";
include('includes/header.php');
include('includes/sidebar.php');

// Get medicine ID from URL
$medicine_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($medicine_id <= 0) {
    die('<div style="padding: 2rem; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i><p>Invalid medicine ID provided.</p></div>');
}

// Load medicine data
$medicineQuery = $fn->query("SELECT * FROM medicines WHERE medicine_id = ?", [$medicine_id]);
$medicine = $fn->fetch($medicineQuery);

if (!$medicine) {
    die('<div style="padding: 2rem; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i><p>Medicine not found.</p></div>');
}

// Load inventory data
$inventoryQuery = $fn->query("SELECT * FROM inventory WHERE medicine_id = ?", [$medicine_id]);
$inventory = $fn->fetch($inventoryQuery);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['medicine_name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $unit_price = trim($_POST['price'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $supplier_id = trim($_POST['supplier_id'] ?? '');
    $reorder_level = trim($_POST['reorder_level'] ?? '10');
    $description = trim($_POST['description'] ?? '');

    // Validation
    if (empty($name)) {
        $error_message = "Medicine name is required.";
    } elseif (empty($sku)) {
        $error_message = "SKU is required.";
    } elseif ($sku !== $medicine['sku'] && $fn->recordExists('medicines', 'sku', $sku)) {
        // Only check for duplicate if SKU was changed
        $error_message = "This SKU already exists. Please use a unique SKU.";
    } elseif (empty($unit_price) || !$fn->validatePrice($unit_price)) {
        $error_message = "Valid price is required.";
    } elseif (empty($quantity) || !$fn->validateStock($quantity)) {
        $error_message = "Valid quantity is required.";
    } elseif (empty($expiry_date) || !$fn->validateExpiryDate($expiry_date)) {
        $error_message = "Valid expiry date is required.";
    } else {
        // Update medicine
        $medicineData = [
            'name' => $name,
            'sku' => $sku,
            'unit_price' => $unit_price,
            'category' => $category,
            'description' => $description,
            'supplier_id' => $supplier_id ?: null
        ];

        $medicineUpdateResult = $fn->updateSafe('medicines', $medicineData, 'medicine_id = ?', [$medicine_id]);

        if ($medicineUpdateResult['success']) {
            // Update inventory
            $inventoryData = [
                'quantity' => $quantity,
                'reorder_level' => $reorder_level,
                'expiry_date' => $expiry_date
            ];

            $inventoryUpdateResult = $fn->updateSafe('inventory', $inventoryData, 'medicine_id = ?', [$medicine_id]);

            if ($inventoryUpdateResult['success']) {
                $success_message = "Medicine updated successfully! Redirecting...";
                echo "<script>setTimeout(function() { window.location.href = 'inventory.php'; }, 2000);</script>";
                // Reload the data
                $medicineQuery = $fn->query("SELECT * FROM medicines WHERE medicine_id = ?", [$medicine_id]);
                $medicine = $fn->fetch($medicineQuery);
                $inventoryQuery = $fn->query("SELECT * FROM inventory WHERE medicine_id = ?", [$medicine_id]);
                $inventory = $fn->fetch($inventoryQuery);
            } else {
                $error_message = $inventoryUpdateResult['error'] ?? "Failed to update inventory record.";
            }
        } else {
            $error_message = $medicineUpdateResult['error'] ?? "Failed to update medicine. Please try again.";
        }
    }
}

// Get suppliers for dropdown
$suppliersResult = $fn->query("SELECT supplier_id, supplier_name FROM suppliers LIMIT 20");
$suppliers = $fn->fetchAll($suppliersResult) ?? [];
?>

<div class="main-9348">
    <div class="content-9348">
        <!-- Page Header -->
        <div class="page-intro-9348">
            <div class="page-title-9348">
                <h1>Edit Medicine</h1>
                <p>Update the details of <?php echo htmlspecialchars($medicine['name'] ?? 'this medicine'); ?></p>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert-9348 alert-success-9348">
                <i class="fas fa-check-circle"></i>
                <div class="alert-message-9348">
                    <strong>Success!</strong>
                    <p><?php echo $success_message; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert-9348 alert-error-9348">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-message-9348">
                    <strong>Error!</strong>
                    <p><?php echo $error_message; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card-9348">
            <form method="POST" enctype="multipart/form-data" id="medicine-form-9348">
                <!-- Basic Information Section -->
                <div class="card-header-9348">
                    <h3 class="card-title-9348">Medicine Information</h3>
                </div>

                <div class="form-grid-9348">
                    <!-- Medicine Name -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Medicine Name</label>
                        <input type="text" name="medicine_name" class="input-9348" placeholder="e.g., Paracetamol 500mg" value="<?php echo htmlspecialchars($medicine['name'] ?? ''); ?>" required>
                        <p class="help-text-9348">Enter the full name of the medicine</p>
                    </div>

                    <!-- SKU -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">SKU</label>
                        <input type="text" name="sku" class="input-9348" placeholder="e.g., MED-PARAC-500" value="<?php echo htmlspecialchars($medicine['sku'] ?? ''); ?>" required>
                        <p class="help-text-9348">Stock Keeping Unit (must be unique)</p>
                    </div>

                    <!-- Category -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Category</label>
                        <select name="category" class="select-9348" required>
                            <option value="">Select Category</option>
                            <option value="Tablets" <?php echo ($medicine['category'] ?? '') === 'Tablets' ? 'selected' : ''; ?>>Tablets</option>
                            <option value="Capsules" <?php echo ($medicine['category'] ?? '') === 'Capsules' ? 'selected' : ''; ?>>Capsules</option>
                            <option value="Syrup" <?php echo ($medicine['category'] ?? '') === 'Syrup' ? 'selected' : ''; ?>>Syrup</option>
                            <option value="Injection" <?php echo ($medicine['category'] ?? '') === 'Injection' ? 'selected' : ''; ?>>Injection</option>
                            <option value="Cream" <?php echo ($medicine['category'] ?? '') === 'Cream' ? 'selected' : ''; ?>>Cream</option>
                            <option value="Lotion" <?php echo ($medicine['category'] ?? '') === 'Lotion' ? 'selected' : ''; ?>>Lotion</option>
                            <option value="Other" <?php echo ($medicine['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <!-- Price -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Unit Price (₵)</label>
                        <input type="number" name="price" class="input-9348" placeholder="0.00" step="0.01" min="0" value="<?php echo htmlspecialchars($medicine['unit_price'] ?? '0'); ?>" required>
                        <p class="help-text-9348">Selling price per unit</p>
                    </div>

                    <!-- Quantity -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Current Quantity</label>
                        <input type="number" name="quantity" class="input-9348" placeholder="0" min="0" value="<?php echo htmlspecialchars($inventory['quantity'] ?? '0'); ?>" required>
                        <p class="help-text-9348">Number of units in stock</p>
                    </div>

                    <!-- Reorder Level -->
                    <div class="form-group-9348">
                        <label class="label-9348">Reorder Level</label>
                        <input type="number" name="reorder_level" class="input-9348" placeholder="10" min="0" value="<?php echo htmlspecialchars($inventory['reorder_level'] ?? '10'); ?>">
                        <p class="help-text-9348">Alert when stock falls below this</p>
                    </div>

                    <!-- Expiry Date -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Expiry Date</label>
                        <input type="date" name="expiry_date" class="input-9348" value="<?php echo htmlspecialchars($inventory['expiry_date'] ?? ''); ?>" required>
                    </div>

                    <!-- Supplier -->
                    <div class="form-group-9348">
                        <label class="label-9348">Supplier</label>
                        <select name="supplier_id" class="select-9348">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo ($medicine['supplier_id'] ?? 0) == $supplier['supplier_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Description Section -->
                <div class="form-section-9348">
                    <h4 class="form-section-title-9348">Additional Information</h4>

                    <div class="form-group-9348 full-width-9348">
                        <label class="label-9348">Description</label>
                        <textarea name="description" class="textarea-9348" placeholder="Enter medicine details, usage instructions, or any other relevant information..."><?php echo htmlspecialchars($medicine['description'] ?? ''); ?></textarea>
                        <p class="help-text-9348">Optional: Add notes about the medicine</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="actions-9348">
                    <button type="button" class="btn-9348 btn-secondary-9348" onclick="window.location.href='inventory.php';">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-9348 btn-primary-9348">
                        <i class="fas fa-save"></i> Update Medicine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
