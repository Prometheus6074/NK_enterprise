<?php

// Supplier-exclusive guard — include this at the top of supplier pages.
// Redirects to login if not authenticated, or to dashboard if not a supplier.

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['user']['role'] !== 'supplier') {
    header("Location: dashboard.php");
    exit;
}
