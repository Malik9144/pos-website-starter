<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';

// ✅ Izinkan spv, spv_warehouse, admin, superadmin
auth_required(['admin','superadmin','spv_warehouse','spv']);

// cek parameter ID stok
if (!isset($_GET['id'])) {
    header('Location: manage_stock.php?err=ID stok tidak ditemukan');
    exit;
}

$id = (int) $_GET['id'];

// ambil data stok
$stmt = db()->prepare("SELECT * FROM warehouse_stock WHERE id=?");
$stmt->execute([$id]);
$stock = $stmt->fetch();

if (!$stock) {
    header('Location: manage_stock.php?err=Stok tidak ditemukan');
    exit;
}

// ambil daftar cabang
$branches = db()->query("SELECT * FROM branches ORDER BY id")->fetchAll();

// jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $stmt = db()->prepare("UPDATE warehouse_stock 
                           SET branch_id=?, sku=?, name=?, qty=?, unit=?, updated_at=NOW()
                           WHERE id=?");
    $stmt->execute([
        $_POST['branch_id'],
        $_POST['sku'],
        $_POST['name'],
        (int)$_POST['qty'],
        $_POST['unit'],
        $id
    ]);

    header("Location: manage_stock.php?msg=Stok '".urlencode($_POST['name'])."' berhasil diperbarui");
    exit;
}

$u = auth_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edit Stok Gudang - POS</title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body {margin:0;font-family:'Segoe UI',sans-serif;background:#064420;color:#fff;}
    header {background:#0b6e4f;padding:15px 30px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 3px 6px rgba(0,0,0,.3);}
    header h1 {color:#ffd700;margin:0;font-size:22px;}
    nav a {color:#ffd700;margin-left:20px;text-decoration:none;font-weight:bold;}
    nav a:hover {color:#fff;}
    .container {padding:30px;}
    .card {background:#0b6e4f;border-radius:12px;padding:20px;box-shadow:0 5px 12px rgba(0,0,0,0.3);margin-bottom:20px;}
    h2 {color:#ffd700;margin-top:0;}
    label {display:block;margin-top:10px;margin-bottom:5px;}
    input, select {width:100%;padding:8px;border-radius:6px;border:none;}
    button {margin-top:10px;padding:10px 15px;background:#ffd700;border:none;border-radius:6px;font-weight:bold;cursor:pointer;color:#064420;}
    button:hover {background:#e6c200;}
  </style>
</head>
<body>
<header>
  <h1><i class="fa-solid fa-warehouse"></i> Dashboard POS</h1>
  <nav>
    <a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="manage_products.php"><i class="fa fa-box"></i> Produk</a>
    <a href="pos.php"><i class="fa fa-shopping-cart"></i> Transaksi</a>
    <a href="reports.php"><i class="fa fa-chart-line"></i> Laporan</a>
    <a href="manage_branches.php"><i class="fa fa-store"></i> Cabang</a>
    <a href="manage_users.php"><i class="fa fa-users"></i> User</a>
    <a href="manage_stock.php"><i class="fa fa-warehouse"></i> Stock Gudang</a>
    <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h2><i class="fa fa-edit"></i> Edit Stok Gudang</h2>
    <form method="post">
      <?php csrf_field(); ?>
      <label>Cabang</label>
      <select name="branch_id" required>
        <?php foreach($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id']==$stock['branch_id']?'selected':'' ?>>
            <?= e($b['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label>Kode SKU</label>
      <input name="sku" required value="<?= e($stock['sku']) ?>">
      <label>Nama Barang</label>
      <input name="name" required value="<?= e($stock['name']) ?>">
      <label>Jumlah</label>
      <input type="number" name="qty" required min="0" value="<?= $stock['qty'] ?>">
      <label>Satuan</label>
      <input name="unit" value="<?= e($stock['unit']) ?>">
      <button type="submit"><i class="fa fa-save"></i> Simpan Perubahan</button>
    </form>
  </div>
</div>
</body>
</html>
