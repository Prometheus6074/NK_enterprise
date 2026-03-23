<?php
include '../db/connect.php';

function createSale($connect2db, $userId, $totalAmount, $paymentMethod, &$resultClass, &$result, $customerName = null, $notes = null)
{
    $sql = "
        INSERT INTO sales (user_id, total_amount, payment_method, customer_name, notes)
        VALUES ('$userId', '$totalAmount', '$paymentMethod', '$customerName', '$notes')
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return false;
    }

    $saleId = mysqli_insert_id($connect2db);
    $resultClass = "success";
    $result = "Sale created successfully";
    return $saleId;
}

function addSaleItem($connect2db, $saleId, $productId, $quantity, $unitPrice, &$resultClass, &$result)
{
    $totalPrice = $quantity * $unitPrice;
    
    $sql = "
        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price)
        VALUES ('$saleId', '$productId', '$quantity', '$unitPrice', '$totalPrice')
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return false;
    }

    return true;
}

function processSale($connect2db, $userId, $cartItems, $paymentMethod, &$resultClass, &$result, $customerName = null, $notes = null)
{
    $totalAmount = 0;
    
    // Calculate total amount and check stock
    foreach ($cartItems as $item) {
        $product = getProduct($connect2db, $item['product_id']);
        
        if ($product['quantity'] < $item['quantity']) {
            $resultClass = "error";
            $result = "Insufficient stock for product: " . $product['name'];
            return false;
        }
        
        $totalAmount += $item['quantity'] * $item['unit_price'];
    }
    
    // Create sale
    $saleId = createSale($connect2db, $userId, $totalAmount, $paymentMethod, $customerName, $notes, $resultClass, $result);
    
    if (!$saleId) {
        return false;
    }
    
    // Add sale items and update inventory
    foreach ($cartItems as $item) {
        if (!addSaleItem($connect2db, $saleId, $item['product_id'], $item['quantity'], $item['unit_price'], $resultClass, $result)) {
            return false;
        }
        
        // Update product quantity
        $product = getProduct($connect2db, $item['product_id']);
        $newQuantity = $product['quantity'] - $item['quantity'];
        
        $updateSql = "UPDATE products SET quantity = $newQuantity WHERE id = " . $item['product_id'];
        if (!mysqli_query($connect2db, $updateSql)) {
            $resultClass = "error";
            $result = "Failed to update inventory";
            return false;
        }
        
        // Log inventory change
        logInventoryChange($connect2db, $item['product_id'], $userId, 'sold', -$item['quantity'], $product['quantity'], $newQuantity, "Sale #$saleId");
    }
    
    $resultClass = "success";
    $result = "Sale completed successfully. Sale ID: $saleId";
    return $saleId;
}

function getSales($connect2db, $limit = 50)
{
    $sql = "SELECT s.*, u.firstname, u.lastname 
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.created_at DESC 
            LIMIT $limit";
    
    $query = mysqli_query($connect2db, $sql);

    $sales = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $sales[] = $row;
    }
    return $sales;
}

function getSaleDetails($connect2db, $saleId)
{
    $sql = "SELECT s.*, u.firstname, u.lastname 
            FROM sales s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = $saleId";
    
    $query = mysqli_query($connect2db, $sql);
    $sale = mysqli_fetch_assoc($query);
    
    if ($sale) {
        $sql = "SELECT si.*, p.name, p.sku 
                FROM sale_items si
                LEFT JOIN products p ON si.product_id = p.id
                WHERE si.sale_id = $saleId";
        
        $query = mysqli_query($connect2db, $sql);
        
        $sale['items'] = [];
        while ($row = mysqli_fetch_assoc($query)) {
            $sale['items'][] = $row;
        }
    }
    
    return $sale;
}

function getTopSellingProducts($connect2db, $limit = 10)
{
    $sql = "SELECT p.id, p.name, p.sku, SUM(si.quantity) as total_sold, SUM(si.total_price) as total_revenue
            FROM products p
            LEFT JOIN sale_items si ON p.id = si.product_id
            LEFT JOIN sales s ON si.sale_id = s.id
            GROUP BY p.id, p.name, p.sku
            HAVING total_sold > 0
            ORDER BY total_sold DESC
            LIMIT $limit";
    
    $query = mysqli_query($connect2db, $sql);

    $products = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $products[] = $row;
    }
    return $products;
}

function getDailySales($connect2db, $date = null)
{
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $sql = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_revenue
            FROM sales
            WHERE DATE(created_at) = '$date'";
    
    $query = mysqli_query($connect2db, $sql);
    return mysqli_fetch_assoc($query);
}

function getMonthlySales($connect2db, $year = null, $month = null)
{
    if (!$year) {
        $year = date('Y');
    }
    if (!$month) {
        $month = date('m');
    }
    
    $sql = "SELECT COUNT(*) as total_sales, SUM(total_amount) as total_revenue
            FROM sales
            WHERE YEAR(created_at) = '$year' AND MONTH(created_at) = '$month'";
    
    $query = mysqli_query($connect2db, $sql);
    return mysqli_fetch_assoc($query);
}

function refundSale($connect2db, $saleId, $userId, &$resultClass, &$result)
{
    $sale = getSaleDetails($connect2db, $saleId);
    
    if (!$sale) {
        $resultClass = "error";
        $result = "Sale not found";
        return false;
    }
    
    // Restock items
    foreach ($sale['items'] as $item) {
        $product = getProduct($connect2db, $item['product_id']);
        $newQuantity = $product['quantity'] + $item['quantity'];
        
        $updateSql = "UPDATE products SET quantity = $newQuantity WHERE id = " . $item['product_id'];
        if (!mysqli_query($connect2db, $updateSql)) {
            $resultClass = "error";
            $result = "Failed to restock items";
            return false;
        }
        
        // Log inventory change
        logInventoryChange($connect2db, $item['product_id'], $userId, 'added', $item['quantity'], $product['quantity'], $newQuantity, "Refund for sale #$saleId");
    }
    
    // Delete sale items and sale
    $deleteItemsSql = "DELETE FROM sale_items WHERE sale_id = $saleId";
    if (!mysqli_query($connect2db, $deleteItemsSql)) {
        $resultClass = "error";
        $result = "Failed to delete sale items";
        return false;
    }
    
    $deleteSaleSql = "DELETE FROM sales WHERE id = $saleId";
    if (!mysqli_query($connect2db, $deleteSaleSql)) {
        $resultClass = "error";
        $result = "Failed to delete sale";
        return false;
    }
    
    $resultClass = "success";
    $result = "Sale refunded successfully";
    return true;
}
?>
