<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';

$user = $_SESSION['user'];

// Handle form submissions
if (isset($_POST['process_sale'])) {
    $cartItems = json_decode($_POST['cart_items'], true);
    $paymentMethod = $_POST['payment_method'];
    $customerName = $_POST['customer_name'] ?: null;
    $notes = $_POST['notes'] ?: null;
    
    $saleId = processSale($connect2db, $user['id'], $cartItems, $paymentMethod, $customerName, $notes, $resultClass, $result);
    
    if ($saleId) {
        // Clear cart after successful sale
        unset($_SESSION['cart']);
    }
}

if (isset($_POST['add_to_cart'])) {
    $productId = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    $product = getProduct($connect2db, $productId);
    
    if ($product['quantity'] >= $quantity) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = [
                'product_id' => $productId,
                'sku' => $product['sku'],
                'name' => $product['name'],
                'quantity' => $quantity,
                'unit_price' => $product['unit_price']
            ];
        }
        
        $resultClass = "success";
        $result = "Added to cart";
    } else {
        $resultClass = "error";
        $result = "Insufficient stock";
    }
}

if (isset($_POST['update_cart'])) {
    $productId = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $productId) {
            $product = getProduct($connect2db, $productId);
            if ($product['quantity'] >= $quantity) {
                $item['quantity'] = $quantity;
                $resultClass = "success";
                $result = "Cart updated";
            } else {
                $resultClass = "error";
                $result = "Insufficient stock";
            }
            break;
        }
    }
}

if (isset($_POST['remove_from_cart'])) {
    $productId = $_POST['product_id'];
    
    $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($productId) {
        return $item['product_id'] != $productId;
    });
    
    $resultClass = "success";
    $result = "Removed from cart";
}

if (isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
    $resultClass = "success";
    $result = "Cart cleared";
}

// Get data
$products = getProducts($connect2db);
$recentSales = getSales($connect2db, 10);
$cart = $_SESSION['cart'] ?? [];

// Calculate cart total
$cartTotal = 0;
foreach ($cart as $item) {
    $cartTotal += $item['quantity'] * $item['unit_price'];
}

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) {
    $products = searchProducts($connect2db, $searchTerm);
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Point of Sale</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <div class="pos-container">
        <div class="pos-header">
            <h1>Point of Sale</h1>
            <div class="nav-links">
                <a href="dashboard.php" class="dashboard-link">Dashboard</a>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin.php" class="admin-link">Admin</a>
                <?php endif; ?>
                <a href="profile.php" class="profile-link">Profile</a>
                <a href="logout.php" class="logout-link">Logout</a>
            </div>
        </div>

        <?php if (isset($result)): ?>
        <div class="message <?php echo $resultClass; ?>">
            <?php echo $result; ?>
        </div>
        <?php endif; ?>

        <div class="pos-main">
            <!-- Product Search and List -->
            <div class="products-panel">
                <div class="search-container">
                    <form method="GET" action="">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>

                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-info">
                            <h4><?php echo $product['name']; ?></h4>
                            <p class="sku">SKU: <?php echo $product['sku']; ?></p>
                            <p class="price">₱<?php echo number_format($product['unit_price'], 2); ?></p>
                            <p class="stock <?php echo $product['quantity'] <= $product['min_quantity'] ? 'low-stock' : ''; ?>">
                                Stock: <?php echo $product['quantity']; ?>
                            </p>
                        </div>
                        <form method="POST" action="" class="add-to-cart-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <div class="quantity-input">
                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                            </div>
                            <button type="submit" name="add_to_cart" class="btn-add-cart" 
                                    <?php echo $product['quantity'] <= 0 ? 'disabled' : ''; ?>>
                                Add to Cart
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart and Checkout -->
            <div class="cart-panel">
                <div class="cart-header">
                    <h2>Shopping Cart</h2>
                    <?php if (!empty($cart)): ?>
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" name="clear_cart" class="btn-clear" onclick="return confirm('Clear entire cart?')">Clear</button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($cart)): ?>
                    <div class="empty-cart">
                        <p>Your cart is empty</p>
                    </div>
                <?php else: ?>
                    <div class="cart-items">
                        <?php foreach ($cart as $item): ?>
                        <div class="cart-item">
                            <div class="item-info">
                                <h4><?php echo $item['name']; ?></h4>
                                <p class="sku"><?php echo $item['sku']; ?></p>
                                <p class="unit-price">₱<?php echo number_format($item['unit_price'], 2); ?> each</p>
                            </div>
                            <div class="item-actions">
                                <form method="POST" action="" class="update-quantity-form">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="quantity-field">
                                    <button type="submit" name="update_cart" class="btn-update">Update</button>
                                </form>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" name="remove_from_cart" class="btn-remove">Remove</button>
                                </form>
                            </div>
                            <div class="item-total">
                                ₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-summary">
                        <div class="cart-total">
                            <strong>Total: ₱<?php echo number_format($cartTotal, 2); ?></strong>
                        </div>
                    </div>

                    <!-- Checkout Form -->
                    <div class="checkout-form">
                        <h3>Checkout</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="cart_items" value='<?php echo json_encode($cart); ?>'>
                            
                            <div class="form-row">
                                <label>Customer Name (Optional):</label>
                                <input type="text" name="customer_name" placeholder="Enter customer name">
                            </div>
                            
                            <div class="form-row">
                                <label>Payment Method:</label>
                                <select name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="credit">Credit</option>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <label>Notes (Optional):</label>
                                <textarea name="notes" placeholder="Add notes..."></textarea>
                            </div>
                            
                            <button type="submit" name="process_sale" class="btn-checkout">
                                Process Sale - $<?php echo number_format($cartTotal, 2); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="recent-sales-section">
            <h2>Recent Sales</h2>
            <?php if (empty($recentSales)): ?>
                <p>No sales yet.</p>
            <?php else: ?>
                <div class="sales-table-container">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Cashier</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td>#<?php echo $sale['id']; ?></td>
                                <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo ucfirst($sale['payment_method']); ?></td>
                                <td><?php echo $sale['firstname'] . ' ' . $sale['lastname']; ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($sale['created_at'])); ?></td>
                                <td>
                                    <button class="btn-view" onclick="viewSaleDetails(<?php echo $sale['id']; ?>)">View</button>
                                    <?php if ($user['role'] === 'admin'): ?>
                                    <button class="btn-refund" onclick="refundSale(<?php echo $sale['id']; ?>)">Refund</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sale Details Modal -->
    <div id="saleDetailsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Sale Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="saleDetailsContent"></div>
        </div>
    </div>

    <script>
        function viewSaleDetails(saleId) {
            fetch('pos.php?sale_details=' + saleId)
                .then(response => response.text())
                .then(html => {
                    // This would need to be implemented with AJAX
                    document.getElementById('saleDetailsContent').innerHTML = 'Sale details for #' + saleId;
                    document.getElementById('saleDetailsModal').style.display = 'block';
                });
        }
        
        function closeModal() {
            document.getElementById('saleDetailsModal').style.display = 'none';
        }
        
        function refundSale(saleId) {
            if (confirm('Are you sure you want to refund this sale? This will restock all items.')) {
                // Submit refund form
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="refund_sale" value="' + saleId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-update cart when quantity changes
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-field');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });
        });
    </script>

</body>

</html>
