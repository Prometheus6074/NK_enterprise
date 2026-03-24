<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';

if ($_SESSION['user']['role'] === 'supplier') { header("Location: supplier.php"); exit; }
if ($_SESSION['user']['role'] !== 'admin')    { header("Location: dashboard.php"); exit; }

include '../functions/admin_functions.php';
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';
include '../functions/supplier_functions.php';
include '../functions/purchase_order_functions.php';

$user       = $_SESSION['user'];
$activePage = 'admin';

// Staff management
if (isset($_POST['create_user']))       createUser($connect2db, $_POST, $resultClass, $result);
if (isset($_POST['update_user']))       updateUser($connect2db, $_POST['user_id'], $_POST, $resultClass, $result);
if (isset($_POST['delete_user']))       deleteUser($connect2db, $_POST['user_id'], $resultClass, $result);
if (isset($_POST['reset_password']))    resetUserPassword($connect2db, $_POST['user_id'], $_POST['new_password'], $resultClass, $result);
// Product management
if (isset($_POST['add_product']))       createProduct($connect2db, $_POST, $resultClass, $result);
if (isset($_POST['update_product']))    updateProduct($connect2db, $_POST['product_id'], $_POST, $resultClass, $result);
if (isset($_POST['delete_product']))    deleteProduct($connect2db, $_POST['product_id'], $resultClass, $result);
if (isset($_POST['adjust_inventory']))  adjustInventory($connect2db, $_POST['product_id'], $user['id'], $_POST['new_quantity'], $_POST['notes'], $resultClass, $result);
if (isset($_POST['add_category']))      createCategory($connect2db, $_POST, $resultClass, $result);
// System
if (isset($_POST['update_settings']))   updateSystemSettings($connect2db, $_POST, $resultClass, $result);
if (isset($_POST['backup_database']))   backupDatabase($connect2db, $resultClass, $result);
if (isset($_POST['export_data']))       $exportData = exportData($connect2db, $_POST['export_type'], $resultClass, $result, $_POST['start_date'] ?? null, $_POST['end_date'] ?? null);
if (isset($_POST['refund_sale']))       refundSale($connect2db, $_POST['sale_id'], $user['id'], $resultClass, $result);
// Supplier management
if (isset($_POST['create_supplier']))         createSupplier($connect2db, $_POST, $resultClass, $result);
if (isset($_POST['delete_supplier']))         deleteSupplier($connect2db, $_POST['supplier_id'], $resultClass, $result);
if (isset($_POST['reset_supplier_password'])) resetSupplierPassword($connect2db, $_POST['supplier_id'], $_POST['new_supplier_password'], $resultClass, $result);
// Purchase Orders
if (isset($_POST['create_purchase_order'])) {
    $poSupplierId = (int)($_POST['po_supplier_id'] ?? 0);
    $poNotes      = $_POST['po_notes'] ?? '';
    $productIds   = $_POST['po_product_id']   ?? [];
    $quantities   = $_POST['po_quantity']      ?? [];
    $prices       = $_POST['po_unit_price']    ?? [];
    $names        = $_POST['po_product_name']  ?? [];
    $skus         = $_POST['po_product_sku']   ?? [];
    $poItems = [];
    foreach ($productIds as $i => $pid) {
        $qty = (int)($quantities[$i] ?? 0);
        if ($qty <= 0) continue;
        $poItems[] = ['product_id' => (int)$pid, 'quantity' => $qty, 'unit_price' => (float)($prices[$i]??0), 'product_name' => $names[$i]??'', 'product_sku' => $skus[$i]??''];
    }
    if (empty($poItems)) { $resultClass = 'error'; $result = 'Please select at least one product with a quantity greater than 0.'; }
    else createPurchaseOrder($connect2db, $user['id'], $poSupplierId, $poItems, $poNotes, $resultClass, $result);
}
if (isset($_POST['confirm_purchase_order'])) confirmPurchaseOrder($connect2db, (int)$_POST['po_id'], $user['id'], $resultClass, $result);
if (isset($_POST['cancel_purchase_order']))  cancelPurchaseOrder($connect2db, (int)$_POST['po_id'], $resultClass, $result);

// Fetch data
$users          = getAllUsers($connect2db);
$products       = getProducts($connect2db);
$categories     = getCategories($connect2db);
$systemStats    = getSystemStats($connect2db);
$systemSettings = getSystemSettings($connect2db);
$recentSales    = getSales($connect2db, 10);
$activityLogs   = getSystemActivityLogs($connect2db, 20);
$suppliers      = getAllSuppliers($connect2db);
$salesReport    = getSalesReport($connect2db);
$topProducts    = getTopProductsReport($connect2db);
$staffPerformance = getStaffPerformanceReport($connect2db);

$purchaseOrders     = getPurchaseOrders($connect2db);
$selectedSupplierId = isset($_GET['catalog_supplier']) ? (int)$_GET['catalog_supplier'] : null;
$supplierCatalog    = $selectedSupplierId ? getSupplierCatalogForAdmin($connect2db, $selectedSupplierId) : [];
$selectedSupplierInfo = null;
if ($selectedSupplierId) {
    foreach ($suppliers as $s) { if ((int)$s['id'] === $selectedSupplierId) { $selectedSupplierInfo = $s; break; } }
}

$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
if ($searchTerm) $products = searchProducts($connect2db, $searchTerm);

$pendingPoCount  = count(array_filter($purchaseOrders, fn($o) => $o['status'] === 'pending'));
$pendingOrders   = array_values(array_filter($purchaseOrders, fn($o) => $o['status'] === 'pending'));
$confirmedOrders = array_values(array_filter($purchaseOrders, fn($o) => $o['status'] === 'confirmed'));
$cancelledOrders = array_values(array_filter($purchaseOrders, fn($o) => $o['status'] === 'cancelled'));
$otherOrders     = array_merge($confirmedOrders, $cancelledOrders);
usort($otherOrders, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../css/style.css" />
  <title>Admin Panel — NK Ent</title>
</head>
<body>

<div class="app-layout">
  <?php include '../components/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Admin Panel</h1>
        <span class="page-header-breadcrumb">System management &amp; reports</span>
      </div>
    </div>

    <div class="page-body">

      <?php if (isset($result)): ?>
      <div class="message <?php echo $resultClass; ?>"><?php echo htmlspecialchars($result); ?></div>
      <?php endif; ?>

      <!-- Tabs -->
      <div class="admin-tabs">
        <button class="tab-btn active"  onclick="showTab('overview',        this)">Overview</button>
        <button class="tab-btn"         onclick="showTab('users',           this)">Staff</button>
        <button class="tab-btn"         onclick="showTab('suppliers',       this)">Suppliers</button>
        <button class="tab-btn"         onclick="showTab('purchase-orders', this)">
          Purchase Orders
          <?php if ($pendingPoCount > 0): ?>
          <span class="po-nav-badge"><?php echo $pendingPoCount; ?></span>
          <?php endif; ?>
        </button>
        <button class="tab-btn"         onclick="showTab('products',        this)">Products</button>
        <button class="tab-btn"         onclick="showTab('sales',           this)">Sales</button>
        <button class="tab-btn"         onclick="showTab('reports',         this)">Reports</button>
        <button class="tab-btn"         onclick="showTab('settings',        this)">Settings</button>
      </div>

      <!-- ── OVERVIEW ── -->
      <div id="overview" class="tab-content active">
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <h3>Total Users</h3>
            <p><?php echo array_sum($systemStats['users']); ?></p>
            <small>
              Admin: <?php echo $systemStats['users']['admin']??0; ?> &middot;
              Manager: <?php echo $systemStats['users']['manager']??0; ?> &middot;
              Cashier: <?php echo $systemStats['users']['cashier']??0; ?>
            </small>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#fef3c7;color:#d97706;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
              </svg>
            </div>
            <h3>Total Products</h3>
            <p><?php echo $systemStats['products']['total_products']; ?></p>
            <small>Total Stock: <?php echo $systemStats['products']['total_quantity']; ?></small>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#d1fae5;color:#059669;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
              </svg>
            </div>
            <h3>Total Sales</h3>
            <p><?php echo $systemStats['sales']['total_sales']; ?></p>
            <small>Revenue: ₱<?php echo number_format($systemStats['sales']['total_revenue']??0, 2); ?></small>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#ede9fe;color:#7c3aed;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
              </svg>
            </div>
            <h3>Today's Activity</h3>
            <p><?php echo $systemStats['today']['today_sales']; ?> Sales</p>
            <small>Revenue: ₱<?php echo number_format($systemStats['today']['today_revenue']??0, 2); ?></small>
          </div>
        </div>

        <?php if ($systemStats['products']['low_stock_count'] > 0): ?>
        <div class="alert-section">
          <div class="low-stock-alert">
            <h3>Low Stock Alert</h3>
            <p><?php echo $systemStats['products']['low_stock_count']; ?> products need restocking</p>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($pendingPoCount > 0): ?>
        <div class="alert-section">
          <div class="low-stock-alert" style="border-left-color:var(--brand);">
            <h3>Incoming Stock</h3>
            <p>
              <?php echo $pendingPoCount; ?> purchase order<?php echo $pendingPoCount!==1?'s':''; ?> pending confirmation.
              <a href="#" onclick="showTab('purchase-orders',document.querySelector('[onclick*=\'purchase-orders\']'));return false;"
                 style="color:var(--brand);font-weight:600;">View</a>
            </p>
          </div>
        </div>
        <?php endif; ?>

        <div class="recent-activity">
          <h3>Recent System Activity</h3>
          <div class="activity-list">
            <?php foreach (array_slice($activityLogs, 0, 5) as $log): ?>
            <div class="activity-item">
              <strong><?php echo ucfirst($log['action']); ?></strong>
              <span><?php echo htmlspecialchars($log['product_name']); ?></span>
              <span>by <?php echo htmlspecialchars($log['firstname'].' '.$log['lastname']); ?></span>
              <span class="activity-time"><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- ── STAFF ── -->
      <div id="users" class="tab-content">
        <div class="add-user-section">
          <h3>Add New Staff Member</h3>
          <form method="POST" action="" class="user-form">
            <div class="form-row">
              <input type="text"  name="firstname" placeholder="First Name" required>
              <input type="text"  name="lastname"  placeholder="Last Name"  required>
              <input type="email" name="email"     placeholder="Email"       required>
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

        <div class="users-table-section">
          <h3>Current Staff Members</h3>
          <div class="table-container">
            <table class="users-table">
              <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($users as $userItem): ?>
                <?php if ($userItem['role'] === 'supplier') continue; ?>
                <tr>
                  <td><?php echo htmlspecialchars($userItem['firstname'].' '.$userItem['lastname']); ?></td>
                  <td><?php echo htmlspecialchars($userItem['email']); ?></td>
                  <td><span class="role-badge role-<?php echo $userItem['role']; ?>"><?php echo ucfirst($userItem['role']); ?></span></td>
                  <td><?php echo date('M j, Y', strtotime($userItem['created_at'])); ?></td>
                  <td>
                    <button class="btn-edit"  onclick="toggleUserEditForm(<?php echo $userItem['id']; ?>)">Edit</button>
                    <button class="btn-reset" onclick="togglePasswordReset(<?php echo $userItem['id']; ?>)">Reset PW</button>
                    <?php if ($userItem['id'] != $user['id']): ?>
                    <form method="POST" action="" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                      <button type="submit" name="delete_user" class="btn-delete"
                              onclick="return confirm('Delete this user?')">Delete</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <tr id="user-edit-<?php echo $userItem['id']; ?>" class="edit-form" style="display:none;">
                  <td colspan="5">
                    <form method="POST" action="">
                      <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                      <div class="form-row">
                        <input type="text"  name="firstname" value="<?php echo htmlspecialchars($userItem['firstname']); ?>" required>
                        <input type="text"  name="lastname"  value="<?php echo htmlspecialchars($userItem['lastname']); ?>"  required>
                        <input type="email" name="email"     value="<?php echo htmlspecialchars($userItem['email']); ?>"     required>
                        <select name="role" required>
                          <option value="admin"   <?php echo $userItem['role']==='admin'  ?'selected':''; ?>>Admin</option>
                          <option value="manager" <?php echo $userItem['role']==='manager'?'selected':''; ?>>Manager</option>
                          <option value="cashier" <?php echo $userItem['role']==='cashier'?'selected':''; ?>>Cashier</option>
                        </select>
                      </div>
                      <div class="form-actions">
                        <button type="submit" name="update_user" class="btn-save">Save</button>
                        <button type="button" class="btn-cancel" onclick="toggleUserEditForm(<?php echo $userItem['id']; ?>)">Cancel</button>
                      </div>
                    </form>
                  </td>
                </tr>
                <tr id="password-reset-<?php echo $userItem['id']; ?>" class="edit-form" style="display:none;">
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

      <!-- ── SUPPLIERS ── -->
      <div id="suppliers" class="tab-content">
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

        <div class="supplier-mgmt-section">
          <h3>Registered Suppliers</h3>
          <?php if (empty($suppliers)): ?>
            <p class="text-muted" style="padding:20px 0;">No supplier accounts yet.</p>
          <?php else: ?>
          <div class="table-container">
            <table class="suppliers-table">
              <thead><tr><th>Name</th><th>Email</th><th>Products</th><th>Registered</th><th>Actions</th></tr></thead>
              <tbody>
                <?php foreach ($suppliers as $sup): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($sup['firstname'].' '.$sup['lastname']); ?></strong></td>
                  <td><?php echo htmlspecialchars($sup['email']); ?></td>
                  <td><span class="product-count-badge"><?php echo (int)$sup['product_count']; ?> product<?php echo $sup['product_count']!=1?'s':''; ?></span></td>
                  <td><?php echo date('M j, Y', strtotime($sup['created_at'])); ?></td>
                  <td>
                    <a href="?catalog_supplier=<?php echo $sup['id']; ?>"
                       class="btn-view"
                       onclick="event.preventDefault();window.location='?catalog_supplier=<?php echo $sup['id']; ?>';setTimeout(()=>showTab('purchase-orders',document.querySelector('[onclick*=\'purchase-orders\']')),100);">
                       Browse Catalog
                    </a>
                    <button class="btn-reset" onclick="toggleSupplierPasswordReset(<?php echo $sup['id']; ?>)">Reset PW</button>
                    <form method="POST" action="" style="display:inline;">
                      <input type="hidden" name="supplier_id" value="<?php echo $sup['id']; ?>">
                      <button type="submit" name="delete_supplier" class="btn-delete"
                              onclick="return confirm('Delete this supplier and all their products?')">Delete</button>
                    </form>
                  </td>
                </tr>
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

      <!-- ── PURCHASE ORDERS ── -->
      <div id="purchase-orders" class="tab-content">

        <!-- Browse Supplier Catalog -->
        <div class="po-section">
          <div class="po-section-header">
            <h3>Browse Supplier Catalog</h3>
            <?php if ($selectedSupplierInfo): ?>
            <span class="po-section-meta">
              Viewing: <strong><?php echo htmlspecialchars($selectedSupplierInfo['firstname'].' '.$selectedSupplierInfo['lastname']); ?></strong>
              &middot; <?php echo count($supplierCatalog); ?> product<?php echo count($supplierCatalog)!==1?'s':''; ?>
            </span>
            <?php endif; ?>
          </div>

          <form method="GET" action="" class="po-supplier-select-form">
            <div class="form-row" style="align-items:center;gap:12px;">
              <select name="catalog_supplier" class="po-supplier-dropdown" onchange="this.form.submit()">
                <option value="">— Select a supplier to browse their catalog —</option>
                <?php foreach ($suppliers as $s): ?>
                <option value="<?php echo $s['id']; ?>" <?php echo ($selectedSupplierId==$s['id'])?'selected':''; ?>>
                  <?php echo htmlspecialchars($s['firstname'].' '.$s['lastname']); ?>
                  (<?php echo (int)$s['product_count']; ?> products)
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>

          <?php if (!$selectedSupplierId): ?>
          <div class="po-select-prompt">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/>
              <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
              <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/>
            </svg>
            <p>Choose a supplier above to view their product catalog and create a purchase order.</p>
          </div>

          <?php elseif (empty($supplierCatalog)): ?>
          <div class="po-select-prompt">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
            <p>This supplier has no products listed yet.</p>
          </div>

          <?php else: ?>
          <form method="POST" action="" id="poForm">
            <input type="hidden" name="po_supplier_id" value="<?php echo $selectedSupplierId; ?>">
            <div class="table-container">
              <table class="po-catalog-table">
                <thead>
                  <tr>
                    <th class="po-check-col">
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                           fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                      </svg>
                    </th>
                    <th>Image</th><th>SKU</th><th>Product</th><th>Category</th>
                    <th>Available</th><th>Unit Price</th><th>Order Qty</th><th>Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($supplierCatalog as $sp): ?>
                  <tr class="po-catalog-row" id="po-row-<?php echo $sp['id']; ?>">
                    <td class="po-check-col">
                      <input type="checkbox" class="po-check" data-row="<?php echo $sp['id']; ?>"
                             onchange="togglePoRow(this, <?php echo $sp['id']; ?>)">
                    </td>
                    <td>
                      <?php if ($sp['image_path']): ?>
                        <img src="../<?php echo htmlspecialchars($sp['image_path']); ?>"
                             alt="<?php echo htmlspecialchars($sp['name']); ?>" class="sp-thumb">
                      <?php else: ?>
                        <div class="sp-no-img">—</div>
                      <?php endif; ?>
                    </td>
                    <td class="mono"><?php echo htmlspecialchars($sp['sku']); ?></td>
                    <td>
                      <strong><?php echo htmlspecialchars($sp['name']); ?></strong>
                      <?php if ($sp['description']): ?>
                      <br><small class="po-desc-preview"><?php echo htmlspecialchars(mb_substr($sp['description'],0,70)); ?><?php echo mb_strlen($sp['description'])>70?'…':''; ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($sp['category']?:'—'); ?></td>
                    <td><span class="po-avail"><?php echo number_format((int)$sp['quantity_available']); ?></span></td>
                    <td class="po-price-cell">₱<?php echo number_format((float)$sp['unit_price'],2); ?></td>
                    <td>
                      <input type="hidden" name="po_product_id[]"   value="<?php echo $sp['id']; ?>">
                      <input type="hidden" name="po_product_name[]" value="<?php echo htmlspecialchars($sp['name']); ?>">
                      <input type="hidden" name="po_product_sku[]"  value="<?php echo htmlspecialchars($sp['sku']); ?>">
                      <input type="hidden" name="po_unit_price[]"   value="<?php echo $sp['unit_price']; ?>">
                      <input type="number" name="po_quantity[]" value="0" min="0"
                             class="po-qty-input" id="qty-<?php echo $sp['id']; ?>"
                             data-price="<?php echo $sp['unit_price']; ?>"
                             data-row="<?php echo $sp['id']; ?>"
                             oninput="onQtyChange(this,<?php echo $sp['id']; ?>)" disabled>
                    </td>
                    <td class="po-subtotal-cell" id="sub-<?php echo $sp['id']; ?>">₱0.00</td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="po-form-footer">
              <div class="po-notes-field">
                <label for="poNotes">Notes (optional)</label>
                <textarea name="po_notes" id="poNotes" rows="2" placeholder="Add any notes for this purchase order..."></textarea>
              </div>
              <div class="po-total-panel">
                <div class="po-total-stats">
                  <span>Selected: <strong id="poItemCount">0</strong> line items</span>
                  <span class="po-grand-total-label">Grand Total: <strong id="poGrandTotal">₱0.00</strong></span>
                </div>
                <button type="submit" name="create_purchase_order" class="btn-primary po-submit-btn"
                        id="poSubmitBtn" disabled>Create Purchase Order</button>
              </div>
            </div>
          </form>
          <?php endif; ?>
        </div>

        <!-- Incoming Stock -->
        <div class="po-section">
          <div class="po-section-header">
            <h3>
              Incoming Stock
              <?php if ($pendingPoCount > 0): ?>
              <span class="po-incoming-badge"><?php echo $pendingPoCount; ?> Pending</span>
              <?php endif; ?>
            </h3>
          </div>

          <?php if (empty($pendingOrders)): ?>
          <div class="po-select-prompt" style="padding:28px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <p>No pending purchase orders. All incoming stock is confirmed.</p>
          </div>
          <?php else: ?>
          <div class="table-container">
            <table class="po-orders-table">
              <thead>
                <tr><th>PO #</th><th>Supplier</th><th>Items</th><th>Total Amount</th>
                    <th>Created By</th><th>Date</th><th>Status</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($pendingOrders as $po): ?>
                <tr>
                  <td><strong class="mono">#<?php echo $po['id']; ?></strong></td>
                  <td><?php echo htmlspecialchars($po['supplier_firstname'].' '.$po['supplier_lastname']); ?></td>
                  <td><?php echo $po['item_count']; ?> item<?php echo $po['item_count']!=1?'s':''; ?></td>
                  <td class="po-price-cell">₱<?php echo number_format($po['total_amount'],2); ?></td>
                  <td><?php echo htmlspecialchars($po['admin_firstname'].' '.$po['admin_lastname']); ?></td>
                  <td><?php echo date('M j, Y g:i A', strtotime($po['created_at'])); ?></td>
                  <td><span class="po-status-badge status-<?php echo $po['status']; ?>"><?php echo ucfirst($po['status']); ?></span></td>
                  <td class="po-action-cell">
                    <button class="btn-view" onclick="togglePoDetails(<?php echo $po['id']; ?>)">Details</button>
                    <form method="POST" action="" style="display:inline;"
                          onsubmit="return confirm('Confirm PO #<?php echo $po['id']; ?>? This will add all matched SKUs to the main inventory.')">
                      <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                      <button type="submit" name="confirm_purchase_order" class="btn-po-confirm">Confirm</button>
                    </form>
                    <form method="POST" action="" style="display:inline;"
                          onsubmit="return confirm('Cancel PO #<?php echo $po['id']; ?>? This cannot be undone.')">
                      <input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">
                      <button type="submit" name="cancel_purchase_order" class="btn-po-cancel">Cancel</button>
                    </form>
                  </td>
                </tr>
                <tr id="po-details-<?php echo $po['id']; ?>" class="po-items-row" style="display:none;">
                  <td colspan="8">
                    <?php $poFull = getPurchaseOrderWithItems($connect2db, $po['id']); ?>
                    <?php if ($poFull && !empty($poFull['items'])): ?>
                    <div class="po-items-inner">
                      <table class="po-items-table">
                        <thead><tr><th>SKU</th><th>Product</th><th>Qty Ordered</th><th>Unit Price</th><th>Line Total</th></tr></thead>
                        <tbody>
                          <?php foreach ($poFull['items'] as $item): ?>
                          <tr>
                            <td class="mono"><?php echo htmlspecialchars($item['product_sku']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo number_format($item['quantity']); ?></td>
                            <td>₱<?php echo number_format($item['unit_price'],2); ?></td>
                            <td>₱<?php echo number_format($item['total_price'],2); ?></td>
                          </tr>
                          <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                          <tr class="po-items-total-row">
                            <td colspan="4" style="text-align:right;font-weight:700;">Order Total:</td>
                            <td style="font-weight:700;">₱<?php echo number_format($poFull['total_amount'],2); ?></td>
                          </tr>
                        </tfoot>
                      </table>
                      <?php if ($poFull['notes']): ?>
                      <p class="po-items-notes"><?php echo htmlspecialchars($poFull['notes']); ?></p>
                      <?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

          <?php if (!empty($otherOrders)): ?>
          <details class="po-history-details">
            <summary>
              View Order History
              (<?php
                $parts=[];
                if(count($confirmedOrders)) $parts[]=count($confirmedOrders).' confirmed';
                if(count($cancelledOrders)) $parts[]=count($cancelledOrders).' cancelled';
                echo implode(', ',$parts);
              ?>)
            </summary>
            <div class="table-container" style="margin-top:12px;">
              <table class="po-orders-table">
                <thead><tr><th>PO #</th><th>Supplier</th><th>Items</th><th>Total</th><th>Created By</th><th>Date</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                  <?php foreach ($otherOrders as $po): ?>
                  <tr>
                    <td class="mono">#<?php echo $po['id']; ?></td>
                    <td><?php echo htmlspecialchars($po['supplier_firstname'].' '.$po['supplier_lastname']); ?></td>
                    <td><?php echo $po['item_count']; ?></td>
                    <td>₱<?php echo number_format($po['total_amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($po['admin_firstname'].' '.$po['admin_lastname']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($po['created_at'])); ?></td>
                    <td><span class="po-status-badge status-<?php echo $po['status']; ?>"><?php echo ucfirst($po['status']); ?></span></td>
                    <td><button class="btn-view" onclick="togglePoDetails(<?php echo $po['id']; ?>)">Details</button></td>
                  </tr>
                  <tr id="po-details-<?php echo $po['id']; ?>" class="po-items-row" style="display:none;">
                    <td colspan="8">
                      <?php $poFull = getPurchaseOrderWithItems($connect2db, $po['id']); ?>
                      <?php if ($poFull && !empty($poFull['items'])): ?>
                      <div class="po-items-inner">
                        <table class="po-items-table">
                          <thead><tr><th>SKU</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
                          <tbody>
                            <?php foreach ($poFull['items'] as $item): ?>
                            <tr>
                              <td class="mono"><?php echo htmlspecialchars($item['product_sku']); ?></td>
                              <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                              <td><?php echo number_format($item['quantity']); ?></td>
                              <td>₱<?php echo number_format($item['unit_price'],2); ?></td>
                              <td>₱<?php echo number_format($item['total_price'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                          </tbody>
                          <tfoot>
                            <tr class="po-items-total-row">
                              <td colspan="4" style="text-align:right;font-weight:700;">Order Total:</td>
                              <td style="font-weight:700;">₱<?php echo number_format($poFull['total_amount'],2); ?></td>
                            </tr>
                          </tfoot>
                        </table>
                        <?php if ($poFull['notes']): ?>
                        <p class="po-items-notes"><?php echo htmlspecialchars($poFull['notes']); ?></p>
                        <?php endif; ?>
                      </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </details>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── PRODUCTS ── -->
      <div id="products" class="tab-content">
        <div class="add-product-section">
          <h3>Add New Product</h3>
          <form method="POST" action="" class="product-form">
            <div class="form-row">
              <input type="text" name="sku"  placeholder="SKU"          required>
              <input type="text" name="name" placeholder="Product Name" required>
            </div>
            <div class="form-row">
              <select name="category_id" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <input type="number" name="quantity"     placeholder="Quantity"     required>
              <input type="number" name="min_quantity" placeholder="Min Quantity" required>
              <input type="number" name="unit_price"   placeholder="Unit Price"   step="0.01" required>
            </div>
            <div class="form-row">
              <input type="text" name="supplier" placeholder="Supplier">
              <input type="text" name="location" placeholder="Location">
            </div>
            <textarea name="description" placeholder="Description"></textarea>
            <button type="submit" name="add_product" class="btn-primary">Add Product</button>
          </form>
        </div>

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
                <tr><th>SKU</th><th>Name</th><th>Category</th><th>Quantity</th><th>Unit Price</th><th>Location</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($products as $product): ?>
                <tr class="<?php echo $product['quantity']<=$product['min_quantity']?'low-stock':''; ?>">
                  <td class="mono"><?php echo htmlspecialchars($product['sku']); ?></td>
                  <td><?php echo htmlspecialchars($product['name']); ?></td>
                  <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                  <td><?php echo $product['quantity']; ?></td>
                  <td>₱<?php echo number_format($product['unit_price'],2); ?></td>
                  <td><?php echo htmlspecialchars($product['location']); ?></td>
                  <td>
                    <button class="btn-edit"   onclick="toggleProductEditForm(<?php echo $product['id']; ?>)">Edit</button>
                    <button class="btn-adjust" onclick="toggleAdjustForm(<?php echo $product['id']; ?>)">Adjust</button>
                    <form method="POST" action="" style="display:inline;">
                      <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                      <button type="submit" name="delete_product" class="btn-delete"
                              onclick="return confirm('Are you sure?')">Delete</button>
                    </form>
                  </td>
                </tr>
                <tr id="product-edit-<?php echo $product['id']; ?>" class="edit-form" style="display:none;">
                  <td colspan="7">
                    <form method="POST" action="">
                      <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                      <div class="form-row">
                        <input type="text"   name="sku"          value="<?php echo htmlspecialchars($product['sku']); ?>"          required>
                        <input type="text"   name="name"         value="<?php echo htmlspecialchars($product['name']); ?>"         required>
                        <select name="category_id" required>
                          <?php foreach ($categories as $cat): ?>
                          <option value="<?php echo $cat['id']; ?>"
                            <?php echo $cat['id']==$product['category_id']?'selected':''; ?>>
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
                        <button type="button" class="btn-cancel" onclick="toggleProductEditForm(<?php echo $product['id']; ?>)">Cancel</button>
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
      </div>

      <!-- ── SALES ── -->
      <div id="sales" class="tab-content">
        <div class="sales-table-section">
          <h3>Recent Sales</h3>
          <div class="table-container">
            <table class="sales-table">
              <thead>
                <tr><th>ID</th><th>Cashier</th><th>Amount</th><th>Payment</th><th>Customer</th><th>Date</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentSales as $sale): ?>
                <tr>
                  <td class="mono">#<?php echo $sale['id']; ?></td>
                  <td><?php echo htmlspecialchars($sale['firstname'].' '.$sale['lastname']); ?></td>
                  <td>₱<?php echo number_format($sale['total_amount'],2); ?></td>
                  <td><?php echo ucfirst($sale['payment_method']); ?></td>
                  <td><?php echo htmlspecialchars($sale['customer_name']?:'N/A'); ?></td>
                  <td><?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></td>
                  <td>
                    <button class="btn-view" onclick="viewSaleDetails(<?php echo $sale['id']; ?>)">View</button>
                    <form method="POST" action="" style="display:inline;">
                      <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                      <button type="submit" name="refund_sale" class="btn-refund"
                              onclick="return confirm('Refund this sale? This will restock all items.')">Refund</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── REPORTS ── -->
      <div id="reports" class="tab-content">
        <div class="reports-grid">
          <div class="report-section">
            <h3>Sales Report</h3>
            <div class="table-container">
              <table class="report-table">
                <thead><tr><th>Date</th><th>Sales Count</th><th>Revenue</th><th>Avg Sale</th></tr></thead>
                <tbody>
                  <?php foreach (array_slice($salesReport,0,10) as $r): ?>
                  <tr>
                    <td><?php echo date('M j, Y', strtotime($r['date'])); ?></td>
                    <td><?php echo $r['sales_count']; ?></td>
                    <td>₱<?php echo number_format($r['revenue'],2); ?></td>
                    <td>₱<?php echo number_format($r['avg_amount'],2); ?></td>
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
                <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th><th>Transactions</th></tr></thead>
                <tbody>
                  <?php foreach ($topProducts as $p): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo $p['total_sold']; ?></td>
                    <td>₱<?php echo number_format($p['total_revenue'],2); ?></td>
                    <td><?php echo $p['sales_count']; ?></td>
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
                <thead><tr><th>Staff Member</th><th>Role</th><th>Sales</th><th>Revenue</th><th>Avg Sale</th></tr></thead>
                <tbody>
                  <?php foreach ($staffPerformance as $s): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($s['firstname'].' '.$s['lastname']); ?></td>
                    <td><?php echo ucfirst($s['role']); ?></td>
                    <td><?php echo $s['sales_count']; ?></td>
                    <td>₱<?php echo number_format($s['total_revenue'],2); ?></td>
                    <td>₱<?php echo number_format($s['avg_sale_amount'],2); ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
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
              <input type="date" name="start_date">
              <input type="date" name="end_date">
            </div>
            <button type="submit" name="export_data" class="btn-primary">Export Data</button>
          </form>
        </div>
      </div>

      <!-- ── SETTINGS ── -->
      <div id="settings" class="tab-content">
        <div class="settings-section">
          <h3>General Settings</h3>
          <form method="POST" action="" class="settings-form">
            <div class="form-row">
              <label>Company Name:</label>
              <input type="text" name="company_name" value="<?php echo htmlspecialchars($systemSettings['company_name']); ?>">
            </div>
            <div class="form-row">
              <label>Company Email:</label>
              <input type="email" name="company_email" value="<?php echo htmlspecialchars($systemSettings['company_email']); ?>">
            </div>
            <div class="form-row">
              <label>Currency:</label>
              <select name="currency">
                <option value="PHP" <?php echo $systemSettings['currency']==='PHP'?'selected':''; ?>>PHP (Philippine Peso)</option>
                <option value="USD" <?php echo $systemSettings['currency']==='USD'?'selected':''; ?>>USD (US Dollar)</option>
                <option value="EUR" <?php echo $systemSettings['currency']==='EUR'?'selected':''; ?>>EUR (Euro)</option>
              </select>
            </div>
            <div class="form-row">
              <label>Tax Rate (%):</label>
              <input type="number" name="tax_rate"
                     value="<?php echo $systemSettings['tax_rate']*100; ?>" step="0.1">
              <small>Current: <?php echo $systemSettings['tax_rate']*100; ?>% (PH VAT)</small>
            </div>
            <div class="form-row">
              <label>Low Stock Threshold:</label>
              <input type="number" name="low_stock_threshold"
                     value="<?php echo $systemSettings['low_stock_threshold']; ?>">
            </div>
            <button type="submit" name="update_settings" class="btn-primary">Save Settings</button>
          </form>
        </div>
        <div class="maintenance-section">
          <h3>System Maintenance</h3>
          <form method="POST" action="" style="display:inline;">
            <button type="submit" name="backup_database" class="btn-primary">Backup Database</button>
          </form>
        </div>
      </div>

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

<!-- Sale Details Modal -->
<div id="saleDetailsModal" class="modal" style="display:none;">
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
function toggleUserEditForm(id)         { const el=document.getElementById('user-edit-'+id);      el.style.display=el.style.display==='none'?'table-row':'none'; }
function togglePasswordReset(id)        { const el=document.getElementById('password-reset-'+id); el.style.display=el.style.display==='none'?'table-row':'none'; }
function toggleSupplierPasswordReset(id){ const el=document.getElementById('sup-pw-reset-'+id);  el.style.display=el.style.display==='none'?'table-row':'none'; }
function toggleProductEditForm(id)      { const el=document.getElementById('product-edit-'+id);   el.style.display=el.style.display==='none'?'table-row':'none'; }
function toggleAdjustForm(id)           { const el=document.getElementById('adjust-form-'+id);    el.style.display=el.style.display==='none'?'table-row':'none'; }
function viewSaleDetails(saleId) {
  const content = document.getElementById('saleDetailsContent');
  content.innerHTML = '<p style="padding:20px;color:#6b7280;">Loading...</p>';
  document.getElementById('saleDetailsModal').style.display = 'flex';

  fetch('get_sale_details.php?id=' + saleId)
    .then(r => r.json())
    .then(data => {
      if (!data || data.error) { content.innerHTML = '<p style="padding:20px;color:#ef4444;">Could not load sale details.</p>'; return; }
      let rows = (data.items || []).map(i => `
        <tr>
          <td style="font-family:monospace;font-size:12px;">${i.sku}</td>
          <td>${i.name}</td>
          <td style="text-align:center;">${i.quantity}</td>
          <td style="text-align:right;">₱${parseFloat(i.unit_price).toFixed(2)}</td>
          <td style="text-align:right;font-weight:700;">₱${parseFloat(i.total_price).toFixed(2)}</td>
        </tr>`).join('');
      content.innerHTML = `
        <div style="font-size:13.5px;padding:4px 0 12px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #e5e7eb;">
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Sale ID</span><br><strong>#${data.id}</strong></div>
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Date</span><br>${data.created_at}</div>
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Cashier</span><br>${data.cashier}</div>
            <div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Payment</span><br>${data.payment_method}</div>
            ${data.customer_name ? `<div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Customer</span><br>${data.customer_name}</div>` : ''}
            ${data.notes ? `<div><span style="color:#9ca3af;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;">Notes</span><br>${data.notes}</div>` : ''}
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="background:#f8fafc;">
              <th style="padding:8px 10px;text-align:left;font-size:11px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">SKU</th>
              <th style="padding:8px 10px;text-align:left;font-size:11px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Product</th>
              <th style="padding:8px 10px;text-align:center;font-size:11px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Qty</th>
              <th style="padding:8px 10px;text-align:right;font-size:11px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Unit</th>
              <th style="padding:8px 10px;text-align:right;font-size:11px;color:#6b7280;text-transform:uppercase;border-bottom:1px solid #e5e7eb;">Total</th>
            </tr></thead>
            <tbody>${rows}</tbody>
            <tfoot><tr style="border-top:2px solid #e5e7eb;">
              <td colspan="4" style="padding:10px 10px 4px;text-align:right;font-weight:700;">Grand Total:</td>
              <td style="padding:10px 10px 4px;text-align:right;font-weight:700;color:#FFDE42;">₱${parseFloat(data.total_amount).toFixed(2)}</td>
            </tr></tfoot>
          </table>
        </div>`;
    })
    .catch(() => { content.innerHTML = '<p style="padding:20px;color:#ef4444;">Failed to load.</p>'; });
}
function closeModal() {
  document.getElementById('saleDetailsModal').style.display = 'none';
}
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('saleDetailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
  });
});

function togglePoRow(checkbox, productId) {
  const qtyInput = document.getElementById('qty-'+productId);
  const row      = document.getElementById('po-row-'+productId);
  if (checkbox.checked) {
    qtyInput.disabled = false;
    if (parseInt(qtyInput.value)<=0) qtyInput.value = 1;
    row.classList.add('po-row-selected');
    onQtyChange(qtyInput, productId);
  } else {
    qtyInput.disabled = true;
    qtyInput.value    = 0;
    row.classList.remove('po-row-selected');
    document.getElementById('sub-'+productId).textContent = '₱0.00';
    updatePoTotal();
  }
}
function onQtyChange(input, productId) {
  const qty      = Math.max(0, parseInt(input.value)||0);
  const price    = parseFloat(input.dataset.price)||0;
  document.getElementById('sub-'+productId).textContent = formatPeso(qty*price);
  updatePoTotal();
}
function updatePoTotal() {
  let total=0, count=0;
  document.querySelectorAll('.po-check:checked').forEach(cb => {
    const rowId=cb.dataset.row, qi=document.getElementById('qty-'+rowId);
    const qty=parseInt(qi?.value)||0, price=parseFloat(qi?.dataset.price)||0;
    if (qty>0) { total+=qty*price; count++; }
  });
  const totalEl=document.getElementById('poGrandTotal');
  const countEl=document.getElementById('poItemCount');
  const btnEl  =document.getElementById('poSubmitBtn');
  if (totalEl) totalEl.textContent = formatPeso(total);
  if (countEl) countEl.textContent = count + ' line item' + (count!==1?'s':'');
  if (btnEl)   btnEl.disabled      = count===0;
}
function formatPeso(n) { return '₱'+n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
function togglePoDetails(orderId) {
  const row=document.getElementById('po-details-'+orderId);
  if (!row) return;
  row.style.display=row.style.display==='none'?'table-row':'none';
}

document.addEventListener('DOMContentLoaded', function() {
  const params = new URLSearchParams(window.location.search);
  if (params.has('catalog_supplier')) {
    const btn=document.querySelector('[onclick*="purchase-orders"]');
    showTab('purchase-orders', btn);
    return;
  }
  const hash=window.location.hash.replace('#','');
  if (hash && document.getElementById(hash)) {
    const btn=document.querySelector(`[onclick*="${hash}"]`);
    showTab(hash, btn);
  }
});
</script>

</body>
</html>
