<?php
include_once '../db/connect.php';

// ─────────────────────────────────────────────
//  IMAGE UPLOAD
// ─────────────────────────────────────────────

function handleSupplierImageUpload($imageFile, &$resultClass, &$result)
{
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($imageFile['type'], $allowed)) {
        $resultClass = 'error';
        $result = 'Invalid image type. Allowed: JPEG, PNG, GIF, WEBP.';
        return false;
    }

    if ($imageFile['size'] > 5 * 1024 * 1024) {
        $resultClass = 'error';
        $result = 'Image too large. Maximum size is 5 MB.';
        return false;
    }

    // Path is relative to the executing script (pages/supplier.php → ../uploads/…)
    $uploadDir = '../uploads/supplier_products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext      = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
    $filename = 'sp_' . uniqid() . '_' . time() . '.' . $ext;
    $target   = $uploadDir . $filename;

    if (!move_uploaded_file($imageFile['tmp_name'], $target)) {
        $resultClass = 'error';
        $result      = 'Failed to save image. Check server permissions.';
        return false;
    }

    // Store root-relative path in DB
    return 'uploads/supplier_products/' . $filename;
}

// ─────────────────────────────────────────────
//  SUPPLIER — READ
// ─────────────────────────────────────────────

function getSupplierProducts($connect2db, $supplierId)
{
    $id  = (int)$supplierId;
    $sql = "SELECT * FROM supplier_products WHERE supplier_id = $id ORDER BY name ASC";
    $q   = mysqli_query($connect2db, $sql);

    $rows = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $rows[] = $row;
    }
    return $rows;
}

function getSupplierProduct($connect2db, $productId, $supplierId)
{
    $pid = (int)$productId;
    $sid = (int)$supplierId;
    $sql = "SELECT * FROM supplier_products WHERE id = $pid AND supplier_id = $sid LIMIT 1";
    $q   = mysqli_query($connect2db, $sql);
    return mysqli_fetch_assoc($q);
}

function getSupplierStats($connect2db, $supplierId)
{
    $id  = (int)$supplierId;
    $sql = "SELECT
                COUNT(*)                    AS total_products,
                COALESCE(SUM(quantity_available), 0) AS total_quantity,
                COUNT(DISTINCT category)    AS total_categories,
                COALESCE(AVG(unit_price), 0)         AS avg_price
            FROM supplier_products
            WHERE supplier_id = $id";
    $q = mysqli_query($connect2db, $sql);
    return mysqli_fetch_assoc($q);
}

// ─────────────────────────────────────────────
//  SUPPLIER — CREATE
// ─────────────────────────────────────────────

function addSupplierProduct($connect2db, $supplierId, $data, $imageFile, &$resultClass, &$result)
{
    $sid         = (int)$supplierId;
    $sku         = mysqli_real_escape_string($connect2db, trim($data['sku']));
    $name        = mysqli_real_escape_string($connect2db, trim($data['name']));
    $description = mysqli_real_escape_string($connect2db, trim($data['description'] ?? ''));
    $category    = mysqli_real_escape_string($connect2db, trim($data['category'] ?? ''));
    $qty         = (int)($data['quantity_available'] ?? 0);
    $price       = (float)($data['unit_price'] ?? 0);

    // Image
    $imageSQL = 'NULL';
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        $path = handleSupplierImageUpload($imageFile, $resultClass, $result);
        if ($path === false) return;
        $escaped  = mysqli_real_escape_string($connect2db, $path);
        $imageSQL = "'$escaped'";
    }

    $sql = "INSERT INTO supplier_products
                (supplier_id, sku, name, description, category, quantity_available, unit_price, image_path)
            VALUES
                ($sid, '$sku', '$name', '$description', '$category', $qty, $price, $imageSQL)";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return;
    }

    $resultClass = 'success';
    $result      = 'Product added successfully.';
}

// ─────────────────────────────────────────────
//  SUPPLIER — UPDATE
// ─────────────────────────────────────────────

function updateSupplierProduct($connect2db, $productId, $supplierId, $data, $imageFile, &$resultClass, &$result)
{
    $existing = getSupplierProduct($connect2db, $productId, $supplierId);
    if (!$existing) {
        $resultClass = 'error';
        $result      = 'Product not found or access denied.';
        return;
    }

    $pid         = (int)$productId;
    $sid         = (int)$supplierId;
    $sku         = mysqli_real_escape_string($connect2db, trim($data['sku']));
    $name        = mysqli_real_escape_string($connect2db, trim($data['name']));
    $description = mysqli_real_escape_string($connect2db, trim($data['description'] ?? ''));
    $category    = mysqli_real_escape_string($connect2db, trim($data['category'] ?? ''));
    $qty         = (int)($data['quantity_available'] ?? 0);
    $price       = (float)($data['unit_price'] ?? 0);

    // New image?
    $imageClause = '';
    if ($imageFile && $imageFile['error'] === UPLOAD_ERR_OK) {
        $path = handleSupplierImageUpload($imageFile, $resultClass, $result);
        if ($path === false) return;

        // Remove old image file
        if ($existing['image_path'] && file_exists('../' . $existing['image_path'])) {
            @unlink('../' . $existing['image_path']);
        }

        $escaped     = mysqli_real_escape_string($connect2db, $path);
        $imageClause = ", image_path = '$escaped'";
    }

    $sql = "UPDATE supplier_products
            SET sku = '$sku',
                name = '$name',
                description = '$description',
                category = '$category',
                quantity_available = $qty,
                unit_price = $price
                $imageClause
            WHERE id = $pid AND supplier_id = $sid";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return;
    }

    $resultClass = 'success';
    $result      = 'Product updated successfully.';
}

// ─────────────────────────────────────────────
//  SUPPLIER — DELETE
// ─────────────────────────────────────────────

function deleteSupplierProduct($connect2db, $productId, $supplierId, &$resultClass, &$result)
{
    $product = getSupplierProduct($connect2db, $productId, $supplierId);
    if (!$product) {
        $resultClass = 'error';
        $result      = 'Product not found or access denied.';
        return;
    }

    $pid = (int)$productId;
    $sid = (int)$supplierId;

    $sql = "DELETE FROM supplier_products WHERE id = $pid AND supplier_id = $sid";
    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return;
    }

    // Remove image file
    if ($product['image_path'] && file_exists('../' . $product['image_path'])) {
        @unlink('../' . $product['image_path']);
    }

    $resultClass = 'success';
    $result      = 'Product deleted successfully.';
}

// ─────────────────────────────────────────────
//  ADMIN HELPERS
// ─────────────────────────────────────────────

function getAllSuppliers($connect2db)
{
    $sql = "SELECT
                u.id, u.firstname, u.lastname, u.email, u.created_at,
                COUNT(sp.id) AS product_count
            FROM users u
            LEFT JOIN supplier_products sp ON sp.supplier_id = u.id
            WHERE u.role = 'supplier'
            GROUP BY u.id
            ORDER BY u.firstname ASC";
    $q = mysqli_query($connect2db, $sql);

    $rows = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $rows[] = $row;
    }
    return $rows;
}

function createSupplier($connect2db, $data, &$resultClass, &$result)
{
    $firstname = mysqli_real_escape_string($connect2db, trim($data['firstname']));
    $lastname  = mysqli_real_escape_string($connect2db, trim($data['lastname']));
    $email     = mysqli_real_escape_string($connect2db, trim($data['email']));
    $password  = $data['password'];

    // Email must be unique
    $check = mysqli_query($connect2db, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check) > 0) {
        $resultClass = 'error';
        $result      = 'Email already exists.';
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql  = "INSERT INTO users (firstname, lastname, email, password, role)
             VALUES ('$firstname', '$lastname', '$email', '$hash', 'supplier')";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return;
    }

    $resultClass = 'success';
    $result      = 'Supplier account created successfully.';
}

function deleteSupplier($connect2db, $userId, &$resultClass, &$result)
{
    $id = (int)$userId;

    // Verify it's actually a supplier
    $check = mysqli_query($connect2db, "SELECT id FROM users WHERE id = $id AND role = 'supplier'");
    if (mysqli_num_rows($check) === 0) {
        $resultClass = 'error';
        $result      = 'Supplier not found.';
        return;
    }

    // Cleanup product images before cascade delete
    $imgQ = mysqli_query($connect2db, "SELECT image_path FROM supplier_products WHERE supplier_id = $id AND image_path IS NOT NULL");
    while ($row = mysqli_fetch_assoc($imgQ)) {
        if (file_exists('../' . $row['image_path'])) {
            @unlink('../' . $row['image_path']);
        }
    }

    $sql = "DELETE FROM users WHERE id = $id AND role = 'supplier'";
    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return;
    }

    $resultClass = 'success';
    $result      = 'Supplier account deleted successfully.';
}

function resetSupplierPassword($connect2db, $userId, $newPassword, &$resultClass, &$result)
{
    $id   = (int)$userId;
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password = '$hash' WHERE id = $id AND role = 'supplier'";
    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = 'error';
        $result      = mysqli_error($connect2db);
        return;
    }

    $resultClass = 'success';
    $result      = 'Password reset successfully.';
}
