<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $role     = $_POST['role'];
    $branch   = (int) $_POST['branch_id'];

    if ($name && $email && $password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $st = db()->prepare("INSERT INTO users (name, email, password_hash, role, branch_id) VALUES (?, ?, ?, ?, ?)");
        try {
            $st->execute([$name, $email, $hash, $role, $branch]);
            $msg = "✅ User berhasil dibuat. Silakan login.";
        } catch (PDOException $e) {
            $msg = "❌ Gagal membuat user: " . $e->getMessage();
        }
    } else {
        $msg = "Semua field wajib diisi!";
    }
}

// ambil daftar cabang untuk pilihan
$branches = db()->query("SELECT * FROM branches ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Register User POS</title>
  <link rel="stylesheet" href="assets/css/theme.css">
  <style>
    body { background:#064420; color:#fff; font-family:Arial, sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; }
    .box { background:#0b6e4f; padding:24px; border-radius:12px; width:360px; }
    h2 { text-align:center; margin-bottom:20px; color:#ffd700; }
    input, select { width:100%; padding:10px; margin-bottom:12px; border:none; border-radius:6px; }
    button { width:100%; padding:10px; background:#ffd700; border:none; border-radius:6px; font-weight:bold; color:#064420; cursor:pointer; }
    .msg { margin-bottom:10px; text-align:center; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Register User</h2>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <input type="text" name="name" placeholder="Nama" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>

      <select name="role" required>
        <option value="kasir">Kasir</option>
        <option value="spv">SPV</option>
        <option value="admin">Admin</option>
        <option value="superadmin">Super Admin</option>
        <option value="spv_warehouse">SPV Warehouse</option>
      </select>

      <select name="branch_id" required>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit">Buat User</button>
    </form>
    <div style="text-align:center;margin-top:10px">
      <a href="login.php" style="color:#ffd700">Kembali ke Login</a>
    </div>
  </div>
</body>
</html>
