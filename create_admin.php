<?php
// Create fresh admin user with known password
include 'db/connect.php';

echo "<h2>Create Fresh Admin User</h2>";

// Delete existing admin user if exists
$deleteSql = "DELETE FROM users WHERE email = 'admin@hardware.com'";
if (mysqli_query($connect2db, $deleteSql)) {
    echo "✅ Deleted existing admin user<br>";
}

// Create new admin user with fresh password hash
$plainPassword = 'password';
$newHash = password_hash($plainPassword, PASSWORD_DEFAULT);

$insertSql = "INSERT INTO users (firstname, lastname, email, password, role) VALUES 
             ('Admin', 'User', 'admin@hardware.com', '$newHash', 'admin')";

if (mysqli_query($connect2db, $insertSql)) {
    echo "✅ Created new admin user<br>";
    echo "Email: admin@hardware.com<br>";
    echo "Password: password<br>";
    echo "Password Hash: $newHash<br>";
} else {
    echo "❌ Failed to create admin user: " . mysqli_error($connect2db) . "<br>";
}

// Test the password immediately
echo "<h3>Testing Password Verification</h3>";
$testSql = "SELECT password FROM users WHERE email = 'admin@hardware.com'";
$testQuery = mysqli_query($connect2db, $testSql);
$user = mysqli_fetch_assoc($testQuery);

if (password_verify($plainPassword, $user['password'])) {
    echo "✅ Password verification PASSED<br>";
    echo "You can now login at: <a href='pages/login.php'>Login Page</a><br>";
} else {
    echo "❌ Password verification FAILED<br>";
    echo "This should not happen with a fresh hash<br>";
}

// Show all users in database
echo "<h3>All Users in Database</h3>";
$allUsersSql = "SELECT id, firstname, lastname, email, role FROM users";
$allUsersQuery = mysqli_query($connect2db, $allUsersSql);

while ($user = mysqli_fetch_assoc($allUsersQuery)) {
    echo "ID: {$user['id']} - {$user['firstname']} {$user['lastname']} - {$user['email']} - {$user['role']}<br>";
}
?>
