<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';

// Supplier-specific includes
if ($_SESSION['user']['role'] === 'supplier') {
    include '../functions/supplier_functions.php';
    include '../functions/purchase_order_functions.php';
}

$user    = $_SESSION['user'];
$profile = getProfile($connect2db, $user['id']);

if (isset($_POST['update_profile'])) {
    updateProfile($_POST, $connect2db, $user['id'], $resultClass, $result);
    $profile = getProfile($connect2db, $user['id']);
    $user    = $_SESSION['user']; // refreshed by updateProfile
}

// ── Stats: role-aware ────────────────────────────────────────
$userSalesCount = 0;
$userSalesTotal = 0.0;
$userLogs       = [];

if ($user['role'] !== 'supplier') {
    $allSales = getSales($connect2db, 500);
    $userSales = array_filter($allSales, fn($s) => (int)$s['user_id'] === (int)$user['id']);
    $userSalesCount = count($userSales);
    foreach ($userSales as $s) {
        $userSalesTotal += (float)$s['total_amount'];
    }

    $allLogs  = getInventoryLogs($connect2db, null, 100);
    $userLogs = array_values(array_filter($allLogs, fn($l) => (int)$l['user_id'] === (int)$user['id']));
}

// Supplier-specific stats
$supplierStats  = null;
$supplierOrders = [];
if ($user['role'] === 'supplier') {
    $supplierStats  = getSupplierStats($connect2db, $user['id']);
    $supplierOrders = getSupplierPurchaseOrders($connect2db, $user['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Profile-specific extras ── */
        .profile-page-wrap {
            width: 95%;
            max-width: 960px;
            margin: 0 auto;
        }
        .profile-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--color-border);
        }
        .profile-header-bar h1 {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-header-bar h1 svg {
            color: var(--color-primary);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 720px) {
            .profile-grid { grid-template-columns: 1fr; }
        }

        /* Card wrapper */
        .profile-card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        .profile-card h2 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--color-text);
        }
        .profile-card h2 svg {
            color: var(--color-primary);
        }

        /* Info display */
        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .info-row label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-text-muted);
            margin-bottom: 3px;
        }
        .info-row p {
            font-size: 15px;
            font-weight: 500;
            color: var(--color-text);
        }

        /* Edit form */
        .edit-toggle-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 16px;
        }
        .edit-toggle-btn:hover { background: var(--color-primary); color: white; }

        .edit-form-wrap {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--color-border);
        }
        .edit-form-wrap .sp-field {
            margin-bottom: 12px;
        }
        .edit-form-wrap input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
        }
        .edit-form-wrap input:focus {
            outline: none;
            border-color: var(--color-primary);
        }

        /* Stats row */
        .profile-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        @media (max-width: 500px) {
            .profile-stats-row { grid-template-columns: 1fr 1fr; }
        }
        .profile-stat {
            background: #f8fafc;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            text-align: center;
        }
        .profile-stat .stat-val {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-primary);
        }
        .profile-stat .stat-lbl {
            font-size: 12px;
            color: var(--color-text-muted);
            margin-top: 2px;
        }

        /* Activity list */
        .activity-entry {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--color-border);
            font-size: 13px;
        }
        .activity-entry:last-child { border-bottom: none; }
        .activity-icon {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-primary);
        }
        .activity-body strong { display: block; font-size: 13px; }
        .activity-body span { color: var(--color-text-muted); font-size: 12px; }

        /* Supplier orders */
        .po-mini-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .po-mini-table th, .po-mini-table td {
            padding: 9px 12px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }
        .po-mini-table th { background: #f8fafc; font-weight: 600; font-size: 12px; }
        .po-mini-table tbody tr:hover { background: #f9fafb; }
        .empty-state {
            text-align: center;
            padding: 32px;
            color: var(--color-text-muted);
            font-size: 14px;
            background: #f8fafc;
            border-radius: var(--radius-sm);
        }
    </style>
</head>
<body>
<div class="profile-page-wrap">

    <!-- Header -->
    <div class="profile-header-bar">
        <h1>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            My Profile
        </h1>
        <div class="nav-links">
            <?php if ($user['role'] === 'supplier'): ?>
                <a href="supplier.php" class="pos-link">My Portal</a>
            <?php else: ?>
                <a href="dashboard.php" class="dashboard-link">Dashboard</a>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="admin.php" class="admin-link">Admin</a>
                <?php endif; ?>
                <a href="pos.php" class="pos-link">POS</a>
            <?php endif; ?>
            <a href="logout.php" class="logout-link">Logout</a>
        </div>
    </div>

    <?php if (isset($result)): ?>
    <div class="message <?php echo htmlspecialchars($resultClass); ?>">
        <?php echo htmlspecialchars($result); ?>
    </div>
    <?php endif; ?>

    <div class="profile-grid">

        <!-- LEFT: Personal info + edit -->
        <div>
            <div class="profile-card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Personal Information
                </h2>

                <div class="info-grid">
                    <div class="info-row">
                        <label>First Name</label>
                        <p><?php echo htmlspecialchars($profile['firstname']); ?></p>
                    </div>
                    <div class="info-row">
                        <label>Last Name</label>
                        <p><?php echo htmlspecialchars($profile['lastname']); ?></p>
                    </div>
                    <div class="info-row">
                        <label>Email</label>
                        <p><?php echo htmlspecialchars($profile['email']); ?></p>
                    </div>
                    <div class="info-row">
                        <label>Role</label>
                        <p>
                            <span class="role-badge role-<?php echo $profile['role']; ?>">
                                <?php echo ucfirst($profile['role']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <button class="edit-toggle-btn" onclick="toggleEditForm()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit Profile
                </button>

                <div id="editFormWrap" class="edit-form-wrap" style="display:none;">
                    <form method="POST" action="">
                        <div class="sp-field">
                            <label style="font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">First Name</label>
                            <input type="text" name="firstname"
                                   value="<?php echo htmlspecialchars($profile['firstname']); ?>" required>
                        </div>
                        <div class="sp-field">
                            <label style="font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Last Name</label>
                            <input type="text" name="lastname"
                                   value="<?php echo htmlspecialchars($profile['lastname']); ?>" required>
                        </div>
                        <div class="sp-field">
                            <label style="font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">Email</label>
                            <input type="email" name="email"
                                   value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                        </div>
                        <div class="form-actions" style="margin-top:12px;">
                            <button type="submit" name="update_profile" class="btn-save">Save</button>
                            <button type="button" class="btn-cancel" onclick="toggleEditForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- RIGHT: Stats + activity -->
        <div style="display:flex; flex-direction:column; gap:20px;">

            <?php if ($user['role'] !== 'supplier'): ?>
            <!-- Stats -->
            <div class="profile-card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Activity Summary
                </h2>
                <div class="profile-stats-row">
                    <div class="profile-stat">
                        <div class="stat-val"><?php echo $userSalesCount; ?></div>
                        <div class="stat-lbl">Sales Made</div>
                    </div>
                    <div class="profile-stat">
                        <div class="stat-val">&#8369;<?php echo number_format($userSalesTotal, 2); ?></div>
                        <div class="stat-lbl">Total Revenue</div>
                    </div>
                    <div class="profile-stat">
                        <div class="stat-val"><?php echo count($userLogs); ?></div>
                        <div class="stat-lbl">Inventory Actions</div>
                    </div>
                </div>
            </div>

            <!-- Recent activity -->
            <div class="profile-card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Recent Inventory Activity
                </h2>
                <?php if (empty($userLogs)): ?>
                    <div class="empty-state">No inventory activity yet.</div>
                <?php else: ?>
                    <?php foreach (array_slice($userLogs, 0, 10) as $log): ?>
                    <div class="activity-entry">
                        <div class="activity-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <div class="activity-body">
                            <strong><?php echo ucfirst($log['action']); ?> — <?php echo htmlspecialchars($log['product_name']); ?></strong>
                            <span>
                                Qty change: <?php echo ($log['quantity_change'] > 0 ? '+' : '') . $log['quantity_change']; ?>
                                &nbsp;&middot;&nbsp;
                                <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                <?php if ($log['notes']): ?>
                                    &nbsp;&middot;&nbsp; <?php echo htmlspecialchars($log['notes']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php else: /* Supplier view */ ?>

            <!-- Supplier stats -->
            <div class="profile-card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    Catalog Summary
                </h2>
                <div class="profile-stats-row">
                    <div class="profile-stat">
                        <div class="stat-val"><?php echo (int)$supplierStats['total_products']; ?></div>
                        <div class="stat-lbl">Products Listed</div>
                    </div>
                    <div class="profile-stat">
                        <div class="stat-val"><?php echo number_format((int)$supplierStats['total_quantity']); ?></div>
                        <div class="stat-lbl">Units Available</div>
                    </div>
                    <div class="profile-stat">
                        <div class="stat-val"><?php echo count($supplierOrders); ?></div>
                        <div class="stat-lbl">Total Orders</div>
                    </div>
                </div>
            </div>

            <!-- Recent purchase orders for supplier -->
            <div class="profile-card">
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    Recent Purchase Orders
                </h2>
                <?php if (empty($supplierOrders)): ?>
                    <div class="empty-state">No purchase orders yet.</div>
                <?php else: ?>
                <div class="table-container">
                    <table class="po-mini-table">
                        <thead>
                            <tr>
                                <th>PO #</th>
                                <th>Ordered By</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($supplierOrders, 0, 10) as $po): ?>
                            <tr>
                                <td><strong>#<?php echo $po['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($po['admin_firstname'] . ' ' . $po['admin_lastname']); ?></td>
                                <td><?php echo (int)$po['item_count']; ?></td>
                                <td>&#8369;<?php echo number_format($po['total_amount'], 2); ?></td>
                                <td>
                                    <span class="po-status-badge status-<?php echo $po['status']; ?>">
                                        <?php echo ucfirst($po['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($po['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div><!-- /right col -->
    </div><!-- /profile-grid -->
</div>

<script>
function toggleEditForm() {
    const wrap = document.getElementById('editFormWrap');
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}
<?php if (isset($result) && isset($resultClass) && $resultClass === 'error'): ?>
// Keep edit form open on error
document.addEventListener('DOMContentLoaded', () => toggleEditForm());
<?php endif; ?>
</script>
</body>
</html>
