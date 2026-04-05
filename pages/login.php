<?php
session_start();
include '../functions/auth_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    login($_POST, $connect2db, $result, $resultClass);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../css/style.css" />
    <title>Login</title>
</head>

<body>
    <form class="login-card" method="POST">
        <h1>Login</h1>

        <input name="email" type="email" placeholder="Email" required />
        <input name="password" type="password" placeholder="Password" required />

        <button type="submit">Sign in</button>
        <!-- DIALOG BOX -->
        <?php include '../components/authDialogBox.php'?>

        <div class="links">
            <a href="forgot-password.php">Forgot password?</a>
            <a href="register.php">Create an account</a>
        </div>
    </form>
</body>

</html>