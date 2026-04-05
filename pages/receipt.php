<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/purchase_order_functions.php';

$user  = $_SESSION['user'];
$poId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$poId) {
    header("Location: dashboard.php");
    exit;
}

$order = getPurchaseOrderWithItems($connect2db, $poId);

if (!$order) {
    header("Location: dashboard.php");
    exit;
}

// Access control: admin/manager can see any PO; supplier can only see their own
$isAdmin    = in_array($user['role'], ['admin', 'manager']);
$isSupplier = ($user['role'] === 'supplier' && (int)$order['supplier_id'] === (int)$user['id']);

if (!$isAdmin && !$isSupplier) {
    header("Location: dashboard.php");
    exit;
}

$backUrl = $isSupplier ? 'supplier.php' : 'admin.php#purchase-orders';

// Status color map
$statusColor = [
    'pending'   => '#d97706',
    'confirmed' => '#059669',
    'cancelled' => '#dc2626',
];
$statusBg = [
    'pending'   => '#fef3c7',
    'confirmed' => '#d1fae5',
    'cancelled' => '#fee2e2',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Receipt — PO #<?php echo $order['id']; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background: #f3f4f6;
            display: block;
            padding: 32px 16px;
        }

        .receipt-wrapper {
            max-width: 780px;
            margin: 0 auto;
        }

        /* Action bar (hidden when printing) */
        .receipt-action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .receipt-action-bar a,
        .receipt-action-bar button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .btn-back {
            background: white;
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        .btn-back:hover { background: #f3f4f6; }
        .btn-print {
            background: var(--color-primary);
            color: white;
            border: none;
        }
        .btn-print:hover { background: var(--color-primary-hover); }

        /* Receipt card */
        .receipt-card {
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        /* Receipt header band */
        .receipt-header {
            background: var(--color-primary);
            color: white;
            padding: 28px 36px;
        }
        .receipt-header .company-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
        }
        .receipt-header .receipt-title {
            font-size: 13px;
            opacity: 0.85;
            font-weight: 500;
        }
        .receipt-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .po-meta {
            text-align: right;
        }
        .po-meta .po-number {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .po-meta .po-date {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
        }

        /* Status strip */
        .receipt-status-strip {
            padding: 10px 36px;
            background: <?php echo $statusBg[$order['status']]; ?>;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: <?php echo $statusColor[$order['status']]; ?>;
        }

        /* Party section */
        .receipt-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid var(--color-border);
        }
        .party-block {
            padding: 24px 36px;
        }
        .party-block:first-child {
            border-right: 1px solid var(--color-border);
        }
        .party-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--color-text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .party-label svg { color: var(--color-primary); }
        .party-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 3px;
        }
        .party-email {
            font-size: 13px;
            color: var(--color-text-muted);
        }

        /* Items table */
        .receipt-items {
            padding: 24px 36px;
        }
        .receipt-items h3 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--color-text-muted);
            margin-bottom: 14px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .items-table th {
            background: #f8fafc;
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #374151;
            border-bottom: 2px solid var(--color-border);
        }
        .items-table th:not(:first-child) { text-align: right; }
        .items-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }
        .items-table td:not(:first-child) { text-align: right; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .items-table .sku-cell {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--color-text-muted);
            display: block;
            margin-top: 2px;
        }

        /* Totals */
        .receipt-totals {
            border-top: 2px solid var(--color-border);
            padding: 20px 36px 28px;
            display: flex;
            justify-content: flex-end;
        }
        .totals-table {
            min-width: 260px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
        }
        .totals-row.grand {
            border-top: 1px solid var(--color-border);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 17px;
            font-weight: 700;
            color: var(--color-primary);
        }

        /* Notes */
        .receipt-notes {
            padding: 0 36px 24px;
        }
        .receipt-notes-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--color-text-muted);
            margin-bottom: 6px;
        }
        .receipt-notes-text {
            font-size: 14px;
            color: var(--color-text);
            background: #f8fafc;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
        }

        /* Footer */
        .receipt-footer {
            border-top: 1px solid var(--color-border);
            padding: 16px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: var(--color-text-muted);
        }

        /* Print styles */
        @media print {
            body { background: white; padding: 0; }
            .receipt-action-bar { display: none; }
            .receipt-card { box-shadow: none; border-radius: 0; }
            .receipt-wrapper { max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="receipt-wrapper">

    <!-- Action bar -->
    <div class="receipt-action-bar">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            Back
        </a>
        <button class="btn-print" onclick="window.print()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print Receipt
        </button>
    </div>

    <!-- Receipt card -->
    <div class="receipt-card">

        <!-- Header -->
        <div class="receipt-header">
            <div class="receipt-header-row">
                <div>
                    <div class="company-name">NK Enterprise</div>
                    <div class="receipt-title">Purchase Order Receipt</div>
                </div>
                <div class="po-meta">
                    <div class="po-number">PO #<?php echo $order['id']; ?></div>
                    <div class="po-date">
                        Issued: <?php echo date('F j, Y', strtotime($order['created_at'])); ?><br>
                        <?php echo date('g:i A', strtotime($order['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status strip -->
        <div class="receipt-status-strip">
            <?php if ($order['status'] === 'confirmed'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <?php elseif ($order['status'] === 'cancelled'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="15" y1="9" x2="9" y2="15"/>
                <line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            <?php endif; ?>
            Status: <?php echo ucfirst($order['status']); ?>
            <?php if ($order['status'] === 'confirmed' && $order['updated_at']): ?>
                &nbsp;&middot;&nbsp; Confirmed on <?php echo date('M j, Y', strtotime($order['updated_at'])); ?>
            <?php endif; ?>
        </div>

        <!-- Parties -->
        <div class="receipt-parties">
            <div class="party-block">
                <div class="party-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Ordered By (Admin)
                </div>
                <div class="party-name">
                    <?php echo htmlspecialchars($order['admin_firstname'] . ' ' . $order['admin_lastname']); ?>
                </div>
                <div class="party-email"><?php echo htmlspecialchars($order['admin_email']); ?></div>
            </div>
            <div class="party-block">
                <div class="party-label">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Supplied By
                </div>
                <div class="party-name">
                    <?php echo htmlspecialchars($order['supplier_firstname'] . ' ' . $order['supplier_lastname']); ?>
                </div>
                <div class="party-email"><?php echo htmlspecialchars($order['supplier_email']); ?></div>
            </div>
        </div>

        <!-- Items -->
        <div class="receipt-items">
            <h3>Order Items</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                            <span class="sku-cell"><?php echo htmlspecialchars($item['product_sku']); ?></span>
                        </td>
                        <td><?php echo number_format((int)$item['quantity']); ?></td>
                        <td>&#8369;<?php echo number_format((float)$item['unit_price'], 2); ?></td>
                        <td>&#8369;<?php echo number_format((float)$item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="receipt-totals">
            <div class="totals-table">
                <div class="totals-row">
                    <span>Items ordered</span>
                    <span><?php echo count($order['items']); ?></span>
                </div>
                <div class="totals-row grand">
                    <span>Grand Total</span>
                    <span>&#8369;<?php echo number_format((float)$order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
        <!-- Notes -->
        <div class="receipt-notes">
            <div class="receipt-notes-label">Notes</div>
            <div class="receipt-notes-text"><?php echo htmlspecialchars($order['notes']); ?></div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="receipt-footer">
            <span>NK Enterprise &mdash; Inventory Management System</span>
            <span>Generated <?php echo date('M j, Y g:i A'); ?></span>
        </div>
    </div>
</div>
</body>
</html>
