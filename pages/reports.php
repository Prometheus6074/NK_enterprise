<?php
session_start();
include '../db/connect.php';
include '../functions/auth_guard.php';

if ($_SESSION['user']['role'] === 'supplier') { header("Location: supplier.php"); exit; }
if ($_SESSION['user']['role'] !== 'admin')    { header("Location: dashboard.php"); exit; }

include '../functions/admin_functions.php';
include '../functions/pos_functions.php';

$user       = $_SESSION['user'];
$activePage = 'reports';

if (isset($_POST['export_data'])) {
    $exportData = exportData(
        $connect2db,
        $_POST['export_type'],
        $resultClass,
        $result,
        $_POST['start_date'] ?? null,
        $_POST['end_date']   ?? null
    );
}

$salesReport      = getSalesReport($connect2db);
$topProducts      = getTopProductsReport($connect2db);
$staffPerformance = getStaffPerformanceReport($connect2db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../css/style.css" />
  <title>Reports — NK Ent</title>
</head>
<body>

<div class="app-layout">
  <?php include '../components/sidebar.php'; ?>

  <div class="main-content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Reports</h1>
        <span class="page-header-breadcrumb">Sales analytics &amp; performance</span>
      </div>
    </div>

    <div class="page-body">

      <?php if (isset($result)): ?>
      <div class="message <?php echo $resultClass; ?>"><?php echo htmlspecialchars($result); ?></div>
      <?php endif; ?>

      <div class="reports-grid">

        <!-- Sales Report -->
        <div class="report-section">
          <h3>Sales Report</h3>
          <div class="table-container">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Sales Count</th>
                  <th>Revenue</th>
                  <th>Avg Sale</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($salesReport)): ?>
                <tr><td colspan="4" class="text-muted" style="text-align:center;padding:24px;">No sales data yet.</td></tr>
                <?php else: ?>
                <?php foreach (array_slice($salesReport, 0, 10) as $r): ?>
                <tr>
                  <td><?php echo date('M j, Y', strtotime($r['date'])); ?></td>
                  <td><?php echo $r['sales_count']; ?></td>
                  <td>₱<?php echo number_format($r['revenue'], 2); ?></td>
                  <td>₱<?php echo number_format($r['avg_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top Selling Products -->
        <div class="report-section">
          <h3>Top Selling Products</h3>
          <div class="table-container">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Units Sold</th>
                  <th>Revenue</th>
                  <th>Transactions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($topProducts)): ?>
                <tr><td colspan="4" class="text-muted" style="text-align:center;padding:24px;">No sales data yet.</td></tr>
                <?php else: ?>
                <?php foreach ($topProducts as $p): ?>
                <tr>
                  <td><?php echo htmlspecialchars($p['name']); ?></td>
                  <td><?php echo $p['total_sold']; ?></td>
                  <td>₱<?php echo number_format($p['total_revenue'], 2); ?></td>
                  <td><?php echo $p['sales_count']; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Staff Performance -->
        <div class="report-section">
          <h3>Staff Performance</h3>
          <div class="table-container">
            <table class="report-table">
              <thead>
                <tr>
                  <th>Staff Member</th>
                  <th>Role</th>
                  <th>Sales</th>
                  <th>Revenue</th>
                  <th>Avg Sale</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($staffPerformance)): ?>
                <tr><td colspan="5" class="text-muted" style="text-align:center;padding:24px;">No performance data yet.</td></tr>
                <?php else: ?>
                <?php foreach ($staffPerformance as $s): ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname']); ?></td>
                  <td><?php echo ucfirst($s['role']); ?></td>
                  <td><?php echo $s['sales_count']; ?></td>
                  <td>₱<?php echo number_format($s['total_revenue'], 2); ?></td>
                  <td>₱<?php echo number_format($s['avg_sale_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div><!-- /reports-grid -->

      <!-- Export -->
      <div class="export-section">
        <h3>Export Data</h3>
        <form method="POST" action="" class="export-form">
          <div class="form-row">
            <select name="export_type" required>
              <option value="">Select Data Type</option>
              <option value="products">Products</option>
              <option value="sales">Sales</option>
              <option value="users">Users</option>
            </select>
            <input type="date" name="start_date">
            <input type="date" name="end_date">
            <button type="submit" name="export_data" class="btn-primary">Export Data</button>
          </div>
        </form>
      </div>

    </div><!-- /page-body -->
  </div><!-- /main-content -->
</div><!-- /app-layout -->

</body>
</html>
