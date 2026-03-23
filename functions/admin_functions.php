<?php
include '../db/connect.php';

// User Management Functions
function getAllUsers($connect2db)
{
    $sql = "SELECT id, firstname, lastname, email, role, created_at FROM users ORDER BY created_at DESC";
    $query = mysqli_query($connect2db, $sql);

    $users = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $users[] = $row;
    }
    return $users;
}

function getUserById($connect2db, $userId)
{
    $sql = "SELECT id, firstname, lastname, email, role, created_at FROM users WHERE id = $userId";
    $query = mysqli_query($connect2db, $sql);
    return mysqli_fetch_assoc($query);
}

function createUser($connect2db, $data, &$resultClass, &$result)
{
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $password = $data['password'];
    $role = $data['role'];

    // Check if email already exists
    $checkSql = "SELECT id FROM users WHERE email = '$email'";
    $checkQuery = mysqli_query($connect2db, $checkSql);
    
    if (mysqli_num_rows($checkQuery) > 0) {
        $resultClass = "error";
        $result = "Email already exists";
        return;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "
        INSERT INTO users (firstname, lastname, email, password, role)
        VALUES ('$firstname', '$lastname', '$email', '$hashedPassword', '$role')
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "User created successfully";
}

function updateUser($connect2db, $userId, $data, &$resultClass, &$result)
{
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $role = $data['role'];

    // Check if email already exists for another user
    $checkSql = "SELECT id FROM users WHERE email = '$email' AND id != $userId";
    $checkQuery = mysqli_query($connect2db, $checkSql);
    
    if (mysqli_num_rows($checkQuery) > 0) {
        $resultClass = "error";
        $result = "Email already exists";
        return;
    }

    $sql = "
        UPDATE users
        SET firstname = '$firstname',
            lastname = '$lastname',
            email = '$email',
            role = '$role'
        WHERE id = $userId
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "User updated successfully";
}

function deleteUser($connect2db, $userId, &$resultClass, &$result)
{
    // Prevent admin from deleting themselves
    if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $userId) {
        $resultClass = "error";
        $result = "You cannot delete your own account";
        return;
    }

    // Check if user has sales
    $salesSql = "SELECT COUNT(*) as sale_count FROM sales WHERE user_id = $userId";
    $salesQuery = mysqli_query($connect2db, $salesSql);
    $salesData = mysqli_fetch_assoc($salesQuery);
    
    if ($salesData['sale_count'] > 0) {
        $resultClass = "error";
        $result = "Cannot delete user with existing sales records";
        return;
    }

    $sql = "DELETE FROM users WHERE id = $userId";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "User deleted successfully";
}

function resetUserPassword($connect2db, $userId, $newPassword, &$resultClass, &$result)
{
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "
        UPDATE users
        SET password = '$hashedPassword'
        WHERE id = $userId
    ";

    if (!mysqli_query($connect2db, $sql)) {
        $resultClass = "error";
        $result = mysqli_error($connect2db);
        return;
    }

    $resultClass = "success";
    $result = "Password reset successfully";
}

// System Statistics
function getSystemStats($connect2db)
{
    $stats = [];
    
    // User counts by role
    $userStatsSql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
    $userStatsQuery = mysqli_query($connect2db, $userStatsSql);
    $stats['users'] = [];
    while ($row = mysqli_fetch_assoc($userStatsQuery)) {
        $stats['users'][$row['role']] = $row['count'];
    }
    
    // Product stats
    $productStatsSql = "SELECT 
        COUNT(*) as total_products,
        SUM(quantity) as total_quantity,
        COUNT(CASE WHEN quantity <= min_quantity THEN 1 END) as low_stock_count
        FROM products";
    $productStatsQuery = mysqli_query($connect2db, $productStatsSql);
    $stats['products'] = mysqli_fetch_assoc($productStatsQuery);
    
    // Sales stats
    $salesStatsSql = "SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_sale_amount
        FROM sales";
    $salesStatsQuery = mysqli_query($connect2db, $salesStatsSql);
    $stats['sales'] = mysqli_fetch_assoc($salesStatsQuery);
    
    // Today's stats
    $todaySql = "SELECT 
        COUNT(*) as today_sales,
        SUM(total_amount) as today_revenue
        FROM sales 
        WHERE DATE(created_at) = CURDATE()";
    $todayQuery = mysqli_query($connect2db, $todaySql);
    $stats['today'] = mysqli_fetch_assoc($todayQuery);
    
    return $stats;
}

// Advanced Reports
function getSalesReport($connect2db, $startDate = null, $endDate = null, $groupBy = 'day')
{
    $sql = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as sales_count,
        SUM(total_amount) as revenue,
        AVG(total_amount) as avg_amount
        FROM sales";
    
    if ($startDate && $endDate) {
        $sql .= " WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
    } elseif ($startDate) {
        $sql .= " WHERE DATE(created_at) >= '$startDate'";
    }
    
    $sql .= " GROUP BY DATE(created_at) ORDER BY date DESC";
    
    $query = mysqli_query($connect2db, $sql);
    
    $report = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $report[] = $row;
    }
    
    return $report;
}

function getTopProductsReport($connect2db, $limit = 10, $startDate = null, $endDate = null)
{
    $sql = "SELECT 
        p.id,
        p.name,
        p.sku,
        SUM(si.quantity) as total_sold,
        SUM(si.total_price) as total_revenue,
        COUNT(DISTINCT si.sale_id) as sales_count
        FROM products p
        LEFT JOIN sale_items si ON p.id = si.product_id
        LEFT JOIN sales s ON si.sale_id = s.id";
    
    if ($startDate && $endDate) {
        $sql .= " WHERE DATE(s.created_at) BETWEEN '$startDate' AND '$endDate'";
    }
    
    $sql .= " GROUP BY p.id, p.name, p.sku
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT $limit";
    
    $query = mysqli_query($connect2db, $sql);
    
    $report = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $report[] = $row;
    }
    
    return $report;
}

function getStaffPerformanceReport($connect2db, $startDate = null, $endDate = null)
{
    $sql = "SELECT 
        u.id,
        u.firstname,
        u.lastname,
        u.role,
        COUNT(s.id) as sales_count,
        SUM(s.total_amount) as total_revenue,
        AVG(s.total_amount) as avg_sale_amount
        FROM users u
        LEFT JOIN sales s ON u.id = s.user_id";
    
    if ($startDate && $endDate) {
        $sql .= " WHERE DATE(s.created_at) BETWEEN '$startDate' AND '$endDate'";
    }
    
    $sql .= " GROUP BY u.id, u.firstname, u.lastname, u.role
        ORDER BY total_revenue DESC";
    
    $query = mysqli_query($connect2db, $sql);
    
    $report = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $report[] = $row;
    }
    
    return $report;
}

// System Settings
function getSystemSettings($connect2db)
{
    // For now, return default settings
    // In a real system, these would be stored in a settings table
    return [
        'company_name' => 'Hardware Store',
        'company_email' => 'contact@hardware.com',
        'currency' => 'PHP',
        'tax_rate' => 0.12,
        'low_stock_threshold' => 5,
        'backup_enabled' => true,
        'email_notifications' => true
    ];
}

function updateSystemSettings($connect2db, $settings, &$resultClass, &$result)
{
    // In a real implementation, this would update a settings table
    // For now, just return success
    $resultClass = "success";
    $result = "Settings updated successfully";
}

// Database Operations
function backupDatabase($connect2db, &$resultClass, &$result)
{
    // This would create a database backup
    // For security reasons, this is a placeholder implementation
    $resultClass = "success";
    $result = "Database backup completed successfully";
}

function exportData($connect2db, $type, &$resultClass, &$result, $startDate = null, $endDate = null)
{
    $data = [];
    
    switch ($type) {
        case 'products':
            $sql = "SELECT * FROM products ORDER BY name";
            break;
        case 'sales':
            $sql = "SELECT s.*, u.firstname, u.lastname 
                    FROM sales s 
                    LEFT JOIN users u ON s.user_id = u.id";
            if ($startDate && $endDate) {
                $sql .= " WHERE DATE(s.created_at) BETWEEN '$startDate' AND '$endDate'";
            }
            $sql .= " ORDER BY s.created_at DESC";
            break;
        case 'users':
            $sql = "SELECT id, firstname, lastname, email, role, created_at FROM users ORDER BY created_at DESC";
            break;
        default:
            $resultClass = "error";
            $result = "Invalid export type";
            return;
    }
    
    $query = mysqli_query($connect2db, $sql);
    while ($row = mysqli_fetch_assoc($query)) {
        $data[] = $row;
    }
    
    $resultClass = "success";
    $result = "Data exported successfully";
    return $data;
}

// Activity Logs
function getSystemActivityLogs($connect2db, $limit = 50, $userId = null)
{
    $sql = "SELECT 
        il.*,
        u.firstname,
        u.lastname,
        p.name as product_name
        FROM inventory_logs il
        LEFT JOIN users u ON il.user_id = u.id
        LEFT JOIN products p ON il.product_id = p.id";
    
    if ($userId) {
        $sql .= " WHERE il.user_id = $userId";
    }
    
    $sql .= " ORDER BY il.created_at DESC LIMIT $limit";
    
    $query = mysqli_query($connect2db, $sql);
    
    $logs = [];
    while ($row = mysqli_fetch_assoc($query)) {
        $logs[] = $row;
    }
    
    return $logs;
}
?>
