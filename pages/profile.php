<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/inventory_functions.php';
include '../functions/pos_functions.php';

$user = $_SESSION['user'];
$profile = getProfile($connect2db, $user['id']);

// Handle profile update
if (isset($_POST['update_profile'])) {
    updateProfile($_POST, $connect2db, $user['id'], $resultClass, $result);
}

// Get user statistics
$userSales = getSalesReport($connect2db, null, null);
$userSalesCount = 0;
$userSalesTotal = 0;
foreach ($userSales as $sale) {
    if ($sale['id'] == $user['id']) {
        $userSalesCount++;
        $userSalesTotal += $sale['total_amount'];
    }
}

$inventoryLogs = getInventoryLogs($connect2db, null, 20);
$userLogs = array_filter($inventoryLogs, function($log) use ($user) {
    return $log['user_id'] == $user['id'];
});

?>

<!DOCTYPE html>
<html>

<head>
    <title>Profile</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

    <div class="dashboard-card">
        <div class="dashboard-header">
            <p>Profile</p>
            <div class="nav-links">
                <a href="dashboard.php" class="dashboard-link">Dashboard</a>
                <?php if ($user['role'] === 'admin'): ?>
                <a href="admin.php" class="admin-link">Admin</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin' || $user['role'] === 'manager'): ?>
                <a href="pos.php" class="pos-link">POS</a>
                <?php endif; ?>
                <a href="logout.php" class="logout-link">Logout</a>
            </div>
        </div>

        <?php if (isset($result)): ?>
        <div class="message <?php echo $resultClass; ?>">
            <?php echo $result; ?>
        </div>
        <?php endif; ?>

        <div class="profile-section">
            <h2>Personal Information</h2>
            <div class="profile-info">
                <div class="info-item">
                    <label>First Name:</label>
                    <p><?php echo htmlspecialchars($profile['firstname']); ?></p>
                </div>
                <div class="info-item">
                    <label>Last Name:</label>
                    <p><?php echo htmlspecialchars($profile['lastname']); ?></p>
                </div>
                <div class="info-item">
                    <label>Email:</label>
                    <p><?php echo htmlspecialchars($profile['email']); ?></p>
                </div>
                <div class="info-item">
                    <label>Role:</label>
                    <p><?php echo ucfirst($profile['role']); ?></p>
                </div>
            </div>
            
            <!-- Edit Profile Form -->
            <div class="edit-profile-section">
                <h3>Edit Profile</h3>
                <form method="POST" action="" class="profile-form">
                    <div class="form-row">
                        <input type="text" name="firstname" value="<?php echo htmlspecialchars($profile['firstname']); ?>" placeholder="First Name" required>
                        <input type="text" name="lastname" value="<?php echo htmlspecialchars($profile['lastname']); ?>" placeholder="Last Name" required>
                    </div>
                    <div class="form-row">
                        <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" placeholder="Email" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                </form>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="user-stats-section">
            <h2>Your Activity</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Sales</h3>
                    <p><?php echo count(array_filter($userSales, function($sale) use ($user) { return $sale['id'] == $user['id']; })); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Sales Revenue</h3>
                    <p>$<?php echo number_format(array_sum(array_map(function($sale) use ($user) { 
                        return $sale['id'] == $user['id'] ? $sale['total_amount'] : 0; 
                    }, $userSales)), 2); ?></p>
                </div>
                <div class="stat-card">
                    <h3>Inventory Actions</h3>
                    <p><?php echo count($userLogs); ?></p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity-section">
            <h2>Recent Activity</h2>
            <?php if (empty($userLogs)): ?>
                <p>No recent activity.</p>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach (array_slice($userLogs, 0, 10) as $log): ?>
                        <div class="activity-item">
                            <div class="activity-info">
                                <strong><?php echo ucfirst($log['action']); ?></strong>
                                <span class="activity-product"><?php echo $log['product_name']; ?></span>
                                <span class="activity-quantity">Qty: <?php echo $log['quantity_change']; ?></span>
                                <span class="activity-time"><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?></span>
                            </div>
                            <?php if ($log['notes']): ?>
                                <div class="activity-notes"><?php echo htmlspecialchars($log['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>


</body>

</html>
