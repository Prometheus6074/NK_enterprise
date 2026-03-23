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
//  CONFIRM — Purchase Order → merge into main inventory
// ─────────────────────────────────────────────────────────────

function confirmPurchaseOrder($connect2db, $orderId, $adminId, &$resultClass, &$result)
{
    $oid = (int)$orderId;
    $aid = (int)$adminId;

    // Verify PO exists and is still pending
    $checkSql = "SELECT id FROM purchase_orders WHERE id = $oid AND status = 'pending'";
    $checkQ   = mysqli_query($connect2db, $checkSql);
    if (!$checkQ || mysqli_num_rows($checkQ) === 0) {
        $resultClass = 'error';
        $result      = 'Purchase order not found or already processed.';
        return false;
    }

    // Fetch all line items
    $itemsSql = "SELECT * FROM purchase_order_items WHERE purchase_order_id = $oid";
    $itemsQ   = mysqli_query($connect2db, $itemsSql);
    if (!$itemsQ) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return false;
    }

    $items = [];
    while ($row = mysqli_fetch_assoc($itemsQ)) {
        $items[] = $row;
    }

    // For each line item: match by SKU → add quantity. No match → skip (product may have been
    // deleted; we still mark PO confirmed but note the unmatched items).
    $unmatched = [];
    foreach ($items as $item) {
        $skuEsc  = mysqli_real_escape_string($connect2db, $item['product_sku']);
        $qty     = (int)$item['quantity'];

        $findSql = "SELECT id, quantity FROM products WHERE sku = '$skuEsc' LIMIT 1";
        $findQ   = mysqli_query($connect2db, $findSql);

        if ($findQ && mysqli_num_rows($findQ) > 0) {
            $product    = mysqli_fetch_assoc($findQ);
            $pid        = (int)$product['id'];
            $newQty     = (int)$product['quantity'] + $qty;

            $updSql = "UPDATE products SET quantity = $newQty WHERE id = $pid";
            if (!mysqli_query($connect2db, $updSql)) {
                $resultClass = 'error';
                $result      = 'Failed to update inventory: ' . mysqli_error($connect2db);
                return false;
            }

            // Inventory log
            $nameEsc  = mysqli_real_escape_string($connect2db, $item['product_name']);
            $notesTxt = mysqli_real_escape_string($connect2db, "Confirmed from PO #$oid");
            $logSql   = "INSERT INTO inventory_logs
                            (product_id, user_id, action, quantity_change, previous_quantity, new_quantity, notes)
                         VALUES ($pid, $aid, 'restocked', $qty, {$product['quantity']}, $newQty, '$notesTxt')";
            mysqli_query($connect2db, $logSql); // non-fatal if log fails
        } else {
            $unmatched[] = $item['product_sku'];
        }
    }

    // Mark PO as confirmed
    $updPoSql = "UPDATE purchase_orders SET status = 'confirmed' WHERE id = $oid";
    if (!mysqli_query($connect2db, $updPoSql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return false;
    }

    $resultClass = 'success';
    if (empty($unmatched)) {
        $result = "Purchase Order #$oid confirmed. Inventory updated successfully.";
    } else {
        $skuList = implode(', ', $unmatched);
        $result  = "Purchase Order #$oid confirmed. Note: the following SKUs were not found in inventory and were skipped: $skuList.";
    }

    return true;
}

// ─────────────────────────────────────────────────────────────
//  CANCEL — Purchase Order
// ─────────────────────────────────────────────────────────────

function cancelPurchaseOrder($connect2db, $orderId, &$resultClass, &$result)
{
    $oid = (int)$orderId;

    // Verify PO is pending
    $checkSql = "SELECT id FROM purchase_orders WHERE id = $oid AND status = 'pending'";
    $checkQ   = mysqli_query($connect2db, $checkSql);
    if (!$checkQ || mysqli_num_rows($checkQ) === 0) {
        $resultClass = 'error';
        $result      = 'Purchase order not found or already processed.';
        return false;
    }

    $sql = "UPDATE purchase_orders SET status = 'cancelled' WHERE id = $oid";
    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return false;
    }

    $resultClass = 'success';
    $result      = "Purchase Order #$oid has been cancelled.";
    return true;
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
