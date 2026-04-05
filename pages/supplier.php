<?php
session_start();
include '../db/connect.php';
include '../functions/supplier_guard.php';
include '../functions/supplier_functions.php';
include '../functions/purchase_order_functions.php';

$user = $_SESSION['user'];

// ── Handle form submissions ──────────────────────────────────────────────────
if (isset($_POST['add_product'])) {
    addSupplierProduct(
        $connect2db,
        $user['id'],
        $_POST,
        $_FILES['product_image'] ?? null,
        $resultClass,
        $result
    );
}

if (isset($_POST['update_product'])) {
    updateSupplierProduct(
        $connect2db,
        (int)$_POST['product_id'],
        $user['id'],
        $_POST,
        $_FILES['product_image'] ?? null,
        $resultClass,
        $result
    );
}

if (isset($_POST['delete_product'])) {
    deleteSupplierProduct(
        $connect2db,
        (int)$_POST['product_id'],
        $user['id'],
        $resultClass,
        $result
    );
}

// ── Fetch data ───────────────────────────────────────────────────────────────
$products       = getSupplierProducts($connect2db, $user['id']);
$stats          = getSupplierStats($connect2db, $user['id']);
$purchaseOrders = getSupplierPurchaseOrders($connect2db, $user['id']);

// Summary counts for orders
$pendingCount   = count(array_filter($purchaseOrders, fn($o) => $o['status'] === 'pending'));
$confirmedCount = count(array_filter($purchaseOrders, fn($o) => $o['status'] === 'confirmed'));

// Re-open modal on error
$reopenAdd  = isset($_POST['add_product'])    && isset($resultClass) && $resultClass === 'error';
$reopenEdit = isset($_POST['update_product']) && isset($resultClass) && $resultClass === 'error';
$editId     = $reopenEdit ? (int)$_POST['product_id'] : null;

// Active tab from GET param (products | orders)
$activeTab = in_array($_GET['tab'] ?? '', ['products', 'orders']) ? $_GET['tab'] : 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Portal</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Supplier tab nav ── */
        .sp-tab-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--color-border);
        }
        .sp-tab-btn {
            padding: 9px 20px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: color 0.15s, border-color 0.15s;
        }
        .sp-tab-btn svg { transition: color 0.15s; }
        .sp-tab-btn:hover { color: var(--color-primary); }
        .sp-tab-btn.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
        }
        .sp-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            background: #fef3c7;
            color: #d97706;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        .sp-tab-content { display: none; }
        .sp-tab-content.active { display: block; }

        /* ── Orders section ── */
        .orders-summary-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }
        .order-stat-card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .order-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .order-stat-icon.pending   { background: #fef3c7; color: #d97706; }
        .order-stat-icon.confirmed { background: #d1fae5; color: #059669; }
        .order-stat-icon.total     { background: #e0e7ff; color: #4f46e5; }
        .order-stat-val {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-text);
        }
        .order-stat-lbl {
            font-size: 12px;
            color: var(--color-text-muted);
        }

        .orders-table { width: 100%; border-collapse: collapse; font-size: 14px; background: white; }
        .orders-table th, .orders-table td {
            padding: 11px 14px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .orders-table th { background: #f8fafc; font-weight: 600; font-size: 13px; color: #374151; }
        .orders-table tbody tr:hover { background: #f9fafb; }

        .orders-items-row { display: none; }
        .orders-items-row td {
            background: #f8fafc;
            padding: 0 !important;
        }
        .orders-items-inner {
            padding: 14px 20px;
        }
        .orders-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: white;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid var(--color-border);
        }
        .orders-items-table th, .orders-items-table td {
            padding: 9px 12px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .orders-items-table th { background: #f1f5f9; font-weight: 600; }
        .orders-items-table tr:last-child td { border-bottom: none; }

        .receipt-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.15s;
        }
        .receipt-link:hover { background: #10b981; color: white; border-color: #10b981; text-decoration: none; }

        .empty-orders {
            text-align: center;
            padding: 52px 20px;
            background: #f8fafc;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-sm);
            color: var(--color-text-muted);
        }
        .empty-orders svg { margin-bottom: 12px; opacity: 0.4; }
        .empty-orders p { font-size: 14px; }

        /* history accordion */
        .orders-history-details summary {
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text-muted);
            padding: 10px 0;
            user-select: none;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .orders-history-details summary:hover { color: var(--color-primary); }
        .orders-history-details[open] summary { color: var(--color-primary); }
    </style>
</head>
<body>

<div class="supplier-container">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="supplier-header">
        <div class="header-left">
            <h1>Supplier Portal</h1>
            <span class="header-subtitle">
                <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                &nbsp;&middot;&nbsp; Supplier Account
            </span>
        </div>
        <div class="nav-links">
            <a href="profile.php" class="profile-link">Profile</a>
            <a href="logout.php"  class="logout-link">Logout</a>
        </div>
    </div>

    <!-- ── Flash message ──────────────────────────────────────────────────── -->
    <?php if (isset($result)): ?>
    <div class="message <?php echo htmlspecialchars($resultClass); ?>">
        <?php echo htmlspecialchars($result); ?>
    </div>
    <?php endif; ?>

    <!-- ── Stats cards ────────────────────────────────────────────────────── -->
    <div class="stats-container">
        <div class="stat-card">
            <h3>Total Products</h3>
            <p><?php echo (int)$stats['total_products']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Total Qty Available</h3>
            <p><?php echo number_format((int)$stats['total_quantity']); ?></p>
        </div>
        <div class="stat-card">
            <h3>Categories</h3>
            <p><?php echo (int)$stats['total_categories']; ?></p>
        </div>
        <div class="stat-card">
            <h3>Avg. Unit Price</h3>
            <p>&#8369;<?php echo number_format((float)$stats['avg_price'], 2); ?></p>
        </div>
    </div>

    <!-- ── Tab navigation ─────────────────────────────────────────────────── -->
    <div class="sp-tab-nav">
        <button class="sp-tab-btn <?php echo $activeTab === 'products' ? 'active' : ''; ?>"
                onclick="switchTab('products', this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 2 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 22 16z"/>
                <polyline points="3.29 7 12 12 20.71 7"/>
                <line x1="12" y1="22" x2="12" y2="12"/>
            </svg>
            My Products
        </button>
        <button class="sp-tab-btn <?php echo $activeTab === 'orders' ? 'active' : ''; ?>"
                onclick="switchTab('orders', this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Purchase Orders
            <?php if ($pendingCount > 0): ?>
                <span class="sp-tab-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         TAB: MY PRODUCTS
    ══════════════════════════════════════════════════════════════════════ -->
    <div id="tab-products" class="sp-tab-content <?php echo $activeTab === 'products' ? 'active' : ''; ?>">

        <div class="sp-section-header">
            <h2>My Products</h2>
            <button class="btn-primary" onclick="openAddModal()">
                + Add Product
            </button>
        </div>

        <!-- Controls: search + sort -->
        <div class="sp-table-controls">
            <input
                type="text"
                id="spSearch"
                placeholder="Search by name, SKU or category&hellip;"
                oninput="applyTableFilters()"
                class="sp-search-input"
            >
            <div class="sp-sort-controls">
                <span class="sort-label">Sort:</span>
                <button class="sort-col-btn active" data-col="name"     onclick="setSort('name')">Name</button>
                <button class="sort-col-btn"         data-col="sku"      onclick="setSort('sku')">SKU</button>
                <button class="sort-col-btn"         data-col="category" onclick="setSort('category')">Category</button>
                <button class="sort-col-btn"         data-col="qty"      onclick="setSort('qty')">Qty</button>
                <button class="sort-col-btn"         data-col="price"    onclick="setSort('price')">Price</button>
                <button class="sort-dir-btn" id="sortDirBtn" onclick="toggleSortDir()" title="Toggle order">&#8593; ASC</button>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div class="sp-empty-state">
                <p>No products yet.</p>
                <p>Click <strong>+ Add Product</strong> to list your first item.</p>
            </div>
        <?php else: ?>

        <div class="table-container">
            <table class="sp-table" id="spTable">
                <thead>
                    <tr>
                        <th style="width:64px;">Image</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Qty Available</th>
                        <th>Unit Price</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr
                        data-sku="<?php      echo strtolower(htmlspecialchars($p['sku']));      ?>"
                        data-name="<?php     echo strtolower(htmlspecialchars($p['name']));     ?>"
                        data-category="<?php echo strtolower(htmlspecialchars($p['category'] ?? '')); ?>"
                        data-qty="<?php      echo (int)$p['quantity_available'];               ?>"
                        data-price="<?php    echo (float)$p['unit_price'];                     ?>"
                    >
                        <td>
                            <?php if ($p['image_path']): ?>
                                <img
                                    src="../<?php echo htmlspecialchars($p['image_path']); ?>"
                                    alt="<?php echo htmlspecialchars($p['name']); ?>"
                                    class="sp-thumb"
                                >
                            <?php else: ?>
                                <div class="sp-no-img">&mdash;</div>
                            <?php endif; ?>
                        </td>
                        <td class="mono"><?php echo htmlspecialchars($p['sku']); ?></td>
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['category'] ?: '&mdash;'); ?></td>
                        <td><?php echo number_format((int)$p['quantity_available']); ?></td>
                        <td>&#8369;<?php echo number_format((float)$p['unit_price'], 2); ?></td>
                        <td>
                            <button
                                class="btn-edit"
                                onclick='openEditModal(<?php echo json_encode([
                                    "id"                 => (int)$p['id'],
                                    "sku"                => $p['sku'],
                                    "name"               => $p['name'],
                                    "description"        => $p['description'] ?? '',
                                    "category"           => $p['category'] ?? '',
                                    "quantity_available" => (int)$p['quantity_available'],
                                    "unit_price"         => (float)$p['unit_price'],
                                    "image_path"         => $p['image_path'] ?? '',
                                ]); ?>)'
                            >Edit</button>
                            <form method="POST" action="" style="display:inline;"
                                  onsubmit="return confirm('Delete this product?')">
                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                <button type="submit" name="delete_product" class="btn-delete">Del</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="sp-row-count" id="spRowCount"></p>

        <?php endif; ?>
    </div><!-- /tab-products -->


    <!-- ══════════════════════════════════════════════════════════════════════
         TAB: PURCHASE ORDERS
    ══════════════════════════════════════════════════════════════════════ -->
    <div id="tab-orders" class="sp-tab-content <?php echo $activeTab === 'orders' ? 'active' : ''; ?>">

        <!-- Summary cards -->
        <div class="orders-summary-row">
            <div class="order-stat-card">
                <div class="order-stat-icon total">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                </div>
                <div>
                    <div class="order-stat-val"><?php echo count($purchaseOrders); ?></div>
                    <div class="order-stat-lbl">Total Orders</div>
                </div>
            </div>
            <div class="order-stat-card">
                <div class="order-stat-icon pending">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="order-stat-val"><?php echo $pendingCount; ?></div>
                    <div class="order-stat-lbl">Pending</div>
                </div>
            </div>
            <div class="order-stat-card">
                <div class="order-stat-icon confirmed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div>
                    <div class="order-stat-val"><?php echo $confirmedCount; ?></div>
                    <div class="order-stat-lbl">Confirmed</div>
                </div>
            </div>
        </div>

        <?php
        $pendingOrders   = array_values(array_filter($purchaseOrders, fn($o) => $o['status'] === 'pending'));
        $confirmedOrders = array_values(array_filter($purchaseOrders, fn($o) => $o['status'] === 'confirmed'));
        $cancelledOrders = array_values(array_filter($purchaseOrders, fn($o) => $o['status'] === 'cancelled'));
        ?>

        <!-- Pending orders -->
        <?php if (!empty($pendingOrders)): ?>
        <h3 style="font-size:15px; font-weight:600; margin-bottom:12px; color:#d97706; display:flex; align-items:center; gap:7px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Pending Orders
        </h3>
        <div class="table-container" style="margin-bottom:28px;">
            <table class="orders-table" id="pendingTable">
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Ordered By</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $po): ?>
                    <tr>
                        <td><strong>#<?php echo $po['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($po['admin_firstname'] . ' ' . $po['admin_lastname']); ?></td>
                        <td><?php echo (int)$po['item_count']; ?> item<?php echo $po['item_count'] != 1 ? 's' : ''; ?></td>
                        <td style="font-family: monospace; font-weight:600;">&#8369;<?php echo number_format($po['total_amount'], 2); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($po['created_at'])); ?></td>
                        <td><span class="po-status-badge status-<?php echo $po['status']; ?>"><?php echo ucfirst($po['status']); ?></span></td>
                        <td style="white-space:nowrap; display:flex; gap:6px; padding-top:10px; padding-bottom:10px;">
                            <button class="btn-view" onclick="toggleOrderItems('p<?php echo $po['id']; ?>')">Details</button>
                            <a class="receipt-link" href="receipt.php?id=<?php echo $po['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 6 2 18 2 18 9"/>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                    <rect x="6" y="14" width="12" height="8"/>
                                </svg>
                                Receipt
                            </a>
                        </td>
                    </tr>
                    <tr id="order-items-p<?php echo $po['id']; ?>" class="orders-items-row">
                        <td colspan="7">
                            <?php $poFull = getPurchaseOrderWithItems($connect2db, $po['id']); ?>
                            <?php if ($poFull && !empty($poFull['items'])): ?>
                            <div class="orders-items-inner">
                                <table class="orders-items-table">
                                    <thead>
                                        <tr><th>SKU</th><th>Product</th><th>Qty Ordered</th><th>Unit Price</th><th>Line Total</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($poFull['items'] as $item): ?>
                                        <tr>
                                            <td class="mono"><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo number_format($item['quantity']); ?></td>
                                            <td>&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td>&#8369;<?php echo number_format($item['total_price'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Confirmed + cancelled (collapsible) -->
        <?php $historyOrders = array_merge($confirmedOrders, $cancelledOrders);
              usort($historyOrders, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        ?>

        <?php if (!empty($historyOrders)): ?>
        <details class="orders-history-details" <?php echo empty($pendingOrders) ? 'open' : ''; ?>>
            <summary>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                Order History
                (<?php
                    $parts = [];
                    if ($confirmedCount) $parts[] = "$confirmedCount confirmed";
                    if (count($cancelledOrders)) $parts[] = count($cancelledOrders) . ' cancelled';
                    echo implode(', ', $parts);
                ?>)
            </summary>
            <div class="table-container" style="margin-top:12px;">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>PO #</th>
                            <th>Ordered By</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyOrders as $po): ?>
                        <tr>
                            <td><strong>#<?php echo $po['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($po['admin_firstname'] . ' ' . $po['admin_lastname']); ?></td>
                            <td><?php echo (int)$po['item_count']; ?></td>
                            <td style="font-family: monospace; font-weight:600;">&#8369;<?php echo number_format($po['total_amount'], 2); ?></td>
                            <td><?php echo date('M j, Y', strtotime($po['created_at'])); ?></td>
                            <td><span class="po-status-badge status-<?php echo $po['status']; ?>"><?php echo ucfirst($po['status']); ?></span></td>
                            <td style="white-space:nowrap; display:flex; gap:6px; padding-top:10px; padding-bottom:10px;">
                                <button class="btn-view" onclick="toggleOrderItems('h<?php echo $po['id']; ?>')">Details</button>
                                <a class="receipt-link" href="receipt.php?id=<?php echo $po['id']; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 6 2 18 2 18 9"/>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                        <rect x="6" y="14" width="12" height="8"/>
                                    </svg>
                                    Receipt
                                </a>
                            </td>
                        </tr>
                        <tr id="order-items-h<?php echo $po['id']; ?>" class="orders-items-row">
                            <td colspan="7">
                                <?php $poFull = getPurchaseOrderWithItems($connect2db, $po['id']); ?>
                                <?php if ($poFull && !empty($poFull['items'])): ?>
                                <div class="orders-items-inner">
                                    <table class="orders-items-table">
                                        <thead>
                                            <tr><th>SKU</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($poFull['items'] as $item): ?>
                                            <tr>
                                                <td class="mono"><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                <td><?php echo number_format($item['quantity']); ?></td>
                                                <td>&#8369;<?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td>&#8369;<?php echo number_format($item['total_price'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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

        <?php if (empty($purchaseOrders)): ?>
        <div class="empty-orders">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            <p>No purchase orders yet. Admins will appear here once they order from your catalog.</p>
        </div>
        <?php endif; ?>

    </div><!-- /tab-orders -->

</div><!-- /supplier-container -->


<!-- ══════════════════════════════════════════════════════════════════════════
     ADD / EDIT PRODUCT MODAL
══════════════════════════════════════════════════════════════════════════ -->
<div id="productModal" class="sp-modal-overlay" style="display:none;" onclick="overlayClick(event)">
    <div class="sp-modal-card">

        <div class="sp-modal-header">
            <h2 id="spModalTitle">Add New Product</h2>
            <button class="sp-modal-close" onclick="closeModal()" title="Close">&times;</button>
        </div>

        <form method="POST" action="" enctype="multipart/form-data" id="spModalForm">
            <input type="hidden" name="product_id" id="spProductId">

            <div class="sp-modal-body">

                <!-- LEFT: Image upload -->
                <div class="sp-modal-image-col">
                    <div class="sp-upload-area" id="spUploadArea"
                         onclick="document.getElementById('spImageInput').click()"
                         title="Click to upload image">
                        <img id="spImgPreview" src="" alt="" style="display:none;">
                        <div id="spImgPlaceholder">
                            <span class="sp-upload-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none"
                                     stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                            </span>
                            <p>Click to upload</p>
                            <small>JPEG &middot; PNG &middot; GIF &middot; WEBP<br>Max 5 MB</small>
                        </div>
                    </div>
                    <input type="file" id="spImageInput" name="product_image"
                           accept="image/*" style="display:none;" onchange="previewImage(this)">
                    <p class="sp-img-note" id="spImgNote"></p>
                </div>

                <!-- RIGHT: Fields -->
                <div class="sp-modal-fields">

                    <div class="sp-field-row-two">
                        <div class="sp-field">
                            <label>SKU <span class="req">*</span></label>
                            <input type="text" name="sku" id="spSku" placeholder="e.g. SCREW001" required>
                        </div>
                        <div class="sp-field">
                            <label>Category</label>
                            <input type="text" name="category" id="spCategory" placeholder="e.g. Fasteners">
                        </div>
                    </div>

                    <div class="sp-field">
                        <label>Product Name <span class="req">*</span></label>
                        <input type="text" name="name" id="spName" placeholder="e.g. Wood Screws #8" required>
                    </div>

                    <div class="sp-field-row-two">
                        <div class="sp-field">
                            <label>Qty Available <span class="req">*</span></label>
                            <input type="number" name="quantity_available" id="spQty"
                                   placeholder="0" min="0" required>
                        </div>
                        <div class="sp-field">
                            <label>Unit Price (&#8369;) <span class="req">*</span></label>
                            <input type="number" name="unit_price" id="spPrice"
                                   placeholder="0.00" min="0" step="0.01" required>
                        </div>
                    </div>

                    <div class="sp-field">
                        <label>Description</label>
                        <textarea name="description" id="spDescription"
                                  placeholder="Optional product details&hellip;" rows="4"></textarea>
                    </div>

                </div>
            </div>

            <div class="sp-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" id="spSubmitBtn" name="add_product" class="btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>


<script>
// ── Tab switching ─────────────────────────────────────────────────────────────
function switchTab(name, btn) {
    document.querySelectorAll('.sp-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.sp-tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ── Order item row toggle ─────────────────────────────────────────────────────
function toggleOrderItems(key) {
    const row = document.getElementById('order-items-' + key);
    if (!row) return;
    row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('spModalTitle').textContent         = 'Add New Product';
    document.getElementById('spProductId').value                = '';
    document.getElementById('spSku').value                      = '';
    document.getElementById('spName').value                     = '';
    document.getElementById('spCategory').value                 = '';
    document.getElementById('spQty').value                      = '';
    document.getElementById('spPrice').value                    = '';
    document.getElementById('spDescription').value              = '';
    document.getElementById('spImgPreview').style.display       = 'none';
    document.getElementById('spImgPlaceholder').style.display   = 'flex';
    document.getElementById('spImgNote').textContent            = '';
    document.getElementById('spImageInput').value               = '';
    document.getElementById('spSubmitBtn').name                 = 'add_product';
    showModal();
}

function openEditModal(p) {
    document.getElementById('spModalTitle').textContent         = 'Edit Product';
    document.getElementById('spProductId').value                = p.id;
    document.getElementById('spSku').value                      = p.sku;
    document.getElementById('spName').value                     = p.name;
    document.getElementById('spCategory').value                 = p.category || '';
    document.getElementById('spQty').value                      = p.quantity_available;
    document.getElementById('spPrice').value                    = p.unit_price;
    document.getElementById('spDescription').value              = p.description || '';
    document.getElementById('spImageInput').value               = '';

    const preview     = document.getElementById('spImgPreview');
    const placeholder = document.getElementById('spImgPlaceholder');

    if (p.image_path) {
        preview.src                   = '../' + p.image_path;
        preview.style.display         = 'block';
        placeholder.style.display     = 'none';
        document.getElementById('spImgNote').textContent = 'Upload a new file to replace the current image.';
    } else {
        preview.style.display         = 'none';
        placeholder.style.display     = 'flex';
        document.getElementById('spImgNote').textContent = '';
    }

    document.getElementById('spSubmitBtn').name = 'update_product';
    showModal();
}

function showModal() {
    document.getElementById('productModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
    document.body.style.overflow = '';
}

function overlayClick(e) {
    if (e.target.id === 'productModal') closeModal();
}

function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('spImgPreview');
        img.src                                             = e.target.result;
        img.style.display                                   = 'block';
        document.getElementById('spImgPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

// ── Table sort + filter ───────────────────────────────────────────────────────
let sortCol = 'name', sortDir = 'asc';

function setSort(col) {
    if (sortCol === col) { toggleSortDir(); return; }
    sortCol = col;
    sortDir = 'asc';
    document.querySelectorAll('.sort-col-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.sort-col-btn[data-col="${col}"]`).classList.add('active');
    document.getElementById('sortDirBtn').textContent = '\u2191 ASC';
    applyTableFilters();
}

function toggleSortDir() {
    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    document.getElementById('sortDirBtn').textContent = sortDir === 'asc' ? '\u2191 ASC' : '\u2193 DESC';
    applyTableFilters();
}

function applyTableFilters() {
    const tbody = document.querySelector('#spTable tbody');
    if (!tbody) return;
    const rows  = Array.from(tbody.querySelectorAll('tr'));
    const term  = document.getElementById('spSearch').value.toLowerCase();

    rows.forEach(r => {
        const match =
            (r.dataset.name     || '').includes(term) ||
            (r.dataset.sku      || '').includes(term) ||
            (r.dataset.category || '').includes(term);
        r.style.display = match ? '' : 'none';
    });

    const visible = rows.filter(r => r.style.display !== 'none');
    visible.sort((a, b) => {
        let av = a.dataset[sortCol] || '';
        let bv = b.dataset[sortCol] || '';
        if (sortCol === 'qty' || sortCol === 'price') {
            av = parseFloat(av) || 0;
            bv = parseFloat(bv) || 0;
            return sortDir === 'asc' ? av - bv : bv - av;
        }
        return sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
    });
    visible.forEach(r => tbody.appendChild(r));

    const countEl = document.getElementById('spRowCount');
    if (countEl) {
        countEl.textContent = term
            ? `Showing ${visible.length} of ${rows.length} products`
            : `${rows.length} product${rows.length !== 1 ? 's' : ''}`;
    }
}

// ── Auto-open modal on error ──────────────────────────────────────────────────
<?php if ($reopenAdd): ?>
openAddModal();
document.getElementById('spSku').value          = <?php echo json_encode($_POST['sku']                ?? ''); ?>;
document.getElementById('spName').value         = <?php echo json_encode($_POST['name']               ?? ''); ?>;
document.getElementById('spCategory').value     = <?php echo json_encode($_POST['category']           ?? ''); ?>;
document.getElementById('spQty').value          = <?php echo json_encode($_POST['quantity_available'] ?? ''); ?>;
document.getElementById('spPrice').value        = <?php echo json_encode($_POST['unit_price']         ?? ''); ?>;
document.getElementById('spDescription').value  = <?php echo json_encode($_POST['description']        ?? ''); ?>;
<?php endif; ?>

<?php if ($reopenEdit): ?>
(function() {
    const p = {
        id:                 <?php echo $editId ?? 0; ?>,
        sku:                <?php echo json_encode($_POST['sku']                ?? ''); ?>,
        name:               <?php echo json_encode($_POST['name']               ?? ''); ?>,
        category:           <?php echo json_encode($_POST['category']           ?? ''); ?>,
        quantity_available: <?php echo json_encode($_POST['quantity_available'] ?? ''); ?>,
        unit_price:         <?php echo json_encode($_POST['unit_price']         ?? ''); ?>,
        description:        <?php echo json_encode($_POST['description']        ?? ''); ?>,
        image_path:         ''
    };
    openEditModal(p);
})();
<?php endif; ?>

document.addEventListener('DOMContentLoaded', () => applyTableFilters());
</script>

</body>
</html>
