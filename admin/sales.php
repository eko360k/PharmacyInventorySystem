<?php
// Define page CSS
$page_css = 'addSales.css';

// Include header (which includes init.php and checks session)
include('./includes/header.php');
?>

<div class="content-9348">
    <!-- Page Header -->
    <div class="page-intro-9348">
        <div class="page-title-9348">
            <h1><i class="fas fa-shopping-cart" style="margin-right: 0.5rem;"></i>New Sales Transaction</h1>
            <p>Process a new sale by adding medicines to cart</p>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <div id="alert-container-9348"></div>

    <!-- Main POS Grid -->
    <div class="pos-grid-9348">
        <!-- Products Section -->
        <div class="products-section-9348">
            <h2 style="font-size: 1.125rem; font-weight: 600; color: var(--text-main-9348); margin-bottom: 1rem;">
                <i class="fas fa-pills" style="margin-right: 0.5rem; color: var(--primary-9348);"></i>
                Available Medicines
            </h2>

            <!-- Search Bar -->
            <div class="search-pos-9348">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    id="med-search-9348" 
                    placeholder="Search medicines by name or SKU..."
                    autocomplete="off"
                >
            </div>

            <!-- Medicine Grid -->
            <div id="med-grid-9348" class="medicine-grid-9348">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Cart Section -->
        <div class="cart-section-9348">
            <!-- Cart Header -->
            <div class="cart-header-9348">
                <h2>Shopping Cart</h2>
                <div class="cart-badge-9348" id="cart-count-badge-9348">0</div>
            </div>

            <!-- Empty Cart Placeholder -->
            <div id="empty-cart-placeholder-9348" class="empty-cart-placeholder-9348">
                <i class="fas fa-inbox" style="font-size: 2.5rem; color: var(--border-strong-9348);"></i>
                <p>Cart is empty. Add medicines to continue.</p>
            </div>

            <!-- Cart Items -->
            <div id="cart-items-list-9348" class="cart-items-9348"></div>

            <!-- Cart Summary -->
            <div class="cart-summary-9348">
                <div class="summary-row-9348">
                    <span>Subtotal:</span>
                    <span id="subtotal-9348">₵0.00</span>
                </div>
                <div class="summary-row-9348">
                    <span>Tax (5%):</span>
                    <span id="tax-9348">₵0.00</span>
                </div>
                <div class="summary-total-9348">
                    <span>Total:</span>
                    <span id="grand-total-9348">₵0.00</span>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="payment-section-9348">
                <label class="payment-label-9348">Payment Method:</label>
                <div class="payment-methods-9348">
                    <button class="payment-btn-9348 active-9348" data-method="Cash">
                        <i class="fas fa-money-bill"></i>Cash
                    </button>
                    <button class="payment-btn-9348" data-method="Card">
                        <i class="fas fa-credit-card"></i>Card
                    </button>
                    <button class="payment-btn-9348" data-method="Cheque">
                        <i class="fas fa-receipt"></i>Cheque
                    </button>
                    <button class="payment-btn-9348" data-method="Mobile Money">
                        <i class="fas fa-mobile-alt"></i>Mobile
                    </button>
                </div>
            </div>

            <!-- Complete Sale Button -->
            <button id="complete-sale-btn-9348" class="complete-sale-btn-9348" disabled>
                <i class="fas fa-check-circle"></i>
                Complete Sale
            </button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast-9348" id="toast-9348">
    <i class="fas fa-check-circle" style="color: var(--success-9348); font-size: 1.25rem;"></i>
    <div id="toast-message-9348"></div>
</div>

<?php include('./includes/footer.php'); ?>

<script>
(function() {
    'use strict';

    // All medicines from database (loaded via PHP below)
    const medicines = <?php
        $result = $fn->query("SELECT m.*, i.quantity, i.expiry_date FROM medicines m JOIN inventory i ON m.medicine_id = i.medicine_id WHERE i.quantity > 0");
        $meds = $fn->fetchAll($result) ?? [];
        echo json_encode($meds);
    ?>;

    // State Management
    let cart = [];
    let selectedPayment = 'Cash';
    let searchTerm = '';

    // DOM Elements
    const medGrid = document.getElementById('med-grid-9348');
    const searchInput = document.getElementById('med-search-9348');
    const cartList = document.getElementById('cart-items-list-9348');
    const emptyPlaceholder = document.getElementById('empty-cart-placeholder-9348');
    const cartCountBadge = document.getElementById('cart-count-badge-9348');
    const subtotalEl = document.getElementById('subtotal-9348');
    const taxEl = document.getElementById('tax-9348');
    const grandTotalEl = document.getElementById('grand-total-9348');
    const checkoutBtn = document.getElementById('complete-sale-btn-9348');
    const toast = document.getElementById('toast-9348');
    const alertContainer = document.getElementById('alert-container-9348');
    const paymentBtns = document.querySelectorAll('.payment-btn-9348');

    // Show Toast Notification
    function showToast(message, type = 'success') {
        const toastMessage = document.getElementById('toast-message-9348');
        toastMessage.textContent = message;
        toast.classList.add('show-9348');
        setTimeout(() => toast.classList.remove('show-9348'), 3000);
    }

    // Show Alert
    function showAlert(message, type = 'error') {
        const alertClass = `alert-${type}-9348`;
        const iconClass = type === 'success' ? 'fa-check-circle' : type === 'warning' ? 'fa-exclamation-circle' : 'fa-times-circle';
        
        const alertHTML = `
            <div class="alert-9348 ${alertClass}">
                <i class="fas ${iconClass}"></i>
                <div class="alert-message-9348">
                    <strong>${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                    ${message}
                </div>
            </div>
        `;
        
        alertContainer.innerHTML = alertHTML;
        setTimeout(() => alertContainer.innerHTML = '', 5000);
    }

    // Render Medicines Grid
    function renderMedicines(filter = '') {
        const filtered = medicines.filter(med => {
            const searchLower = filter.toLowerCase();
            return med.name.toLowerCase().includes(searchLower) || 
                   med.sku.toLowerCase().includes(searchLower);
        });

        if (filtered.length === 0) {
            medGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: var(--text-muted-9348);">No medicines found</p>';
            return;
        }

        medGrid.innerHTML = filtered.map(med => {
            const stockClass = med.quantity > med.reorder_level ? 'med-stock-available-9348' : 
                              med.quantity > 0 ? 'med-stock-low-9348' : 'med-stock-out-9348';
            const stockText = med.quantity > 0 ? `${med.quantity} in stock` : 'Out of stock';

            return `
                <div class="med-card-9348">
                    <div class="med-image-9348">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="med-info-9348">
                        <h3>${med.name}</h3>
                        <p>${med.sku}</p>
                    </div>
                    <div class="med-price-9348">₵${parseFloat(med.unit_price).toFixed(2)}</div>
                    <div class="med-stock-9348 ${stockClass}">${stockText}</div>
                    <div class="qty-control-9348">
                        <button class="qty-btn-9348" onclick="window.updateLocalQty(${med.medicine_id}, -1)" ${med.quantity === 0 ? 'disabled' : ''}>−</button>
                        <input type="number" class="qty-input-9348" id="qty-${med.medicine_id}-9348" value="1" min="1" max="${med.quantity}">
                        <button class="qty-btn-9348" onclick="window.updateLocalQty(${med.medicine_id}, 1)" ${med.quantity === 0 ? 'disabled' : ''}>+</button>
                    </div>
                    <button class="add-to-cart-btn-9348" onclick="window.addToCart(${med.medicine_id})" ${med.quantity === 0 ? 'disabled' : ''}>
                        <i class="fas fa-plus"></i> Add to Cart
                    </button>
                </div>
            `;
        }).join('');
    }

    // Update Local Quantity
    window.updateLocalQty = (medicineId, delta) => {
        const input = document.getElementById(`qty-${medicineId}-9348`);
        const med = medicines.find(m => m.medicine_id === medicineId);
        let newVal = parseInt(input.value) + delta;
        
        if (newVal >= 1 && newVal <= med.quantity) {
            input.value = newVal;
        }
    };

    // Add to Cart
    window.addToCart = (medicineId) => {
        const med = medicines.find(m => m.medicine_id === medicineId);
        const qtyInput = document.getElementById(`qty-${medicineId}-9348`);
        const quantity = parseInt(qtyInput.value);

        if (quantity <= 0 || quantity > med.quantity) {
            showAlert('Invalid quantity', 'warning');
            return;
        }

        const existingItem = cart.find(item => item.medicine_id === medicineId);
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            cart.push({
                medicine_id: medicineId,
                name: med.name,
                sku: med.sku,
                unit_price: med.unit_price,
                quantity: quantity
            });
        }

        qtyInput.value = 1;
        updateCartUI();
        showToast(`${med.name} added to cart`);
    };

    // Remove from Cart
    window.removeFromCart = (medicineId) => {
        cart = cart.filter(item => item.medicine_id !== medicineId);
        updateCartUI();
    };

    // Update Cart UI
    function updateCartUI() {
        emptyPlaceholder.style.display = cart.length === 0 ? 'block' : 'none';
        cartList.style.display = cart.length === 0 ? 'none' : 'block';

        // Update badge
        cartCountBadge.textContent = cart.length;

        // Render cart items
        cartList.innerHTML = cart.map(item => {
            const itemTotal = item.unit_price * item.quantity;
            return `
                <div class="cart-item-9348">
                    <div class="cart-item-info-9348">
                        <h4>${item.name}</h4>
                        <p>${item.sku} × ${item.quantity}</p>
                    </div>
                    <div class="cart-item-price-9348">₵${itemTotal.toFixed(2)}</div>
                    <button class="cart-item-remove-9348" onclick="window.removeFromCart(${item.medicine_id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        }).join('');

        // Calculate totals
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const tax = subtotal * 0.05;
        const total = subtotal + tax;

        subtotalEl.textContent = `₵${subtotal.toFixed(2)}`;
        taxEl.textContent = `₵${tax.toFixed(2)}`;
        grandTotalEl.textContent = `₵${total.toFixed(2)}`;

        // Enable/disable checkout button
        checkoutBtn.disabled = cart.length === 0;
    }

    // Payment Method Selection
    paymentBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            paymentBtns.forEach(b => b.classList.remove('active-9348'));
            this.classList.add('active-9348');
            selectedPayment = this.dataset.method;
        });
    });

    // Complete Sale
    checkoutBtn.addEventListener('click', async () => {
        if (cart.length === 0) {
            showAlert('Cart is empty', 'warning');
            return;
        }

        checkoutBtn.disabled = true;
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const tax = subtotal * 0.05;
        const total = subtotal + tax;

        try {
            const response = await fetch('process-sale.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    cart: cart,
                    payment_method: selectedPayment,
                    subtotal: subtotal,
                    tax: tax,
                    total: total
                })
            });

            const result = await response.json();

            if (result.success) {
                showAlert(`Sale #${result.sale_id} completed successfully`, 'success');
                showToast(`Sale processed! Total: ₵${total.toFixed(2)}`);
                
                // Reset cart
                cart = [];
                searchInput.value = '';
                renderMedicines();
                updateCartUI();
                
                // Reset payment method
                paymentBtns.forEach(btn => btn.classList.remove('active-9348'));
                paymentBtns[0].classList.add('active-9348');
                selectedPayment = 'Cash';
            } else {
                showAlert(result.error || 'Failed to process sale', 'error');
            }
        } catch (error) {
            showAlert('Error processing sale: ' + error.message, 'error');
        } finally {
            checkoutBtn.disabled = cart.length === 0;
        }
    });

    // Search Input Event
    searchInput.addEventListener('input', (e) => {
        searchTerm = e.target.value;
        renderMedicines(searchTerm);
    });

    // Initialize
    renderMedicines();
})();
</script>
