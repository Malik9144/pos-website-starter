<?php
require_once __DIR__ . '/../config/config.php'; 
require_once __DIR__ . '/../src/lib/db.php'; 
require_once __DIR__ . '/../src/lib/auth.php'; 
require_once __DIR__ . '/../src/lib/permissions.php'; 
require_once __DIR__ . '/../src/lib/utils.php'; 
require_once __DIR__ . '/../src/lib/csrf.php';
require_once __DIR__ . '/../src/nav/sidebar.php'; // SIDEBAR TETAP DIPANGGIL PALING ATAS

auth_required(['admin','superadmin']);
restrict_for_kasir();

$u = auth_user();

// Proses tambah user
if($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])){ 
  csrf_check();
  $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
  db()->prepare('INSERT INTO users(name,email,password_hash,role,branch_id) VALUES(?,?,?,?,?)')
    ->execute([$_POST['name'], $_POST['email'], $pass, $_POST['role'], (int)$_POST['branch_id']]);
  header("Location: manage_users.php?msg=User berhasil ditambah"); 
  exit;
}

// Proses hapus user
if (isset($_POST['delete_id'])) {
  csrf_check();
  // Cegah hapus akun sendiri
  if ($_POST['delete_id'] == $u['id']) {
    header('Location: manage_users.php?err=Anda tidak boleh menghapus akun sendiri');
    exit;
  }
  db()->prepare('DELETE FROM users WHERE id=?')->execute([$_POST['delete_id']]);
  header('Location: manage_users.php?msg=User berhasil dihapus');
  exit;
}

$users = db()->query('SELECT u.*, b.name branch FROM users u LEFT JOIN branches b ON b.id=u.branch_id ORDER BY u.id DESC')->fetchAll();
$branches = db()->query('SELECT * FROM branches')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manajemen User - POS</title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; }
    .container { margin-left:240px; padding:30px; }
    .card { background:#0b6e4f; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 5px 12px rgba(0,0,0,0.3); }
    h2 { color:#ffd700; margin-top:0; }
    label { display:block; margin-top:10px; margin-bottom:5px; }
    input, select { width:100%; padding:8px; border-radius:6px; border:none; }
    button { margin-top:10px; padding:10px 15px; background:#ffd700; border:none; border-radius:6px; font-weight:bold; cursor:pointer; color:#064420; }
    button:hover { background:#e6c200; }
    table { width:100%; border-collapse:collapse; margin-top:15px; background:#fff; color:#000; border-radius:8px; overflow:hidden; }
    th, td { padding:10px; border-bottom:1px solid #ddd; }
    th { background:#064420; color:#ffd700; text-align:left; }
    tr:hover { background:#f2f2f2; }
    .badge { background:#ffd700; color:#064420; padding:3px 6px; border-radius:6px; font-size:12px; font-weight:bold; }
    .alert { padding:10px; border-radius:6px; margin-bottom:15px; }
    .alert.success { background:#27ae60; color:#fff; }
    .alert.error { background:#e74c3c; color:#fff; }
    .btn-danger { background:#e74c3c; color:#fff; font-weight:bold; border:none; border-radius:6px; padding:7px 14px; cursor:pointer; font-size:15px; }
    .btn-danger:hover { background:#c0392b; }
    @media (max-width: 800px) {
      .container {margin-left:70px; padding:18px;}
      .card { padding:11px;}
      table {font-size:13px;}
    }
  </style>
</head>
<body>
<div class="container">
  <h2><i class="fa fa-users"></i> Manajemen User</h2>
  <p>Kelola akun user yang bisa mengakses aplikasi POS.</p>

  <?php if (isset($_GET['msg'])): ?>
    <div class="alert success"><i class="fa fa-check-circle"></i> <?= e($_GET['msg']) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['err'])): ?>
    <div class="alert error"><i class="fa fa-times-circle"></i> <?= e($_GET['err']) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2><i class="fa fa-user-plus"></i> Tambah User</h2>
    <form method="post">
      <?php csrf_field(); ?>
      <label>Nama</label>
      <input name="name" required>
      <label>Email</label>
      <input name="email" type="email" required>
      <label>Password</label>
      <input name="password" type="password" required>
      <label>Role</label>
      <select name="role">
        <option>kasir</option>
        <option>spv</option>
        <option>admin</option>
        <option>superadmin</option>
        <option>spv_warehouse</option>
      </select>
      <label>Cabang</label>
      <select name="branch_id">
        <?php foreach($branches as $b): ?>
          <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit"><i class="fa fa-save"></i> Simpan</button>
    </form>
  </div>

  <div class="card">
    <h2><i class="fa fa-list"></i> Daftar User</h2>
    <table>
      <tr>
        <th>Nama</th>
        <th>Email</th>
        <th>Role</th>
        <th>Cabang</th>
        <th>Aksi</th>
      </tr>
      <?php foreach($users as $user): ?>
        <tr>
          <td><?= e($user['name']) ?></td>
          <td><?= e($user['email']) ?></td>
          <td><span class="badge"><?= e($user['role']) ?></span></td>
          <td><?= e($user['branch']) ?></td>
          <td>
            <?php if($user['id'] != $u['id']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Yakin hapus akun ini?');">
                <?php csrf_field(); ?>
                <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                <button type="submit" class="btn-danger"><i class="fa fa-trash"></i> Hapus</button>
              </form>
            <?php else: ?>
              <span style="color:#aaa;font-size:13px;">(Akun Anda)</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
