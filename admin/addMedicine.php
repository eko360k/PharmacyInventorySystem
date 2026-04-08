<?php
$page_css = "addMedicine.css";
include('includes/header.php');
include('includes/sidebar.php');

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
    } elseif ($fn->recordExists('medicines', 'sku', $sku)) {
        $error_message = "This SKU already exists. Please use a unique SKU.";
    } elseif (empty($unit_price) || !$fn->validatePrice($unit_price)) {
        $error_message = "Valid price is required.";
    } elseif (empty($quantity) || !$fn->validateStock($quantity)) {
        $error_message = "Valid quantity is required.";
    } elseif (empty($expiry_date) || !$fn->validateExpiryDate($expiry_date)) {
        $error_message = "Valid expiry date is required.";
    } else {
        // Insert medicine into database
        $data = [
            'name' => $name,
            'sku' => $sku,
            'unit_price' => $unit_price,
            'category' => $category,
            'description' => $description,
            'supplier_id' => $supplier_id ?: null
        ];

        $medicineResult = $fn->insertSafe('medicines', $data);

        if ($medicineResult['success']) {
            $medicine_id = $medicineResult['insert_id'];

            // Insert into inventory
            $inventoryData = [
                'medicine_id' => $medicine_id,
                'quantity' => $quantity,
                'reorder_level' => $reorder_level,
                'expiry_date' => $expiry_date
            ];

            $inventoryResult = $fn->insertSafe('inventory', $inventoryData);

            if ($inventoryResult['success']) {
                $success_message = "Medicine added successfully! Redirecting...";
                echo "<script>setTimeout(function() { window.location.href = 'inventory.php'; }, 2000);</script>";
            } else {
                // Rollback: delete the medicine that was just inserted
                $fn->deleteSafe('medicines', 'medicine_id = ?', [$medicine_id]);
                $error_message = $inventoryResult['error'] ?? "Failed to add inventory record.";
            }
        } else {
            $error_message = $medicineResult['error'] ?? "Failed to add medicine. Please try again.";
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
                <h1>Add New Medicine</h1>
                <p>Add a new medicine to your pharmacy inventory.</p>
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
                        <input type="text" name="medicine_name" class="input-9348" placeholder="e.g., Paracetamol 500mg" required>
                        <p class="help-text-9348">Enter the full name of the medicine</p>
                    </div>

                    <!-- SKU -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">SKU</label>
                        <input type="text" name="sku" class="input-9348" placeholder="e.g., MED-PARAC-500" required>
                        <p class="help-text-9348">Stock Keeping Unit (must be unique)</p>
                    </div>

                    <!-- Category -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Category</label>
                        <select name="category" class="select-9348" required>
                            <option value="">Select Category</option>
                            <option value="Tablets">Tablets</option>
                            <option value="Capsules">Capsules</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Cream">Cream</option>
                            <option value="Lotion">Lotion</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Price -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Unit Price (₵)</label>
                        <input type="number" name="price" class="input-9348" placeholder="0.00" step="0.01" min="0" required>
                        <p class="help-text-9348">Selling price per unit</p>
                    </div>

                    <!-- Quantity -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Initial Quantity</label>
                        <input type="number" name="quantity" class="input-9348" placeholder="0" min="0" required>
                        <p class="help-text-9348">Number of units to add</p>
                    </div>

                    <!-- Reorder Level -->
                    <div class="form-group-9348">
                        <label class="label-9348">Reorder Level</label>
                        <input type="number" name="reorder_level" class="input-9348" placeholder="10" min="0" value="10">
                        <p class="help-text-9348">Alert when stock falls below this</p>
                    </div>

                    <!-- Expiry Date -->
                    <div class="form-group-9348">
                        <label class="label-9348 label-required-9348">Expiry Date</label>
                        <input type="date" name="expiry_date" class="input-9348" required>
                    </div>

                    <!-- Supplier -->
                    <div class="form-group-9348">
                        <label class="label-9348">Supplier</label>
                        <select name="supplier_id" class="select-9348">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['supplier_id']; ?>">
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
                        <textarea name="description" class="textarea-9348" placeholder="Enter medicine details, usage instructions, or any other relevant information..."></textarea>
                        <p class="help-text-9348">Optional: Add notes about the medicine</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="actions-9348">
                    <button type="button" class="btn-9348 btn-secondary-9348" onclick="window.location.href='inventory.php';">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn-9348 btn-primary-9348">
                        <i class="fas fa-save"></i> Add Medicine
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
