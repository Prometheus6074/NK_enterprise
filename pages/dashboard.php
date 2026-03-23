<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
// Block suppliers from the staff dashboard
if ($_SESSION['user']['role'] === 'supplier') {
    header("Location: supplier.php");
    exit;
}
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';

$user = $_SESSION['user'];

// Handle form submissions
if (isset($_POST['add_product'])) {
    createProduct($connect2db, $_POST, $resultClass, $result);
}

if (isset($_POST['update_product'])) {
    updateProduct($connect2db, $_POST['product_id'], $_POST, $resultClass, $result);
}

if (isset($_POST['delete_product'])) {
    deleteProduct($connect2db, $_POST['product_id'], $resultClass, $result);
}

if (isset($_POST['adjust_inventory'])) {
    adjustInventory($connect2db, $_POST['product_id'], $user['id'], $_POST['new_quantity'], $_POST['notes'], $resultClass, $result);
}

if (isset($_POST['add_category'])) {
    createCategory($connect2db, $_POST, $resultClass, $result);
}

// Get data
$products = getProducts($connect2db);
$categories = getCategories($connect2db);
$lowStockProducts = getLowStockProducts($connect2db);
$recentSales = getSales($connect2db, 5);
$dailySales = getDailySales($connect2db);
$monthlySales = getMonthlySales($connect2db);

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) {
    $products = searchProducts($connect2db, $searchTerm);
}


?>

<!DOCTYPE html>
<html>

<head>
    <title>Inventory Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <div class="dashboard-card">
        <div class="dashboard-header">
            <p>Welcome, <?php echo $user['firstname'] ?> (<?php echo ucfirst($user['role']) ?>)</p>
            <div class="nav-links">
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin.php" class="admin-link">Admin</a>
                <?php endif; ?>
                <a href="pos.php" class="pos-link">POS</a>
                <a href="profile.php" class="profile-link">Profile</a>
                <a href="logout.php" class="logout-link">Logout</a>
            </div>
        </div>

        <?php if (isset($result)): ?>
            <div class="message <?php echo $resultClass; ?>">
                <?php echo $result; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <h3>Total Products</h3>
                <p><?php echo count($products); ?></p>
            </div>
            <div class="stat-card">
                <h3>Low Stock Items</h3>
                <p><?php echo count($lowStockProducts); ?></p>
            </div>
            <div class="stat-card">
                <h3>Today's Sales</h3>
                <p>₱<?php echo number_format($dailySales['total_revenue'] ?: 0, 2); ?></p>
            </div>
            <div class="stat-card">
                <h3>Monthly Sales</h3>
                <p>₱<?php echo number_format($monthlySales['total_revenue'] ?: 0, 2); ?></p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-container">
            <form method="GET" action="">
                <input type="text" name="search" placeholder="Search products by name, SKU, or description..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($searchTerm): ?>
            <div class="search-results">
                <p>Search results for "<?php echo htmlspecialchars($searchTerm); ?>"</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($lowStockProducts)): ?>
            <div class="low-stock-alert">
                <h3>⚠️ Low Stock Alert</h3>
                <?php foreach ($lowStockProducts as $product): ?>
                    <div class="low-stock-item">
                        <strong><?php echo $product['name']; ?></strong> - <?php echo $product['quantity']; ?> left (Min: <?php echo $product['min_quantity']; ?>)
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
            <div class="add-product-section">
                <h2>Add New Product</h2>
                <form method="POST" action="" class="product-form">
                    <div class="form-row">
                        <input type="text" name="sku" placeholder="SKU" required>
                        <input type="text" name="name" placeholder="Product Name" required>
                    </div>
                    <div class="form-row">
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="quantity" placeholder="Quantity" required>
                        <input type="number" name="min_quantity" placeholder="Min Quantity" required>
                        <input type="decimal" name="unit_price" placeholder="Unit Price" step="0.01" required>
                    </div>
                    <div class="form-row">
                        <input type="text" name="supplier" placeholder="Supplier">
                        <input type="text" name="location" placeholder="Location">
                    </div>
                    <textarea name="description" placeholder="Description"></textarea>
                    <button type="submit" name="add_product" class="btn-primary">Add Product</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Products Table -->
        <div class="products-section">
            <h2>Products</h2>
            <div class="table-container">
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr class="<?php echo $product['quantity'] <= $product['min_quantity'] ? 'low-stock' : ''; ?>">
                                <td><?php echo $product['sku']; ?></td>
                                <td><?php echo $product['name']; ?></td>
                                <td><?php echo $product['category_name']; ?></td>
                                <td><?php echo $product['quantity']; ?></td>
                                <td>₱<?php echo number_format($product['unit_price'], 2); ?></td>
                                <td><?php echo $product['location']; ?></td>
                                <td>
                                    <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
                                        <button class="btn-edit" onclick="toggleEditForm(<?php echo $product['id']; ?>)">Edit</button>
                                        <button class="btn-adjust" onclick="toggleAdjustForm(<?php echo $product['id']; ?>)">Adjust</button>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="delete_product" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Edit Form -->
                            <tr id="edit-form-<?php echo $product['id']; ?>" class="edit-form" style="display: none;">
                                <td colspan="7">
                                    <form method="POST" action="">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <div class="form-row">
                                            <input type="text" name="sku" value="<?php echo $product['sku']; ?>" required>
                                            <input type="text" name="name" value="<?php echo $product['name']; ?>" required>
                                            <select name="category_id" required>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $product['category_id'] ? 'selected' : ''; ?>><?php echo $category['name']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="number" name="quantity" value="<?php echo $product['quantity']; ?>" required>
                                            <input type="number" name="min_quantity" value="<?php echo $product['min_quantity']; ?>" required>
                                            <input type="decimal" name="unit_price" value="<?php echo $product['unit_price']; ?>" step="0.01" required>
                                        </div>
                                        <div class="form-row">
                                            <input type="text" name="supplier" value="<?php echo $product['supplier']; ?>">
                                            <input type="text" name="location" value="<?php echo $product['location']; ?>">
                                        </div>
                                        <textarea name="description"><?php echo $product['description']; ?></textarea>
                                        <div class="form-actions">
                                            <button type="submit" name="update_product" class="btn-save">Save</button>
                                            <button type="button" class="btn-cancel" onclick="toggleEditForm(<?php echo $product['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>

                            <!-- Adjust Inventory Form -->
                            <tr id="adjust-form-<?php echo $product['id']; ?>" class="adjust-form" style="display: none;">
                                <td colspan="7">
                                    <form method="POST" action="">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <div class="form-row">
                                            <label>Current Quantity: <?php echo $product['quantity']; ?></label>
                                            <input type="number" name="new_quantity" placeholder="New Quantity" required>
                                            <input type="text" name="notes" placeholder="Notes (optional)">
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" name="adjust_inventory" class="btn-save">Adjust</button>
                                            <button type="button" class="btn-cancel" onclick="toggleAdjustForm(<?php echo $product['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="recent-sales-section">
            <h2>Recent Sales</h2>
            <?php if (empty($recentSales)): ?>
                <p>No sales yet.</p>
            <?php else: ?>
                <div class="sales-list">
                    <?php foreach ($recentSales as $sale): ?>
                        <div class="sale-item">
                            <div class="sale-info">
                                <strong>Sale #<?php echo $sale['id']; ?></strong>
                                <span class="sale-amount">$<?php echo number_format($sale['total_amount'], 2); ?></span>
                                <span class="sale-date"><?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></span>
                                <span class="sale-cashier">by <?php echo $sale['firstname'] . ' ' . $sale['lastname']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleEditForm(productId) {
            const editForm = document.getElementById('edit-form-' + productId);
            editForm.style.display = editForm.style.display === 'none' ? 'table-row' : 'none';
        }

        function toggleAdjustForm(productId) {
            const adjustForm = document.getElementById('adjust-form-' + productId);
            adjustForm.style.display = adjustForm.style.display === 'none' ? 'table-row' : 'none';
        }
    </script>


</body>


</html>