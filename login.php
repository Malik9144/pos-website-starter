<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung redirect ke dashboard
if (auth_user()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (auth_login($email, $password)) {
        // SIMPAN WAKTU LOGIN USER ke session
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
    $msg = "Email atau password salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login POS</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body {font-family: Arial,sans-serif; background: #064420; color: #fff; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0}
    .login-box {background: #0b6e4f; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.5); width: 320px}
    .login-box h2 {margin-bottom: 20px; text-align: center; color: #ffd700}
    .login-box input {width: 100%; padding: 10px; margin-bottom: 15px; border: none; border-radius: 6px}
    .login-box button {width: 100%; padding: 10px; background: #ffd700; color: #064420; border: none; border-radius: 6px; font-weight: bold; cursor: pointer}
    .msg {color: #ffcccc; text-align: center; margin-bottom: 10px}
  </style>
</head>
<body>
  <form class="login-box" method="post">
    <h2>Login POS</h2>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <input type="email" name="email" placeholder="Email" required autofocus>
    <input type="password" name="password" placeholder="Password" required>
    <button type="submit">Masuk</button>
  </form>
</body>
</html>
