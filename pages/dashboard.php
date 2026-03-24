<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
if ($_SESSION['user']['role'] === 'supplier') {
    header("Location: supplier.php");
    exit;
}
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';

$user = $_SESSION['user'];
$activePage = 'dashboard';

if (isset($_POST['add_product']))      createProduct($connect2db, $_POST, $resultClass, $result);
if (isset($_POST['update_product']))   updateProduct($connect2db, $_POST['product_id'], $_POST, $resultClass, $result);
if (isset($_POST['delete_product']))   deleteProduct($connect2db, $_POST['product_id'], $resultClass, $result);
if (isset($_POST['adjust_inventory'])) adjustInventory($connect2db, $_POST['product_id'], $user['id'], $_POST['new_quantity'], $_POST['notes'], $resultClass, $result);
if (isset($_POST['add_category']))     createCategory($connect2db, $_POST, $resultClass, $result);

$products         = getProducts($connect2db);
$categories       = getCategories($connect2db);
$lowStockProducts = getLowStockProducts($connect2db);
$recentSales      = getSales($connect2db, 5);
$dailySales       = getDailySales($connect2db);
$monthlySales     = getMonthlySales($connect2db);

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) $products = searchProducts($connect2db, $searchTerm);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../css/style.css" />
  <title>Dashboard — NK Ent</title>
</head>
<body>

<div class="app-layout">
  <?php include '../components/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Dashboard</h1>
        <span class="page-header-breadcrumb">Inventory overview &amp; management</span>
      </div>
    </div>

    <div class="page-body">

      <?php if (isset($result)): ?>
      <div class="message <?php echo $resultClass; ?>"><?php echo htmlspecialchars($result); ?></div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
          </div>
          <h3>Total Products</h3>
          <p><?php echo count($products); ?></p>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon" style="background:#fef3c7; color:#d97706;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <h3>Low Stock Items</h3>
          <p><?php echo count($lowStockProducts); ?></p>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon" style="background:#d1fae5; color:#059669;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
          <h3>Today's Sales</h3>
          <p>₱<?php echo number_format($dailySales['total_revenue'] ?: 0, 2); ?></p>
        </div>
        <div class="stat-card">
          <div class="stat-card-icon" style="background:#ede9fe; color:#7c3aed;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
              <line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
          </div>
          <h3>Monthly Sales</h3>
          <p>₱<?php echo number_format($monthlySales['total_revenue'] ?: 0, 2); ?></p>
        </div>
      </div>

      <?php if (!empty($lowStockProducts)): ?>
      <div class="low-stock-alert" style="margin-bottom:20px;">
        <h3>Low Stock Alert</h3>
        <?php foreach ($lowStockProducts as $p): ?>
          <div class="low-stock-item">
            <strong><?php echo htmlspecialchars($p['name']); ?></strong> — <?php echo $p['quantity']; ?> remaining (Min: <?php echo $p['min_quantity']; ?>)
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Search -->
      <div class="search-container">
        <form method="GET" action="">
          <input type="text" name="search" placeholder="Search products by name, SKU, or description..." value="<?php echo htmlspecialchars($searchTerm); ?>">
          <button type="submit">Search</button>
        </form>
      </div>

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
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="quantity" placeholder="Quantity" required>
            <input type="number" name="min_quantity" placeholder="Min Quantity" required>
            <input type="number" name="unit_price" placeholder="Unit Price" step="0.01" required>
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
                <th>SKU</th><th>Name</th><th>Category</th>
                <th>Quantity</th><th>Unit Price</th><th>Location</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
              <tr class="<?php echo $product['quantity'] <= $product['min_quantity'] ? 'low-stock' : ''; ?>">
                <td class="mono"><?php echo htmlspecialchars($product['sku']); ?></td>
                <td><?php echo htmlspecialchars($product['name']); ?></td>
                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                <td><?php echo $product['quantity']; ?></td>
                <td>₱<?php echo number_format($product['unit_price'], 2); ?></td>
                <td><?php echo htmlspecialchars($product['location']); ?></td>
                <td>
                  <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
                  <button class="btn-edit" onclick="toggleEditForm(<?php echo $product['id']; ?>)">Edit</button>
                  <button class="btn-adjust" onclick="toggleAdjustForm(<?php echo $product['id']; ?>)">Adjust</button>
                  <form method="POST" action="" style="display:inline;">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <button type="submit" name="delete_product" class="btn-delete"
                            onclick="return confirm('Delete this product?')">Delete</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <tr id="edit-form-<?php echo $product['id']; ?>" class="edit-form" style="display:none;">
                <td colspan="7">
                  <form method="POST" action="">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="form-row">
                      <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" required>
                      <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                      <select name="category_id" required>
                        <?php foreach ($categories as $cat): ?>
                          <option value="<?php echo $cat['id']; ?>"
                            <?php echo $cat['id'] == $product['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <input type="number" name="quantity"     value="<?php echo $product['quantity']; ?>"     required>
                      <input type="number" name="min_quantity" value="<?php echo $product['min_quantity']; ?>" required>
                      <input type="number" name="unit_price"   value="<?php echo $product['unit_price']; ?>"   step="0.01" required>
                    </div>
                    <div class="form-row">
                      <input type="text" name="supplier" value="<?php echo htmlspecialchars($product['supplier']); ?>">
                      <input type="text" name="location" value="<?php echo htmlspecialchars($product['location']); ?>">
                    </div>
                    <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    <div class="form-actions">
                      <button type="submit" name="update_product" class="btn-save">Save</button>
                      <button type="button" class="btn-cancel" onclick="toggleEditForm(<?php echo $product['id']; ?>)">Cancel</button>
                    </div>
                  </form>
                </td>
              </tr>
              <tr id="adjust-form-<?php echo $product['id']; ?>" class="adjust-form" style="display:none;">
                <td colspan="7">
                  <form method="POST" action="">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="form-row">
                      <label>Current: <?php echo $product['quantity']; ?></label>
                      <input type="number" name="new_quantity" placeholder="New Quantity" required>
                      <input type="text"   name="notes"        placeholder="Notes (optional)">
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
          <p class="text-muted">No sales yet.</p>
        <?php else: ?>
          <div class="sales-list">
            <?php foreach ($recentSales as $sale): ?>
            <div class="sale-item">
              <strong>Sale #<?php echo $sale['id']; ?></strong>
              <span class="sale-amount">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
              <span class="sale-date"><?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></span>
              <span class="sale-cashier">by <?php echo htmlspecialchars($sale['firstname'] . ' ' . $sale['lastname']); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<script>
function toggleEditForm(id) {
  const el = document.getElementById('edit-form-' + id);
  el.style.display = el.style.display === 'none' ? 'table-row' : 'none';
}
function toggleAdjustForm(id) {
  const el = document.getElementById('adjust-form-' + id);
  el.style.display = el.style.display === 'none' ? 'table-row' : 'none';
}
</script>

</body>
</html>
