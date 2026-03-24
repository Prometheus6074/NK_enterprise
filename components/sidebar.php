<?php
// components/sidebar.php
// Requires $user and $activePage to be set before including
// $activePage: 'dashboard' | 'pos' | 'admin' | 'reports' | 'settings' | 'profile' | 'supplier'

$activePage   = $activePage ?? '';
$userInitials = strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1));
$isAdmin      = $user['role'] === 'admin';
$isManager    = $user['role'] === 'manager';
$isSupplier   = $user['role'] === 'supplier';
?>

<aside class="sidebar" id="appSidebar">
  <a href="<?php echo $isSupplier ? 'supplier.php' : 'dashboard.php'; ?>" class="sidebar-logo">
    <div class="sidebar-logo-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16.5 9.4 7.55 4.24"/>
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
        <line x1="12" y1="22.08" x2="12" y2="12"/>
      </svg>
    </div>
    <div>
      <span class="sidebar-logo-text">NK Ent</span>
      <span class="sidebar-logo-sub">Inventory System</span>
    </div>
  </a>

  <div class="sidebar-section">

    <?php if (!$isSupplier): ?>

    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-nav">
      <li>
        <a href="dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
          <!-- layout-dashboard -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
          Dashboard
        </a>
      </li>
      <li>
        <a href="pos.php" class="<?php echo $activePage === 'pos' ? 'active' : ''; ?>">
          <!-- shopping-cart -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
          </svg>
          Point of Sale
        </a>
      </li>
    </ul>

    <?php if ($isAdmin): ?>
    <div class="sidebar-section-label">Administration</div>
    <ul class="sidebar-nav">
      <li>
        <a href="admin.php" class="<?php echo $activePage === 'admin' ? 'active' : ''; ?>">
          <!-- shield -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          Admin Panel
          <?php if (isset($pendingPoCount) && $pendingPoCount > 0): ?>
          <span class="badge"><?php echo $pendingPoCount; ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li>
        <a href="reports.php" class="<?php echo $activePage === 'reports' ? 'active' : ''; ?>">
          <!-- bar-chart-2 -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"/>
            <line x1="12" y1="20" x2="12" y2="4"/>
            <line x1="6"  y1="20" x2="6"  y2="14"/>
          </svg>
          Reports
        </a>
      </li>
      <li>
        <a href="settings.php" class="<?php echo $activePage === 'settings' ? 'active' : ''; ?>">
          <!-- settings -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
          </svg>
          Settings
        </a>
      </li>
    </ul>
    <?php endif; ?>

    <div class="sidebar-section-label">Account</div>
    <ul class="sidebar-nav">
      <li>
        <a href="profile.php" class="<?php echo $activePage === 'profile' ? 'active' : ''; ?>">
          <!-- user -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Profile
        </a>
      </li>
    </ul>

    <?php else: ?>
    <!-- Supplier navigation -->
    <div class="sidebar-section-label">Supplier</div>
    <ul class="sidebar-nav">
      <li>
        <a href="supplier.php" class="<?php echo $activePage === 'supplier' ? 'active' : ''; ?>">
          <!-- store -->
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/>
            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
            <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/>
            <path d="M2 7h20"/>
            <path d="M22 7v3a2 2 0 0 1-2 2a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>
          </svg>
          My Products
        </a>
      </li>
    </ul>

    <div class="sidebar-section-label">Account</div>
    <ul class="sidebar-nav">
      <li>
        <a href="profile.php" class="<?php echo $activePage === 'profile' ? 'active' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Profile
        </a>
      </li>
    </ul>
    <?php endif; ?>

  </div><!-- /sidebar-section -->

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?php echo htmlspecialchars($userInitials); ?></div>
      <div class="sidebar-user-info">
        <span class="sidebar-user-name"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></span>
        <span class="sidebar-user-role"><?php echo ucfirst($user['role']); ?></span>
      </div>
      <a href="logout.php" class="sidebar-logout" title="Logout">
        <!-- log-out -->
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
      </a>
    </div>
  </div>
</aside>
