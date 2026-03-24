<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';
include '../functions/inventory_functions.php';

$user       = $_SESSION['user'];
$activePage = 'profile';
$profile    = getProfile($connect2db, $user['id']);

if (isset($_POST['update_profile'])) {
    updateProfile($_POST, $connect2db, $user['id'], $resultClass, $result);
    $profile = getProfile($connect2db, $user['id']);
}

$inventoryLogs = getInventoryLogs($connect2db, null, 20);
$userLogs = array_filter($inventoryLogs, fn($log) => $log['user_id'] == $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../css/style.css" />
  <title>Profile — NK Ent</title>
</head>
<body>

<div class="app-layout">
  <?php include '../components/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Profile</h1>
        <span class="page-header-breadcrumb">Your account information</span>
      </div>
    </div>

    <div class="page-body">

      <?php if (isset($result)): ?>
      <div class="message <?php echo $resultClass; ?>"><?php echo htmlspecialchars($result); ?></div>
      <?php endif; ?>

      <div class="profile-section">
        <h2>Personal Information</h2>
        <div class="profile-info">
          <div class="info-item">
            <label>First Name</label>
            <p><?php echo htmlspecialchars($profile['firstname']); ?></p>
          </div>
          <div class="info-item">
            <label>Last Name</label>
            <p><?php echo htmlspecialchars($profile['lastname']); ?></p>
          </div>
          <div class="info-item">
            <label>Email</label>
            <p><?php echo htmlspecialchars($profile['email']); ?></p>
          </div>
          <div class="info-item">
            <label>Role</label>
            <p><?php echo ucfirst($profile['role']); ?></p>
          </div>
        </div>

        <div class="edit-profile-section">
          <h3>Edit Profile</h3>
          <form method="POST" action="" class="profile-form">
            <div class="form-row">
              <input type="text" name="firstname"
                     value="<?php echo htmlspecialchars($profile['firstname']); ?>"
                     placeholder="First Name" required>
              <input type="text" name="lastname"
                     value="<?php echo htmlspecialchars($profile['lastname']); ?>"
                     placeholder="Last Name" required>
            </div>
            <div class="form-row">
              <input type="email" name="email"
                     value="<?php echo htmlspecialchars($profile['email']); ?>"
                     placeholder="Email" required>
            </div>
            <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
          </form>
        </div>
      </div>

      <div class="user-stats-section">
        <h2>Your Activity</h2>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-card-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
              </svg>
            </div>
            <h3>Total Sales</h3>
            <p><?php echo count(array_filter($inventoryLogs, fn($l) => $l['user_id'] == $user['id'] && $l['action'] === 'sold')); ?></p>
          </div>
          <div class="stat-card">
            <div class="stat-card-icon" style="background:#d1fae5;color:#059669;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
              </svg>
            </div>
            <h3>Inventory Actions</h3>
            <p><?php echo count($userLogs); ?></p>
          </div>
        </div>
      </div>

      <div class="recent-activity-section">
        <h2>Recent Activity</h2>
        <?php if (empty($userLogs)): ?>
          <p class="text-muted">No recent activity.</p>
        <?php else: ?>
        <div class="activity-list">
          <?php foreach (array_slice($userLogs, 0, 10) as $log): ?>
          <div class="activity-item">
            <div class="activity-info">
              <strong><?php echo ucfirst($log['action']); ?></strong>
              <span class="activity-product"><?php echo htmlspecialchars($log['product_name']); ?></span>
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

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

</body>
</html>
