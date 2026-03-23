<?php
include '../db/connect.php';

function getProfile($connect2db, $userId)
{
    $sql = "SELECT firstname, lastname, email, role FROM users WHERE id = $userId";
    $query = mysqli_query($connect2db, $sql);

    return mysqli_fetch_assoc($query);
}

function updateProfile($data, $connect2db, $userId, &$resultClass, &$result)
{
    $firstname = $data['firstname'];
    $lastname  = $data['lastname'];
    $email     = $data['email'];

    $sql = "
        UPDATE users
        SET firstname = '$firstname',
            lastname = '$lastname',
            email = '$email'
        WHERE id = $userId
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Updated Successfully";

    $_SESSION['user']['firstname'] = $firstname;
    $_SESSION['user']['lastname'] = $lastname;
    $_SESSION['user']['email'] = $email;
}

function getCategories($connect2db)
{
    $sql = "SELECT * FROM categories ORDER BY name";
    $query = mysqli_query($connect2db, $sql);

    $categories = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $categories[] = $row;
    }
    return $categories;
}

function createCategory($connect2db, $data, &$resultClass, &$result)
{
    $name = $data['name'];
    $description = $data['description'];

    $sql = "
        INSERT INTO categories (name, description)
        VALUES ('$name', '$description')
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Category created successfully";
}

function getProducts($connect2db, $categoryId = null)
{
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id";
    
    if ($categoryId) {
        $sql .= " WHERE p.category_id = $categoryId";
    }
    
    $sql .= " ORDER BY p.name";
    
    $query = mysqli_query($connect2db, $sql);

    $products = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $products[] = $row;
    }
    return $products;
}

function createProduct($connect2db, $data, &$resultClass, &$result)
{
    $sku = $data['sku'];
    $name = $data['name'];
    $description = $data['description'];
    $category_id = $data['category_id'];
    $quantity = $data['quantity'];
    $min_quantity = $data['min_quantity'];
    $unit_price = $data['unit_price'];
    $supplier = $data['supplier'];
    $location = $data['location'];

    $sql = "
        INSERT INTO products (sku, name, description, category_id, quantity, min_quantity, unit_price, supplier, location)
        VALUES (
            '$sku',
            '$name',
            '$description',
            '$category_id',
            '$quantity',
            '$min_quantity',
            '$unit_price',
            '$supplier',
            '$location'
        )
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Product added successfully";
}

function updateProduct($connect2db, $productId, $data, &$resultClass, &$result)
{
    $sku = $data['sku'];
    $name = $data['name'];
    $description = $data['description'];
    $category_id = $data['category_id'];
    $quantity = $data['quantity'];
    $min_quantity = $data['min_quantity'];
    $unit_price = $data['unit_price'];
    $supplier = $data['supplier'];
    $location = $data['location'];

    $sql = "
        UPDATE products
        SET sku = '$sku',
            name = '$name',
            description = '$description',
            category_id = '$category_id',
            quantity = '$quantity',
            min_quantity = '$min_quantity',
            unit_price = '$unit_price',
            supplier = '$supplier',
            location = '$location'
        WHERE id = $productId
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Product updated successfully";
}

function deleteProduct($connect2db, $productId, &$resultClass, &$result)
{
    $sql = "DELETE FROM products WHERE id = $productId";
    
    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }
    
    $resultClass = "success";
    $result = "Product deleted successfully";
}

function getProduct($connect2db, $productId)
{
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = $productId";
    $query = mysqli_query($connect2db, $sql);
    return mysqli_fetch_assoc($query);
}

function searchProducts($connect2db, $searchTerm)
{
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.name LIKE '%$searchTerm%' 
            OR p.sku LIKE '%$searchTerm%' 
            OR p.description LIKE '%$searchTerm%'
            ORDER BY p.name";
    
    $query = mysqli_query($connect2db, $sql);

    $products = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $products[] = $row;
    }
    return $products;
}

function getLowStockProducts($connect2db)
{
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.quantity <= p.min_quantity 
            ORDER BY p.quantity ASC";
    
    $query = mysqli_query($connect2db, $sql);

    $products = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $products[] = $row;
    }
    return $products;
}

function logInventoryChange($connect2db, $productId, $userId, $action, $quantityChange, $previousQuantity, $newQuantity, $notes = null)
{
    $sql = "
        INSERT INTO inventory_logs (product_id, user_id, action, quantity_change, previous_quantity, new_quantity, notes)
        VALUES ('$productId', '$userId', '$action', '$quantityChange', '$previousQuantity', '$newQuantity', '$notes')
    ";
    
    mysqli_query($connect2db, $sql);
}

function getInventoryLogs($connect2db, $productId = null, $limit = 50)
{
    $sql = "SELECT il.*, u.firstname, u.lastname, p.name as product_name 
            FROM inventory_logs il
            LEFT JOIN users u ON il.user_id = u.id
            LEFT JOIN products p ON il.product_id = p.id";
    
    if ($productId) {
        $sql .= " WHERE il.product_id = $productId";
    }
    
    $sql .= " ORDER BY il.created_at DESC LIMIT $limit";
    
    $query = mysqli_query($connect2db, $sql);

    $logs = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $logs[] = $row;
    }
    return $logs;
}

function adjustInventory($connect2db, $productId, $userId, $newQuantity, $notes, &$resultClass, &$result)
{
    $product = getProduct($connect2db, $productId);
    $previousQuantity = $product['quantity'];
    $quantityChange = $newQuantity - $previousQuantity;
    
    $sql = "UPDATE products SET quantity = $newQuantity WHERE id = $productId";
    
    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }
    
    $action = $quantityChange > 0 ? 'restocked' : ($quantityChange < 0 ? 'sold' : 'adjusted');
    logInventoryChange($connect2db, $productId, $userId, $action, $quantityChange, $previousQuantity, $newQuantity, $notes);
    
    $resultClass = "success";
    $result = "Inventory adjusted successfully";
}
?>
