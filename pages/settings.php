<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';

if ($_SESSION['user']['role'] === 'supplier') { header("Location: supplier.php"); exit; }
if ($_SESSION['user']['role'] !== 'admin')    { header("Location: dashboard.php"); exit; }

include '../functions/admin_functions.php';

$user       = $_SESSION['user'];
$activePage = 'settings';

if (isset($_POST['update_settings'])) updateSystemSettings($connect2db, $_POST, $resultClass, $result);
if (isset($_POST['backup_database'])) backupDatabase($connect2db, $resultClass, $result);

$systemSettings = getSystemSettings($connect2db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../css/style.css" />
  <title>Settings — NK Ent</title>
</head>
<body>

<div class="app-layout">
  <?php include '../components/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Settings</h1>
        <span class="page-header-breadcrumb">System configuration &amp; maintenance</span>
      </div>
    </div>

    <div class="page-body">

      <?php if (isset($result)): ?>
      <div class="message <?php echo $resultClass; ?>"><?php echo htmlspecialchars($result); ?></div>
      <?php endif; ?>

      <!-- General Settings -->
      <div class="settings-section">
        <h3>General Settings</h3>
        <form method="POST" action="" class="settings-form">
          <div class="form-row">
            <label>Company Name:</label>
            <input type="text" name="company_name"
                   value="<?php echo htmlspecialchars($systemSettings['company_name']); ?>">
          </div>
          <div class="form-row">
            <label>Company Email:</label>
            <input type="email" name="company_email"
                   value="<?php echo htmlspecialchars($systemSettings['company_email']); ?>">
          </div>
          <div class="form-row">
            <label>Currency:</label>
            <select name="currency">
              <option value="PHP" <?php echo $systemSettings['currency'] === 'PHP' ? 'selected' : ''; ?>>PHP (Philippine Peso)</option>
              <option value="USD" <?php echo $systemSettings['currency'] === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
              <option value="EUR" <?php echo $systemSettings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
            </select>
          </div>
          <div class="form-row">
            <label>Tax Rate (%):</label>
            <input type="number" name="tax_rate"
                   value="<?php echo $systemSettings['tax_rate'] * 100; ?>" step="0.1">
            <small style="color:var(--color-text-muted);font-size:12px;align-self:center;">
              Current: <?php echo $systemSettings['tax_rate'] * 100; ?>% (PH VAT)
            </small>
          </div>
          <div class="form-row">
            <label>Low Stock Threshold:</label>
            <input type="number" name="low_stock_threshold"
                   value="<?php echo $systemSettings['low_stock_threshold']; ?>">
          </div>
          <div class="form-actions">
            <button type="submit" name="update_settings" class="btn-primary">Save Settings</button>
          </div>
        </form>
      </div>

      <!-- System Maintenance -->
      <div class="maintenance-section">
        <h3>System Maintenance</h3>
        <p class="text-muted" style="margin-bottom:16px;">
          Create a full backup of the database. This captures all current data and can be used for recovery.
        </p>
        <form method="POST" action="" style="display:inline;">
          <button type="submit" name="backup_database" class="btn-primary">Backup Database</button>
        </form>
      </div>

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

</body>
</html>
