<?php
require_once __DIR__ . '/../src/lib/auth.php';
auth_required(); // pastikan hanya user login yg bisa akses
$u = auth_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard POS</title>
  <style>
    body { font-family: Arial, sans-serif; background:#064420; color:#fff; padding:20px; }
    .box { background:#0b6e4f; padding:20px; border-radius:10px; max-width:600px; margin:auto; }
    h1 { color:#ffd700; }
    a { color:#ffd700; text-decoration:none; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Selamat Datang, <?= htmlspecialchars($u['name']) ?>!</h1>
    <p>Email: <?= htmlspecialchars($u['email']) ?></p>
    <p>Role: <?= htmlspecialchars($u['role']) ?></p>
    <p>Cabang: <?= htmlspecialchars($u['branch_id']) ?></p>

    <hr>
    <p><a href="logout.php">🔒 Logout</a></p>
  </div>
</body>
</html>
