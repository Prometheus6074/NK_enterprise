<?php
// Debug login script
include 'db/connect.php';

echo "<h2>Debug Login Process</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "Email: " . $_POST['email'] . "<br>";
    echo "Password: " . $_POST['password'] . "<br>";
    
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    echo "<h3>Database Query:</h3>";
    $sql = "SELECT id, firstname, lastname, email, password, role FROM users WHERE email = '$email' LIMIT 1";
    echo "SQL: $sql<br>";
    
    $query = mysqli_query($connect2db, $sql);
    
    if (!$query) {
        echo "❌ Query failed: " . mysqli_error($connect2db) . "<br>";
        exit;
    }
    
    if (mysqli_num_rows($query) === 0) {
        echo "❌ User not found<br>";
    } else {
        echo "✅ User found<br>";
        $row = mysqli_fetch_assoc($query);
        
        echo "User Data:<br>";
        echo "ID: " . $row['id'] . "<br>";
        echo "Name: " . $row['firstname'] . ' ' . $row['lastname'] . "<br>";
        echo "Email: " . $row['email'] . "<br>";
        echo "Role: " . $row['role'] . "<br>";
        echo "Password Hash: " . $row['password'] . "<br>";
        
        echo "<h3>Password Verification:</h3>";
        echo "Plain Password: '$password'<br>";
        
        if (password_verify($password, $row['password'])) {
            echo "✅ Password verification SUCCESS<br>";
            echo "Setting session and redirecting...<br>";
            
            $_SESSION['user'] = [
                'id' => $row['id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'role' => $row['role']
            ];
            
            echo "Session set. <a href='dashboard.php'>Go to Dashboard</a><br>";
            
        } else {
            echo "❌ Password verification FAILED<br>";
            
            // Test with different variations
            $testPasswords = ['password', 'Password', 'PASSWORD', 'admin'];
            foreach ($testPasswords as $testPwd) {
                if (password_verify($testPwd, $row['password'])) {
                    echo "✅ Actual password is: '$testPwd'<br>";
                    break;
                }
            }
        }
    }
} else {
    ?>
    <form method="POST">
        <h2>Debug Login Form</h2>
        <input name="email" type="email" placeholder="Email" required><br><br>
        <input name="password" type="password" placeholder="Password" required><br><br>
        <button type="submit">Test Login</button>
    </form>
    <?php
}
?>
