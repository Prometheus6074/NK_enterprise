<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';

// Block suppliers from admin panel
if ($_SESSION['user']['role'] === 'supplier') {
    header("Location: supplier.php");
    exit;
}

// Check if user is admin
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

include '../functions/admin_functions.php';
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';
include '../functions/supplier_functions.php';   // ← NEW

$user = $_SESSION['user'];

// Handle form submissions
if (isset($_POST['create_user'])) {
    createUser($connect2db, $_POST, $resultClass, $result);
}

if (isset($_POST['update_user'])) {
    updateUser($connect2db, $_POST['user_id'], $_POST, $resultClass, $result);
}

if (isset($_POST['delete_user'])) {
    deleteUser($connect2db, $_POST['user_id'], $resultClass, $result);
}

if (isset($_POST['reset_password'])) {
    resetUserPassword($connect2db, $_POST['user_id'], $_POST['new_password'], $resultClass, $result);
}

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

if (isset($_POST['update_settings'])) {
    updateSystemSettings($connect2db, $_POST, $resultClass, $result);
}

if (isset($_POST['backup_database'])) {
    backupDatabase($connect2db, $resultClass, $result);
}

if (isset($_POST['export_data'])) {
    $exportData = exportData($connect2db, $_POST['export_type'], $resultClass, $result, $_POST['start_date'] ?? null, $_POST['end_date'] ?? null);
}

if (isset($_POST['refund_sale'])) {
    refundSale($connect2db, $_POST['sale_id'], $user['id'], $resultClass, $result);
}

// ── NEW: Supplier account management ──────────────────────────────────────────
if (isset($_POST['create_supplier'])) {
    createSupplier($connect2db, $_POST, $resultClass, $result);
}

if (isset($_POST['delete_supplier'])) {
    deleteSupplier($connect2db, $_POST['supplier_id'], $resultClass, $result);
}

if (isset($_POST['reset_supplier_password'])) {
    resetSupplierPassword($connect2db, $_POST['supplier_id'], $_POST['new_supplier_password'], $resultClass, $result);
}

// Get data
$users          = getAllUsers($connect2db);
$products       = getProducts($connect2db);
$categories     = getCategories($connect2db);
$systemStats    = getSystemStats($connect2db);
$systemSettings = getSystemSettings($connect2db);
$recentSales    = getSales($connect2db, 10);
$activityLogs   = getSystemActivityLogs($connect2db, 20);

// Supplier data  ← NEW
$suppliers = getAllSuppliers($connect2db);

// Report data
$salesReport      = getSalesReport($connect2db);
$topProducts      = getTopProductsReport($connect2db);
$staffPerformance = getStaffPerformanceReport($connect2db);

// Search functionality
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) {
    $products = searchProducts($connect2db, $searchTerm);
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <div class="nav-links">
                <a href="dashboard.php" class="dashboard-link">Dashboard</a>
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

        <!-- Admin Navigation Tabs -->
        <div class="admin-tabs">
            <button class="tab-btn active" onclick="showTab('overview', this)">Overview</button>
            <button class="tab-btn" onclick="showTab('users', this)">Staff Management</button>
            <button class="tab-btn" onclick="showTab('suppliers', this)">Suppliers</button>
            <button class="tab-btn" onclick="showTab('products', this)">Product Management</button>
            <button class="tab-btn" onclick="showTab('sales', this)">Sales Management</button>
            <button class="tab-btn" onclick="showTab('reports', this)">Reports</button>
            <button class="tab-btn" onclick="showTab('settings', this)">System Settings</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <h2>System Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p><?php echo array_sum($systemStats['users']); ?></p>
                    <small>Admin: <?php echo $systemStats['users']['admin'] ?? 0; ?> | Manager: <?php echo $systemStats['users']['manager'] ?? 0; ?> | Cashier: <?php echo $systemStats['users']['cashier'] ?? 0; ?></small>
                </div>
                <div class="stat-card">
                    <h3>Total Products</h3>
                    <p><?php echo $systemStats['products']['total_products']; ?></p>
                    <small>Total Stock: <?php echo $systemStats['products']['total_quantity']; ?></small>
                </div>
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <p><?php echo $systemStats['sales']['total_sales']; ?></p>
                    <small>Revenue: ₱<?php echo number_format($systemStats['sales']['total_revenue'] ?: 0, 2); ?></small>
                </div>
                <div class="stat-card">
                    <h3>Today's Activity</h3>
                    <p><?php echo $systemStats['today']['today_sales']; ?> Sales</p>
                    <small>Revenue: ₱<?php echo number_format($systemStats['today']['today_revenue'] ?: 0, 2); ?></small>
                </div>
            </div>

            <?php if ($systemStats['products']['low_stock_count'] > 0): ?>
            <div class="alert-section">
                <div class="low-stock-alert">
                    <h3>⚠️ Low Stock Alert</h3>
                    <p><?php echo $systemStats['products']['low_stock_count']; ?> products need restocking</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="recent-activity">
                <h3>Recent System Activity</h3>
                <div class="activity-list">
                    <?php foreach (array_slice($activityLogs, 0, 5) as $log): ?>
                    <div class="activity-item">
                        <strong><?php echo ucfirst($log['action']); ?></strong>
                        <span><?php echo $log['product_name']; ?></span>
                        <span>by <?php echo $log['firstname'] . ' ' . $log['lastname']; ?></span>
                        <span><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Staff Management Tab -->
        <div id="users" class="tab-content">
            <h2>Staff Management</h2>
            
            <!-- Add User Form -->
            <div class="add-user-section">
                <h3>Add New Staff Member</h3>
                <form method="POST" action="" class="user-form">
                    <div class="form-row">
                        <input type="text" name="firstname" placeholder="First Name" required>
                        <input type="text" name="lastname" placeholder="Last Name" required>
                        <input type="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="form-row">
                        <input type="password" name="password" placeholder="Password" required>
                        <select name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="cashier">Cashier</option>
                        </select>
                    </div>
                    <button type="submit" name="create_user" class="btn-primary">Add Staff Member</button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="users-table-section">
                <h3>Current Staff Members</h3>
                <div class="table-container">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $userItem): ?>
                            <?php if ($userItem['role'] === 'supplier') continue; // suppliers shown in their own tab ?>
                            <tr>
                                <td><?php echo $userItem['firstname'] . ' ' . $userItem['lastname']; ?></td>
                                <td><?php echo $userItem['email']; ?></td>
                                <td><span class="role-badge role-<?php echo $userItem['role']; ?>"><?php echo ucfirst($userItem['role']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($userItem['created_at'])); ?></td>
                                <td>
                                    <button class="btn-edit" onclick="toggleUserEditForm(<?php echo $userItem['id']; ?>)">Edit</button>
                                    <button class="btn-reset" onclick="togglePasswordReset(<?php echo $userItem['id']; ?>)">Reset Password</button>
                                    <?php if ($userItem['id'] != $user['id']): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Edit User Form -->
                            <tr id="user-edit-<?php echo $userItem['id']; ?>" class="edit-form" style="display: none;">
                                <td colspan="5">
                                    <form method="POST" action="">
                                        <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                                        <div class="form-row">
                                            <input type="text" name="firstname" value="<?php echo $userItem['firstname']; ?>" required>
                                            <input type="text" name="lastname" value="<?php echo $userItem['lastname']; ?>" required>
                                            <input type="email" name="email" value="<?php echo $userItem['email']; ?>" required>
                                            <select name="role" required>
                                                <option value="admin"    <?php echo $userItem['role'] == 'admin'    ? 'selected' : ''; ?>>Admin</option>
                                                <option value="manager"  <?php echo $userItem['role'] == 'manager'  ? 'selected' : ''; ?>>Manager</option>
                                                <option value="cashier"  <?php echo $userItem['role'] == 'cashier'  ? 'selected' : ''; ?>>Cashier</option>
                                            </select>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" name="update_user" class="btn-save">Save</button>
                                            <button type="button" class="btn-cancel" onclick="toggleUserEditForm(<?php echo $userItem['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            
                            <!-- Password Reset Form -->
                            <tr id="password-reset-<?php echo $userItem['id']; ?>" class="edit-form" style="display: none;">
                                <td colspan="5">
                                    <form method="POST" action="">
                                        <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                                        <div class="form-row">
                                            <label>New Password:</label>
                                            <input type="password" name="new_password" required>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" name="reset_password" class="btn-save">Reset Password</button>
                                            <button type="button" class="btn-cancel" onclick="togglePasswordReset(<?php echo $userItem['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════
             SUPPLIERS TAB  (NEW)
        ════════════════════════════════════════════════════════════ -->
        <div id="suppliers" class="tab-content">
            <h2>Supplier Management</h2>

            <!-- Create supplier account -->
            <div class="supplier-mgmt-section">
                <h3>Add New Supplier Account</h3>
                <form method="POST" action="" class="user-form">
                    <div class="form-row">
                        <input type="text"  name="firstname" placeholder="First Name" required>
                        <input type="text"  name="lastname"  placeholder="Last Name"  required>
                        <input type="email" name="email"     placeholder="Email"       required>
                    </div>
                    <div class="form-row">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <button type="submit" name="create_supplier" class="btn-primary">Create Supplier Account</button>
                </form>
            </div>

            <!-- Suppliers list -->
            <div class="supplier-mgmt-section">
                <h3>Registered Suppliers</h3>
                <?php if (empty($suppliers)): ?>
                    <p style="color:#6b7280; padding:20px 0;">No supplier accounts yet. Create one above.</p>
                <?php else: ?>
                <div class="table-container">
                    <table class="suppliers-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Products</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sup['firstname'] . ' ' . $sup['lastname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($sup['email']); ?></td>
                                <td>
                                    <span class="product-count-badge">
                                        <?php echo (int)$sup['product_count']; ?> product<?php echo $sup['product_count'] != 1 ? 's' : ''; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($sup['created_at'])); ?></td>
                                <td>
                                    <button class="btn-reset" onclick="toggleSupplierPasswordReset(<?php echo $sup['id']; ?>)">Reset PW</button>
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="supplier_id" value="<?php echo $sup['id']; ?>">
                                        <button
                                            type="submit"
                                            name="delete_supplier"
                                            class="btn-delete"
                                            onclick="return confirm('Delete this supplier and all their products?')"
                                        >Delete</button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Password reset form (inline) -->
                            <tr id="sup-pw-reset-<?php echo $sup['id']; ?>" class="edit-form" style="display:none;">
                                <td colspan="5">
                                    <form method="POST" action="">
                                        <input type="hidden" name="supplier_id" value="<?php echo $sup['id']; ?>">
                                        <div class="form-row">
                                            <label>New Password for <?php echo htmlspecialchars($sup['firstname']); ?>:</label>
                                            <input type="password" name="new_supplier_password" required>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" name="reset_supplier_password" class="btn-save">Reset Password</button>
                                            <button type="button" class="btn-cancel" onclick="toggleSupplierPasswordReset(<?php echo $sup['id']; ?>)">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- ════ END SUPPLIERS TAB ════ -->

        <!-- Product Management Tab -->
        <div id="products" class="tab-content">
            <h2>Product Management</h2>
            
            <!-- Add Product Form -->
            <div class="add-product-section">
                <h3>Add New Product</h3>
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

            <!-- Products Table -->
            <div class="products-table-section">
                <h3>Product Inventory</h3>
                <div class="search-container">
                    <form method="GET" action="">
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit">Search</button>
                    </form>
                </div>
                
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
                                    <button class="btn-edit" onclick="toggleProductEditForm(<?php echo $product['id']; ?>)">Edit</button>
                                    <button class="btn-adjust" onclick="toggleAdjustForm(<?php echo $product['id']; ?>)">Adjust</button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="delete_product" class="btn-delete" onclick="return confirm('Are you sure?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            
                            <!-- Edit Product Form -->
                            <tr id="product-edit-<?php echo $product['id']; ?>" class="edit-form" style="display: none;">
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
                                            <button type="button" class="btn-cancel" onclick="toggleProductEditForm(<?php echo $product['id']; ?>)">Cancel</button>
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
        </div>

        <!-- Sales Management Tab -->
        <div id="sales" class="tab-content">
            <h2>Sales Management</h2>
            
            <div class="sales-table-section">
                <h3>Recent Sales</h3>
                <div class="table-container">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cashier</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td>#<?php echo $sale['id']; ?></td>
                                <td><?php echo $sale['firstname'] . ' ' . $sale['lastname']; ?></td>
                                <td>₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                <td><?php echo ucfirst($sale['payment_method']); ?></td>
                                <td><?php echo $sale['customer_name'] ?: 'N/A'; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></td>
                                <td>
                                    <button class="btn-view" onclick="viewSaleDetails(<?php echo $sale['id']; ?>)">View</button>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                        <button type="submit" name="refund_sale" class="btn-refund" onclick="return confirm('Refund this sale? This will restock all items.')">Refund</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div id="reports" class="tab-content">
            <h2>Reports & Analytics</h2>
            
            <div class="reports-grid">
                <div class="report-section">
                    <h3>Sales Report</h3>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Sales Count</th>
                                    <th>Revenue</th>
                                    <th>Avg Sale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($salesReport, 0, 10) as $report): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($report['date'])); ?></td>
                                    <td><?php echo $report['sales_count']; ?></td>
                                    <td>₱<?php echo number_format($report['revenue'], 2); ?></td>
                                    <td>₱<?php echo number_format($report['avg_amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-section">
                    <h3>Top Selling Products</h3>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                    <th>Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><?php echo $product['name']; ?></td>
                                    <td><?php echo $product['total_sold']; ?></td>
                                    <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                    <td><?php echo $product['sales_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-section">
                    <h3>Staff Performance</h3>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Role</th>
                                    <th>Sales Count</th>
                                    <th>Revenue</th>
                                    <th>Avg Sale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffPerformance as $staff): ?>
                                <tr>
                                    <td><?php echo $staff['firstname'] . ' ' . $staff['lastname']; ?></td>
                                    <td><?php echo ucfirst($staff['role']); ?></td>
                                    <td><?php echo $staff['sales_count']; ?></td>
                                    <td>₱<?php echo number_format($staff['total_revenue'], 2); ?></td>
                                    <td>₱<?php echo number_format($staff['avg_sale_amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Export Data -->
            <div class="export-section">
                <h3>Export Data</h3>
                <form method="POST" action="" class="export-form">
                    <div class="form-row">
                        <select name="export_type" required>
                            <option value="">Select Data Type</option>
                            <option value="products">Products</option>
                            <option value="sales">Sales</option>
                            <option value="users">Users</option>
                        </select>
                        <input type="date" name="start_date" placeholder="Start Date">
                        <input type="date" name="end_date" placeholder="End Date">
                    </div>
                    <button type="submit" name="export_data" class="btn-primary">Export Data</button>
                </form>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <h2>System Settings</h2>
            
            <div class="settings-section">
                <h3>General Settings</h3>
                <form method="POST" action="" class="settings-form">
                    <div class="form-row">
                        <label>Company Name:</label>
                        <input type="text" name="company_name" value="<?php echo $systemSettings['company_name']; ?>">
                    </div>
                    <div class="form-row">
                        <label>Company Email:</label>
                        <input type="email" name="company_email" value="<?php echo $systemSettings['company_email']; ?>">
                    </div>
                    <div class="form-row">
                        <label>Currency:</label>
                        <select name="currency">
                            <option value="PHP" <?php echo $systemSettings['currency'] == 'PHP' ? 'selected' : ''; ?>>PHP (Philippine Peso)</option>
                            <option value="USD" <?php echo $systemSettings['currency'] == 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                            <option value="EUR" <?php echo $systemSettings['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Tax Rate (%):</label>
                        <input type="number" name="tax_rate" value="<?php echo $systemSettings['tax_rate'] * 100; ?>" step="0.1">
                        <small>Current: <?php echo $systemSettings['tax_rate'] * 100; ?>% (PH VAT)</small>
                    </div>
                    <div class="form-row">
                        <label>Low Stock Threshold:</label>
                        <input type="number" name="low_stock_threshold" value="<?php echo $systemSettings['low_stock_threshold']; ?>">
                    </div>
                    <button type="submit" name="update_settings" class="btn-primary">Save Settings</button>
                </form>
            </div>

            <div class="maintenance-section">
                <h3>System Maintenance</h3>
                <div class="maintenance-actions">
                    <form method="POST" action="" style="display: inline;">
                        <button type="submit" name="backup_database" class="btn-primary">Backup Database</button>
                    </form>
                </div>
            </div>
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
        function showTab(tabName, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            if (btn) btn.classList.add('active');
        }
        
        function toggleUserEditForm(userId) {
            const form = document.getElementById('user-edit-' + userId);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }
        
        function togglePasswordReset(userId) {
            const form = document.getElementById('password-reset-' + userId);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }

        function toggleSupplierPasswordReset(supplierId) {
            const form = document.getElementById('sup-pw-reset-' + supplierId);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }
        
        function toggleProductEditForm(productId) {
            const form = document.getElementById('product-edit-' + productId);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }
        
        function toggleAdjustForm(productId) {
            const form = document.getElementById('adjust-form-' + productId);
            form.style.display = form.style.display === 'none' ? 'table-row' : 'none';
        }
        
        function viewSaleDetails(saleId) {
            alert('Sale details for #' + saleId + ' would be displayed here');
        }
        
        function closeModal() {
            document.getElementById('saleDetailsModal').style.display = 'none';
        }

        // Open suppliers tab directly if that's the active tab from URL hash
        document.addEventListener('DOMContentLoaded', function () {
            if (window.location.hash === '#suppliers') {
                showTab('suppliers', document.querySelector('[onclick*="suppliers"]'));
            }
        });
    </script>

</body>

</html>
