<?php
include '../functions/auth_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    register($_POST, $connect2db, $result, $resultClass);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../css/style.css" />
    <title>Register</title>
</head>

<body>

    <form class="login-card" method="POST">
        <h1>Create Account</h1>

        <input type="text" name="firstname" placeholder="First Name" required />
        <input type="text" name="lastname" placeholder="Last Name" required />
        <input type="email" name="email" placeholder="Email" required />
        <input type="password" name="password" placeholder="Password" required />
        <input type="password" name="confirm_password" placeholder="Confirm password" required />

        <button type="submit">Register</button>
        <!-- DIALOG BOX -->
        <?php include '../components/authDialogBox.php'?>
        <div class="links single">
            <a href="login.php">Already have an account?</a>
        </div>
    </form>
</body>

</html>