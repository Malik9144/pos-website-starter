<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__ . '/../src/nav/sidebar_functions.php';

$u = get_auth_user();

// Helper function untuk get nama cabang
function getBranchName($branch_id) {
    $stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
    $stmt->execute([$branch_id]);
    $result = $stmt->fetch();
    return $result ? $result['name'] : 'Unknown Branch';
}

// 1. Handle POST (update stock atau reset opname)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (isset($_POST['action']) && $_POST['action'] === 'reset_opname') {
        // Reset stock opname
        $branch_id = (int)($_POST['branch_id'] ?? 0);

        if (!$branch_id) {
            header('Location: manage_stockbranch.php?err=Cabang tidak valid');
            exit;
        }

        try {
            db()->beginTransaction();

            // Contoh implementasi reset opname:
            // Hapus semua stock_movements dengan tipe 'adjustment' di cabang ini (opsional)
            $stmt = db()->prepare("DELETE FROM stock_movements WHERE branch_id = ? AND movement_type = 'adjustment'");
            $stmt->execute([$branch_id]);

            // Catat log, rekalkulasi fisik stok bisa ditambahkan di sini jika ada tabel khusus opname

            db()->commit();

            header("Location: manage_stockbranch.php?msg=Reset stok opname berhasil untuk cabang ".htmlspecialchars(getBranchName($branch_id)));
            exit;
        } catch (Exception $e) {
            db()->rollback();
            header("Location: manage_stockbranch.php?err=Gagal reset stok opname: " . $e->getMessage());
            exit;
        }
    }

    // Update atau insert stok
    if (isset($_POST['product_id'], $_POST['branch_id'], $_POST['quantity'], $_POST['type'])) {
        $pid = (int)$_POST['product_id'];
        $bid = (int)$_POST['branch_id'];
        $qty = (int)$_POST['quantity'];
        $type = $_POST['type'];
        $note = trim($_POST['note'] ?? '');

        if ($qty <= 0) {
            header('Location: manage_stockbranch.php?err=Jumlah harus > 0');
            exit;
        }

        // Jika jenis keluar maka qty negatif
        if ($type === 'out') {
            $qty = -$qty;
        } elseif ($type === 'adjustment') {
            // Untuk adjustment, qty bisa positif atau negatif (tandai jika perlu validasi khusus)
            // Contoh validasi tambahan: tidak boleh stok jadi negatif
            $stmt = db()->prepare("SELECT quantity FROM stock_branch WHERE product_id=? AND branch_id=?");
            $stmt->execute([$pid, $bid]);
            $current_stock = (int)$stmt->fetchColumn();
            if ($current_stock + $qty < 0) {
                header('Location: manage_stockbranch.php?err=Penyesuaian stok membuat stok negatif!');
                exit;
            }
        }

        // SPV hanya boleh akses cabangnya sendiri
        if ($u['role'] === 'spv') {
            if (empty($u['branch_id'])) {
                header('Location: manage_stockbranch.php?err=Akun SPV belum terdaftar cabang.');
                exit;
            }
            if ($bid !== (int)$u['branch_id']) {
                header('Location: manage_stockbranch.php?err=Anda tidak diberi akses cabang ini');
                exit;
            }
        }

        try {
            db()->beginTransaction();

            // Update atau insert stok
            $stmt = db()->prepare("
                INSERT INTO stock_branch(product_id, branch_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$pid, $bid, $qty]);

            // Insert riwayat pergerakan stok
            $stmt = db()->prepare("
                INSERT INTO stock_movements
                (product_id, branch_id, movement_type, quantity, note, user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$pid, $bid, $type, abs($qty), $note, $u['id']]);

            db()->commit();

            header('Location: manage_stockbranch.php?msg=Stok berhasil diperbarui');
            exit;
        } catch (Exception $e) {
            db()->rollback();
            header('Location: manage_stockbranch.php?err=Error: ' . $e->getMessage());
            exit;
        }
    }
}

// 2. Prepare filter pencarian dan cabang
$search_q = trim($_GET['q'] ?? '');
$filter_branch = $_GET['branch_id'] ?? '';

// Setup cabang berdasar role user
if ($u['role'] === 'spv') {
    if (empty($u['branch_id'])) {
        header('Location: dashboard.php?err=Akun SPV belum terdaftar cabang.');
        exit;
    }
    $current_branch_id = (int)$u['branch_id'];
    $current_branch_name = getBranchName($current_branch_id);
    $branches = [ ['id' => $current_branch_id, 'name' => $current_branch_name] ];
    $branches_all = $branches;
    $filter_branch = $current_branch_id;
} else {
    $branches_all = db()->query("SELECT id,name FROM branches ORDER BY name")->fetchAll();

    if ($filter_branch) {
        $branches_stmt = db()->prepare("SELECT id,name FROM branches WHERE id=? ORDER BY name");
        $branches_stmt->execute([(int)$filter_branch]);
        $branches = $branches_stmt->fetchAll();
        $current_branch_id = (int)$filter_branch;
    } else {
        $branches = $branches_all;
        $current_branch_id = null;
    }
}

// 3. Load produk sesuai filter
$product_sql = "SELECT id,name,sku,branch_id FROM products WHERE active=1";
$params = [];

if ($current_branch_id) {
    $product_sql .= " AND branch_id = ?";
    $params[] = $current_branch_id;
}

if ($search_q !== '') {
    $product_sql .= " AND (LOWER(name) LIKE ? OR LOWER(sku) LIKE ?)";
    $params[] = '%' . strtolower($search_q) . '%';
    $params[] = '%' . strtolower($search_q) . '%';
}

$product_sql .= " ORDER BY name";
$stmt = db()->prepare($product_sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// 4. Load stok cabang sesuai filter
$stock_sql = "
    SELECT sb.id, p.name AS product, p.sku, b.name AS branch, sb.quantity
    FROM stock_branch sb
    JOIN products p ON sb.product_id = p.id
    JOIN branches b ON sb.branch_id = b.id
";
$stock_params = [];

if ($u['role'] === 'spv') {
    $stock_sql .= " WHERE sb.branch_id = ?";
    $stock_params[] = (int)$u['branch_id'];
} elseif ($filter_branch) {
    $stock_sql .= " WHERE sb.branch_id = ?";
    $stock_params[] = (int)$filter_branch;
}

$stock_sql .= " ORDER BY p.name, b.name";
$stmt = db()->prepare($stock_sql);
$stmt->execute($stock_params);
$stocks = $stmt->fetchAll();

// 5. Load histori pergerakan stok
$hist_sql = "
    SELECT m.*, p.name AS product, p.sku, b.name AS branch, u.name AS user
    FROM stock_movements m
    JOIN products p ON m.product_id = p.id
    JOIN branches b ON m.branch_id = b.id
    LEFT JOIN users u ON m.user_id = u.id
";
$hist_params = [];

if ($u['role'] === 'spv') {
    $hist_sql .= " WHERE m.branch_id = ?";
    $hist_params[] = (int)$u['branch_id'];
} elseif ($filter_branch) {
    $hist_sql .= " WHERE m.branch_id = ?";
    $hist_params[] = (int)$filter_branch;
}

$hist_sql .= " ORDER BY m.created_at DESC LIMIT 20";
$stmt = db()->prepare($hist_sql);
$stmt->execute($hist_params);
$history = $stmt->fetchAll();

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Stok Cabang</title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<!-- DataTables CSS -->
<link rel="stylesheet" href="/pos-web-starter/assets/css/jquery.dataTables.min.css">
<style>
body {margin:0;font-family:'Segoe UI',sans-serif;background:#064420;color:#fff;}
.container {margin-left:240px;padding:30px;}
.card {background:#0b6e4f;border-radius:12px;padding:20px;box-shadow:0 5px 12px rgba(0,0,0,0.3);margin-bottom:20px;}
h2 {color:#ffd700;margin-top:0;}
.branch-info {background:#085c3a;padding:10px 15px;border-radius:8px;margin-bottom:15px;text-align:center;}
.branch-info strong {color:#ffd700;}
label {display:block;margin-top:10px;margin-bottom:5px;color:#ffd700;font-weight:bold;}
select,input,textarea {width:100%;padding:8px;border-radius:6px;border:none;box-sizing:border-box;}
button {margin-top:10px;padding:10px 15px;background:#ffd700;border:none;border-radius:6px;font-weight:bold;cursor:pointer;color:#064420;}
button:hover {background:#e6c200;}
.filter-form {display:grid;grid-template-columns:2fr 1fr auto;gap:15px;align-items:end;margin-bottom:20px;}
table {width:100%;border-collapse:collapse;margin-top:15px;background:#fff;color:#000;border-radius:8px;overflow:hidden;}
th,td {padding:10px;border-bottom:1px solid #ddd;}
th {background:#064420;color:#ffd700;text-align:left;}
tr:hover {background:#f9f9f9;}
.alert {padding:10px;border-radius:6px;margin-bottom:15px;}
.alert.success {background:#27ae60;color:#fff;}
.alert.error {background:#e74c3c;color:#fff;}
code {background:#eee;color:#333;padding:2px 4px;border-radius:4px;font-size:90%;}
.no-data {text-align:center;padding:20px;color:#ccc;font-style:italic;}
.btn-reset {background:#f39c12;color:#fff;border:none;padding:10px 15px;border-radius:6px;cursor:pointer;margin-top:15px;}
.btn-reset:hover {background:#e67e22;}
@media (max-width:800px){
  .container{margin-left:70px;padding:18px;}
  .filter-form{grid-template-columns:1fr;gap:10px;}
  table{font-size:13px;}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../src/nav/sidebar.php'; ?>

<div class="container">
  <?php if(isset($_GET['msg'])): ?>
    <div class="alert success"><?= e($_GET['msg']) ?></div>
  <?php endif; ?>
  <?php if(isset($_GET['err'])): ?>
    <div class="alert error"><?= e($_GET['err']) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2><i class="fa fa-box"></i> Update Stok Cabang</h2>

    <?php if($u['role'] === 'spv'): ?>
      <div class="branch-info">
        <i class="fa fa-building"></i> Cabang Anda: <strong><?= e($current_branch_name) ?></strong>
      </div>
    <?php elseif($current_branch_id): ?>
      <div class="branch-info">
        <i class="fa fa-filter"></i> Filter Cabang: <strong><?= e(getBranchName($current_branch_id)) ?></strong>
      </div>
    <?php endif; ?>

    <!-- Form Filter dan Cari -->
    <?php if($u['role'] === 'admin' || $u['role'] === 'superadmin'): ?>
      <form method="get" class="filter-form">
        <div>
          <label>Cari Produk</label>
          <input type="text" name="q" value="<?= e($search_q) ?>" placeholder="Nama atau SKU produk...">
        </div>
        <div>
          <label>Pilih Cabang</label>
          <select name="branch_id">
            <option value="">-- Semua Cabang --</option>
            <?php foreach($branches_all as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $filter_branch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <button type="submit"><i class="fa fa-search"></i> Filter</button>
        </div>
      </form>
    <?php else: ?>
      <form method="get" class="filter-form">
        <div>
          <label>Cari Produk di Cabang Anda</label>
          <input type="text" name="q" value="<?= e($search_q) ?>" placeholder="Nama atau SKU produk...">
        </div>
        <div></div>
        <div>
          <button type="submit"><i class="fa fa-search"></i> Cari</button>
        </div>
      </form>
    <?php endif; ?>

    <!-- Form Update Stok -->
    <form method="post">
      <?php csrf_field(); ?>

      <!-- Hidden input branch_id untuk SPV -->
      <?php if($u['role'] === 'spv'): ?>
        <input type="hidden" name="branch_id" value="<?= $current_branch_id ?>">
      <?php endif; ?>

      <label>Produk <?= $current_branch_id ? '(Cabang: '.e(getBranchName($current_branch_id)).')' : '' ?></label>
      <select name="product_id" required>
        <option value="">-- Pilih produk --</option>
        <?php if(empty($products)): ?>
          <option disabled>Tidak ada produk tersedia</option>
        <?php else: ?>
          <?php foreach($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> <code><?= e($p['sku']) ?></code></option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>

      <label>Cabang</label>
      <select name="branch_id" required <?= $u['role']==='spv'?'readonly':'' ?>>
        <?php if($u['role'] === 'spv'): ?>
          <option value="<?= $current_branch_id ?>" selected><?= e($current_branch_name) ?> (Cabang Anda)</option>
        <?php else: ?>
          <option value="">-- Pilih cabang --</option>
          <?php foreach($branches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>

      <label>Jumlah</label>
      <input type="number" name="quantity" required min="1" placeholder="Masukkan jumlah stok">

      <label>Tipe</label>
      <select name="type" required>
        <option value="in">Masuk (Tambah)</option>
        <option value="out">Keluar (Kurangi)</option>
        <option value="adjustment">Penyesuaian</option>
      </select>

      <label>Keterangan</label>
      <textarea name="note" rows="3" placeholder="Keterangan (opsional)"></textarea>

      <button type="submit" <?= empty($products)?'disabled':'' ?>><i class="fa fa-save"></i> Simpan Perubahan Stok</button>
    </form>

    <!-- Form Reset Stock Opname -->
    <?php if($u['role'] !== 'spv' && $current_branch_id): ?>
    <form method="post" onsubmit="return confirm('Reset stok opname akan menghapus semua adjustment di cabang ini, yakin?');">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="reset_opname">
      <input type="hidden" name="branch_id" value="<?= e($current_branch_id) ?>">
      <button type="submit" class="btn-reset">
        <i class="fa fa-redo"></i> Reset Stok Opname
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Daftar Stok -->
  <div class="card">
    <h2><i class="fa fa-warehouse"></i> Daftar Stok Cabang</h2>
    <?php if(empty($stocks)): ?>
      <div class="no-data"><i class="fa fa-inbox"></i><br>Belum ada data stok untuk cabang ini</div>
    <?php else: ?>
      <table id="stockTable">
        <thead>
        <tr>
          <th>ID</th>
          <th>Produk</th>
          <th>SKU</th>
          <th>Cabang</th>
          <th>Stok</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($stocks as $s): ?>
        <tr>
          <td><?= $s['id'] ?></td>
          <td><?= e($s['product']) ?></td>
          <td><code><?= e($s['sku']) ?></code></td>
          <td><?= e($s['branch']) ?></td>
          <td><strong style="color:<?= $s['quantity']>0?'#27ae60':'#e74c3c' ?>"><?= number_format($s['quantity']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Histori Pergerakan Stok -->
  <div class="card">
    <h2><i class="fa fa-clock"></i> Histori Pergerakan Stok (20 terbaru)</h2>
    <?php if(empty($history)): ?>
      <div class="no-data"><i class="fa fa-history"></i><br>Belum ada histori pergerakan stok</div>
    <?php else: ?>
      <table id="historyTable">
        <thead>
        <tr>
          <th>Waktu</th>
          <th>Produk</th>
          <th>SKU</th>
          <th>Cabang</th>
          <th>Tipe</th>
          <th>Qty</th>
          <th>Keterangan</th>
          <th>User</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($history as $h): ?>
        <tr>
          <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
          <td><?= e($h['product']) ?></td>
          <td><code><?= e($h['sku']) ?></code></td>
          <td><?= e($h['branch']) ?></td>
          <td>
            <?php if($h['movement_type']=='in'): ?>
              <span style="color:#27ae60;"><i class="fa fa-arrow-up"></i> Masuk</span>
            <?php elseif($h['movement_type']=='out'): ?>
              <span style="color:#e74c3c;"><i class="fa fa-arrow-down"></i> Keluar</span>
            <?php else: ?>
              <span style="color:#f39c12;"><i class="fa fa-edit"></i> Penyesuaian</span>
            <?php endif; ?>
          </td>
          <td><strong><?= number_format($h['quantity']) ?></strong></td>
          <td><?= e($h['note']) ?></td>
          <td><?= e($h['user']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- jQuery & DataTables JS -->
<script src="/pos-web-starter/assets/js/jquery-3.7.1.min.js"></script>
<script src="/pos-web-starter/assets/js/jquery.dataTables.min.js"></script>

<script>
// Auto-submit form saat pilih cabang (hanya admin/superadmin)
<?php if($u['role'] === 'admin' || $u['role'] === 'superadmin'): ?>
document.querySelector('select[name="branch_id"]').addEventListener('change', function() {
  if(this.closest('form').querySelector('input[name="q"]')) {
    this.closest('form').submit();
  }
});
<?php endif; ?>

// Disable tombol simpan jika tidak ada produk
<?php if(empty($products)): ?>
document.querySelector('form[method="post"] button[type="submit"]').disabled = true;
document.querySelector('form[method="post"] button[type="submit"]').title = 'Tidak ada produk tersedia untuk cabang ini';
<?php endif; ?>

// Initialize DataTables
$(document).ready(function(){
    // DataTables untuk tabel stok
    $('#stockTable').DataTable({
        order: [[4, 'asc']], // Sort berdasarkan kolom Stok (ascending)
        pageLength: 25,
        language: {
            url: '/pos-web-starter/assets/js/id.json'
        }
    });

    // DataTables untuk tabel histori
    $('#historyTable').DataTable({
        order: [[0, 'desc']], // Sort berdasarkan Waktu (descending)
        pageLength: 20,
        language: {
            url: '/pos-web-starter/assets/js/id.json'
        }
    });
});
</script>
</body>
</html>
