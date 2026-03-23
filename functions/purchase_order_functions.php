<?php
include_once '../db/connect.php';

// ─────────────────────────────────────────────────────────────
//  READ — Supplier catalog (admin view)
// ─────────────────────────────────────────────────────────────

function getSupplierCatalogForAdmin($connect2db, $supplierId)
{
    $sid = (int)$supplierId;
    $sql = "SELECT * FROM supplier_products
            WHERE supplier_id = $sid
            ORDER BY name ASC";
    $q   = mysqli_query($connect2db, $sql);

    $rows = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $rows[] = $row;
    }
    return $rows;
}

// ─────────────────────────────────────────────────────────────
//  CREATE — Purchase Order
// ─────────────────────────────────────────────────────────────

function createPurchaseOrder($connect2db, $adminId, $supplierId, $items, $notes, &$resultClass, &$result)
{
    $aid      = (int)$adminId;
    $sid      = (int)$supplierId;
    $notesEsc = mysqli_real_escape_string($connect2db, $notes ?? '');

    // Calculate grand total from checked items only (qty > 0)
    $total = 0;
    foreach ($items as $item) {
        $total += (int)$item['quantity'] * (float)$item['unit_price'];
    }

    if ($total <= 0) {
        $resultClass = 'error';
        $result      = 'Purchase order total must be greater than zero.';
        return false;
    }

    // Insert PO header
    $sql = "INSERT INTO purchase_orders (admin_id, supplier_id, status, total_amount, notes)
            VALUES ($aid, $sid, 'pending', $total, '$notesEsc')";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return false;
    }

    $orderId = mysqli_insert_id($connect2db);

    // Insert line items
    foreach ($items as $item) {
        $pid        = (int)$item['product_id'];
        $qty        = (int)$item['quantity'];
        $price      = (float)$item['unit_price'];
        $lineTotal  = $qty * $price;
        $nameEsc    = mysqli_real_escape_string($connect2db, $item['product_name']);
        $skuEsc     = mysqli_real_escape_string($connect2db, $item['product_sku']);

        $sql = "INSERT INTO purchase_order_items
                    (purchase_order_id, supplier_product_id, product_name, product_sku, quantity, unit_price, total_price)
                VALUES ($orderId, $pid, '$nameEsc', '$skuEsc', $qty, $price, $lineTotal)";

        if (!mysqli_query($connect2db, $sql)) {
            $resultClass = 'error';
            $result      = mysqli_error($connect2db);
            return false;
        }
    }

    $resultClass = 'success';
    $result      = "Purchase Order #$orderId created successfully. It will appear in Incoming Stock below.";
    return $orderId;
}

// ─────────────────────────────────────────────────────────────
//  READ — All Purchase Orders (with join)
// ─────────────────────────────────────────────────────────────

function getPurchaseOrders($connect2db, $status = null)
{
    $sql = "SELECT
                po.*,
                u_adm.firstname AS admin_firstname,
                u_adm.lastname  AS admin_lastname,
                u_sup.firstname AS supplier_firstname,
                u_sup.lastname  AS supplier_lastname,
                COUNT(poi.id)   AS item_count
            FROM purchase_orders po
            LEFT JOIN users u_adm ON u_adm.id = po.admin_id
            LEFT JOIN users u_sup ON u_sup.id = po.supplier_id
            LEFT JOIN purchase_order_items poi ON poi.purchase_order_id = po.id";

    if ($status) {
        $esc  = mysqli_real_escape_string($connect2db, $status);
        $sql .= " WHERE po.status = '$esc'";
    }

    $sql .= " GROUP BY po.id ORDER BY po.created_at DESC";

    $q    = mysqli_query($connect2db, $sql);
    $rows = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $rows[] = $row;
    }
    return $rows;
}

// ─────────────────────────────────────────────────────────────
//  READ — Single PO with line items
// ─────────────────────────────────────────────────────────────

function getPurchaseOrderWithItems($connect2db, $orderId)
{
    $oid = (int)$orderId;

    $sql = "SELECT
                po.*,
                u_adm.firstname AS admin_firstname,
                u_adm.lastname  AS admin_lastname,
                u_sup.firstname AS supplier_firstname,
                u_sup.lastname  AS supplier_lastname
            FROM purchase_orders po
            LEFT JOIN users u_adm ON u_adm.id = po.admin_id
            LEFT JOIN users u_sup ON u_sup.id = po.supplier_id
            WHERE po.id = $oid";

    $q     = mysqli_query($connect2db, $sql);
    $order = mysqli_fetch_assoc($q);
    if (!$order) {
        return null;
    }

    $iSql = "SELECT * FROM purchase_order_items WHERE purchase_order_id = $oid ORDER BY id ASC";
    $iq   = mysqli_query($connect2db, $iSql);

    $order['items'] = [];
    while ($row = mysqli_fetch_assoc($iq)) {
        $order['items'][] = $row;
    }

    return $order;
}
