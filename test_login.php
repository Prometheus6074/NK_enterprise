<?php
// Test script to verify admin login
include 'db/connect.php';

echo "<h2>Login Test for nk_ent Database</h2>";

// Test 1: Database connection
echo "<h3>1. Database Connection</h3>";
if (isset($connect2db) && $connect2db) {
    echo "✅ Database connection successful to nk_ent<br>";
} else {
    echo "❌ Database connection failed<br>";
    echo "Error: " . mysqli_connect_error() . "<br>";
    exit;
}

// Test 2: Check if users table exists
echo "<h3>2. Check Users Table</h3>";
$tableCheck = "SHOW TABLES LIKE 'users'";
$tableQuery = mysqli_query($connect2db, $tableCheck);

if (!$tableQuery) {
    echo "❌ Table check failed: " . mysqli_error($connect2db) . "<br>";
    exit;
}

if (mysqli_num_rows($tableQuery) === 0) {
    echo "❌ Users table not found in nk_ent database<br>";
    echo "Please import the nk_ent_users.sql file first<br>";
    echo "<a href='stuff/nk_ent_users.sql'>Download SQL file</a><br>";
    exit;
} else {
    echo "✅ Users table found<br>";
}

// Test 3: Check if admin user exists
echo "<h3>3. Check Admin User</h3>";
$sql = "SELECT id, firstname, lastname, email, password, role FROM users WHERE email = 'admin@hardware.com'";
$query = mysqli_query($connect2db, $sql);

if (!$query) {
    echo "❌ Query failed: " . mysqli_error($connect2db) . "<br>";
    exit;
}

if (mysqli_num_rows($query) === 0) {
    echo "❌ Admin user not found in database<br>";
    exit;
}

$admin = mysqli_fetch_assoc($query);
echo "✅ Admin user found:<br>";
echo "ID: " . $admin['id'] . "<br>";
echo "Name: " . $admin['firstname'] . ' ' . $admin['lastname'] . "<br>";
echo "Email: " . $admin['email'] . "<br>";
echo "Role: " . $admin['role'] . "<br>";
echo "Password Hash: " . $admin['password'] . "<br>";

// Test 4: Verify password
echo "<h3>4. Password Verification</h3>";
$plainPassword = 'password';
if (password_verify($plainPassword, $admin['password'])) {
    echo "✅ Password verification successful<br>";
} else {
    echo "❌ Password verification failed<br>";
    echo "Plain password: '$plainPassword'<br>";
    echo "Hashed password: " . $admin['password'] . "<br>";
    
    // Test with different common passwords
    $testPasswords = ['admin', 'password', '123456', ''];
    echo "<h4>Testing other common passwords:</h4>";
    foreach ($testPasswords as $testPwd) {
        if (password_verify($testPwd, $admin['password'])) {
            echo "✅ Password is: '$testPwd'<br>";
            break;
        } else {
            echo "❌ '$testPwd' is not the password<br>";
        }
    }
    
    // Update password if verification fails
    echo "<h4>Updating password...</h4>";
    $newHash = password_hash('password', PASSWORD_DEFAULT);
    $updateSql = "UPDATE users SET password = '$newHash' WHERE email = 'admin@hardware.com'";
    if (mysqli_query($connect2db, $updateSql)) {
        echo "✅ Admin password updated in database<br>";
        echo "You can now login with: admin@hardware.com / password<br>";
    } else {
        echo "❌ Failed to update password: " . mysqli_error($connect2db) . "<br>";
    }
}

echo "<h3>Test Complete</h3>";
echo "<a href='pages/login.php'>Go to Login Page</a><br>";
echo "<a href='stuff/nk_ent_users.sql'>Download SQL file if needed</a>";
?>
