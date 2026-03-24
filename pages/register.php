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
  <title>Create Account — NK Ent</title>
</head>
<body class="auth-body">

  <div class="auth-wrapper">
    <div class="auth-card">

      <div class="auth-logo">
        <div class="auth-logo-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16.5 9.4 7.55 4.24"/>
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
            <line x1="12" y1="22.08" x2="12" y2="12"/>
          </svg>
        </div>
        <span class="auth-logo-text">NK Ent</span>
      </div>

      <h1>Create account</h1>
      <p class="auth-subtitle">Fill in your details to get started</p>

      <?php if (isset($result) && $result): ?>
      <div class="message <?php echo htmlspecialchars($resultClass); ?>">
        <?php echo htmlspecialchars($result); ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="auth-field">
          <label for="firstname">First name</label>
          <input id="firstname" type="text" name="firstname" placeholder="Juan" required />
        </div>
        <div class="auth-field">
          <label for="lastname">Last name</label>
          <input id="lastname" type="text" name="lastname" placeholder="Dela Cruz" required />
        </div>
        <div class="auth-field">
          <label for="email">Email address</label>
          <input id="email" type="email" name="email" placeholder="you@example.com" required />
        </div>
        <div class="auth-field">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" placeholder="••••••••" required />
        </div>
        <div class="auth-field">
          <label for="confirm_password">Confirm password</label>
          <input id="confirm_password" type="password" name="confirm_password" placeholder="••••••••" required />
        </div>
        <button type="submit" class="auth-submit">Create account</button>
      </form>

      <div class="auth-links single">
        <a href="login.php">Already have an account? Sign in</a>
      </div>

    </div>
  </div>

</body>
</html>
