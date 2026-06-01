<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['admin','superadmin','spv','spv_warehouse']);

// tambah stok gudang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sku'], $_POST['name'], $_POST['qty']) && !isset($_POST['id'])) {
    csrf_check();
    $stmt = db()->prepare('INSERT INTO warehouse_stock(branch_id,sku,name,qty,unit) VALUES(?,?,?,?,?)');
    $stmt->execute([
        $_POST['branch_id'],
        $_POST['sku'],
        $_POST['name'],
        (int)$_POST['qty'],
        $_POST['unit']
    ]);
    header('Location: manage_stock.php?msg=Stok berhasil ditambahkan');
    exit;
}

// ambil semua stok gudang
$rows = db()->query('SELECT w.*, b.name as branch_name 
                     FROM warehouse_stock w 
                     JOIN branches b ON b.id=w.branch_id 
                     ORDER BY w.id DESC')->fetchAll();
$branches = db()->query('SELECT * FROM branches ORDER BY id')->fetchAll();
$u = auth_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manajemen Stok Gudang - POS</title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body {margin:0;font-family:'Segoe UI',sans-serif;background:#064420;color:#fff;}
    .container {margin-left:240px; padding:30px;}
    .card {background:#0b6e4f;border-radius:12px;padding:20px;box-shadow:0 5px 12px rgba(0,0,0,0.3);margin-bottom:20px;}
    h2 {color:#ffd700;margin-top:0;}
    label {display:block;margin-top:10px;margin-bottom:5px;}
    input, select {width:100%;padding:8px;border-radius:6px;border:none;}
    button {margin-top:10px;padding:10px 15px;background:#ffd700;border:none;border-radius:6px;font-weight:bold;cursor:pointer;color:#064420;}
    button:hover {background:#e6c200;}
    table {width:100%;border-collapse:collapse;margin-top:15px;background:#fff;color:#000;}
    th,td {padding:10px;border-bottom:1px solid #ddd;}
    th {background:#064420;color:#ffd700;text-align:left;}
    tr:hover {background:#f9f9f9;}
    .alert {padding:10px;border-radius:6px;margin-bottom:15px;}
    .alert.success {background:#27ae60;color:#fff;}
    .alert.error {background:#e74c3c;color:#fff;}
    .btn-danger {background:#e74c3c;color:#fff;padding:6px 12px;border:none;border-radius:6px;cursor:pointer;}
    .btn-danger:hover {background:#c0392b;}
    .btn-edit {background:#3498db;color:#fff;padding:6px 12px;border:none;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-block;}
    .btn-edit:hover {background:#2980b9;}
    @media (max-width: 800px) {
      .container {margin-left:70px; padding:18px;}
      .card { padding:11px;}
      table {font-size:13px;}
    }
  </style>
</head>
<body>
<div class="container">
  <?php if (isset($_GET['msg'])): ?>
    <div class="alert success"><?= e($_GET['msg']) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['err'])): ?>
    <div class="alert error"><?= e($_GET['err']) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2><i class="fa fa-plus"></i> Tambah Stok Gudang</h2>
    <form method="post">
      <?php csrf_field(); ?>
      <label>Cabang</label>
      <select name="branch_id" required>
        <?php foreach($branches as $b): ?>
          <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Kode SKU</label>
      <input name="sku" required>
      <label>Nama Barang</label>
      <input name="name" required>
      <label>Jumlah</label>
      <input type="number" name="qty" required min="0">
      <label>Satuan</label>
      <input name="unit" value="pcs">
      <button type="submit"><i class="fa fa-save"></i> Simpan</button>
    </form>
  </div>

  <div class="card">
    <h2><i class="fa fa-list"></i> Daftar Stok Gudang</h2>
    <table>
      <tr><th>ID</th><th>Cabang</th><th>SKU</th><th>Nama Barang</th><th>Jumlah</th><th>Satuan</th><th>Update</th><th>Aksi</th></tr>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= e($r['branch_name']) ?></td>
          <td><?= e($r['sku']) ?></td>
          <td><?= e($r['name']) ?></td>
          <td><?= $r['qty'] ?></td>
          <td><?= e($r['unit']) ?></td>
          <td><?= $r['updated_at'] ?></td>
          <td>
            <?php if (in_array($u['role'], ['admin','superadmin','spv','spv_warehouse'])): ?>
              <a href="edit_stock.php?id=<?= $r['id'] ?>" class="btn-edit"><i class="fa fa-edit"></i> Edit</a>
            <?php endif; ?>
            <form method="post" action="delete_stock.php" id="form-del-<?= $r['id'] ?>" style="display:inline;">
              <?php csrf_field(); ?>
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="button" class="btn-danger"
                onclick="confirmDelete(<?= $r['id'] ?>,'<?= e($r['name']) ?>')">
                <i class="fa fa-trash"></i> Hapus
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
<script>
function confirmDelete(id, name){
  Swal.fire({
    title: 'Hapus Stok?',
    text: "Barang: " + name,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('form-del-'+id).submit();
    }
  });
}
</script>
</body>
</html>
