<?php
ob_start();

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/permissions.php';
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['admin','superadmin','spv']);
restrict_for_kasir();

$u = auth_user();
$error = "";
$msg = "";

// Helper function for escaping HTML
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Ambil data cabang untuk form tambah produk - FILTER BERDASARKAN ROLE
if ($u['role'] === 'superadmin' || $u['role'] === 'admin') {
    $branches = db()->query('SELECT * FROM branches ORDER BY name')->fetchAll();
} else {
    $stmt = db()->prepare('SELECT * FROM branches WHERE id = ? ORDER BY name');
    $stmt->execute([$u['branch_id']]);
    $branches = $stmt->fetchAll();
}

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$branch_filter = $_GET['branch'] ?? '';

// === TAMBAH PRODUK ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_check();
    $img = null;
    try {
        if (!empty($_FILES['image']['name'])) {
            $img = upload_image('image', __DIR__.'/uploads/products');
        }
    } catch(Exception $e) {
        $error = "Error upload gambar: " . $e->getMessage();
    }

    if (empty($error)) {
        try {
            if ($u['role'] === 'spv') {
                $branch_id = $u['branch_id'];
            } else {
                $branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : $u['branch_id'];
            }
            
            $price = (int)str_replace('.', '', $_POST['price']);
            $hpp   = (int)str_replace('.', '', $_POST['hpp']);
            $category = $_POST['category'] ?? 'other';

            db()->prepare('INSERT INTO products(sku,name,price,hpp,image,category,active,branch_id) 
                           VALUES(?,?,?,?,?,?,1,?)')
                ->execute([$_POST['sku'], $_POST['name'], $price, $hpp, $img, $category, $branch_id]);
            
            header("Location: manage_products.php?msg=" . urlencode("Produk '{$_POST['name']}' berhasil ditambahkan"));
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "⚠️ SKU <b>" . e($_POST['sku']) . "</b> sudah digunakan. Gunakan kode lain.";
            } else {
                $error = "Terjadi kesalahan database: " . $e->getMessage();
            }
        }
    }
}

// === UPDATE PRODUK ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    csrf_check();
    $id = (int)$_POST['edit_id'];
    
    if ($u['role'] !== 'superadmin') {
        $check = db()->prepare("SELECT branch_id FROM products WHERE id = ?");
        $check->execute([$id]);
        $product = $check->fetch();
        
        if (!$product || $product['branch_id'] != $u['branch_id']) {
            header("Location: manage_products.php?err=" . urlencode("Tidak dapat mengedit produk cabang lain"));
            exit;
        }
    }
    
    $img = $_POST['old_image'];
    if (!empty($_FILES['image']['name'])) {
        try {
            $img = upload_image('image', __DIR__.'/uploads/products');
            // Hapus gambar lama jika berhasil upload
            if ($img && !empty($_POST['old_image'])) {
                $old_file = __DIR__.'/uploads/products/' . $_POST['old_image'];
                if (file_exists($old_file)) @unlink($old_file);
            }
        } catch(Exception $e) {
            $error = "Error upload gambar: " . $e->getMessage();
        }
    }
    
    if (empty($error)) {
        try {
            $price = (int)str_replace('.', '', $_POST['price']);
            $hpp   = (int)str_replace('.', '', $_POST['hpp']);
            $category = $_POST['category'] ?? 'other';

            if ($u['role'] === 'superadmin') {
                db()->prepare("UPDATE products SET sku=?, name=?, price=?, hpp=?, image=?, category=? WHERE id=?")
                    ->execute([$_POST['sku'], $_POST['name'], $price, $hpp, $img, $category, $id]);
            } else {
                db()->prepare("UPDATE products SET sku=?, name=?, price=?, hpp=?, image=?, category=? WHERE id=? AND branch_id=?")
                    ->execute([$_POST['sku'], $_POST['name'], $price, $hpp, $img, $category, $id, $u['branch_id']]);
            }
            
            header("Location: manage_products.php?msg=" . urlencode("Produk '{$_POST['name']}' berhasil diperbarui"));
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "⚠️ SKU <b>" . e($_POST['sku']) . "</b> sudah digunakan produk lain.";
            } else {
                $error = "Terjadi kesalahan database: " . $e->getMessage();
            }
        }
    }
}

// === HAPUS PRODUK ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_check();
    $id = (int)$_POST['delete_id'];
    
    if ($u['role'] === 'superadmin') {
        $stmt = db()->prepare("SELECT image, name FROM products WHERE id=?");
        $stmt->execute([$id]);
    } else {
        $stmt = db()->prepare("SELECT image, name FROM products WHERE id=? AND branch_id=?");
        $stmt->execute([$id, $u['branch_id']]);
    }
    $p = $stmt->fetch();
    
    if (!$p) {
        header("Location: manage_products.php?err=" . urlencode("Produk tidak ditemukan atau bukan milik cabang Anda"));
        exit;
    }

    try {
        if ($u['role'] === 'superadmin') {
            $res = db()->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        } else {
            $res = db()->prepare("DELETE FROM products WHERE id=? AND branch_id=?")->execute([$id, $u['branch_id']]);
        }
        
        if ($res && $p && !empty($p['image'])) {
            $imgfile = __DIR__."/uploads/products/" . $p['image'];
            if (file_exists($imgfile)) @unlink($imgfile);
        }
        header("Location: manage_products.php?msg=" . urlencode("Produk '{$p['name']}' berhasil dihapus"));
        exit;
    } catch(PDOException $e) {
        if ($e->getCode() == 23000) {
            if ($u['role'] === 'superadmin') {
                db()->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
            } else {
                db()->prepare("UPDATE products SET active=0 WHERE id=? AND branch_id=?")->execute([$id, $u['branch_id']]);
            }
            header("Location: manage_products.php?msg=" . urlencode("Produk '{$p['name']}' dinonaktifkan karena sudah pernah digunakan"));
            exit;
        } else {
            throw $e;
        }
    }
}

// Build query with filters
$query = "SELECT p.*, b.name as branch_name FROM products p LEFT JOIN branches b ON p.branch_id=b.id WHERE p.active=1";
$params = [];

if ($u['role'] !== 'superadmin') {
    $query .= " AND p.branch_id = ?";
    $params[] = $u['branch_id'];
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $query .= " AND p.category = ?";
    $params[] = $category_filter;
}

if (!empty($branch_filter) && $u['role'] === 'superadmin') {
    $query .= " AND p.branch_id = ?";
    $params[] = (int)$branch_filter;
}

$query .= " ORDER BY ";
$query .= $u['role'] === 'superadmin' ? "b.name, p.name" : "p.name";

$stmt = db()->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get categories for filter
$categories = [
    'food' => 'Makanan',
    'drink' => 'Minuman', 
    'other' => 'Lainnya'
];
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manajemen Produk - <?= $u['role'] === 'spv' ? 'Cabang ' . ($branches[0]['name'] ?? '') : 'Multi Cabang' ?></title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body { 
      margin:0; 
      font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; 
      background: linear-gradient(135deg, #064420, #0b6e4f);
      color:#fff; 
      line-height: 1.6;
    }
    
    .container { margin-left:240px; padding:30px; }
    
    .page-header {
      background: linear-gradient(135deg, #0b6e4f, #085c3a);
      border-radius: 16px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    }
    
    .page-title {
      color: #ffd700;
      font-size: 28px;
      font-weight: 700;
      margin: 0 0 10px 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .role-info {
      background: rgba(255, 215, 0, 0.1);
      padding: 12px 16px;
      border-radius: 10px;
      border-left: 4px solid #ffd700;
      margin-top: 15px;
      font-size: 14px;
    }
    
    .card { 
      background: linear-gradient(135deg, #0b6e4f, #085c3a);
      border-radius:16px; 
      padding:24px; 
      margin-bottom:24px; 
      box-shadow:0 8px 32px rgba(0,0,0,0.15);
      border: 1px solid rgba(255, 215, 0, 0.1);
      transition: all 0.3s ease;
    }
    
    .card:hover {
      transform: translateY(-2px);
      box-shadow:0 12px 40px rgba(0,0,0,0.25);
    }
    
    .card h2 { 
      color:#ffd700; 
      margin-top:0; 
      margin-bottom:20px;
      font-size:20px;
      font-weight:600;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .filters {
      background: rgba(255,255,255,0.05);
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr auto;
      gap: 15px;
      align-items: end;
    }
    
    .filter-group label {
      display: block;
      margin-bottom: 6px;
      color: #ffd700;
      font-weight: 500;
      font-size: 13px;
    }
    
    .filter-group input,
    .filter-group select {
      width: 100%;
      padding: 10px 12px;
      border: 2px solid transparent;
      border-radius: 8px;
      background: rgba(255,255,255,0.95);
      color: #333;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .filter-group input:focus,
    .filter-group select:focus {
      outline: none;
      border-color: #ffd700;
      box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
    }
    
    .btn {
      padding: 10px 18px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      font-size: 14px;
      text-align: center;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      color: #064420;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #ffed4e, #ffd700);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255,215,0,0.3);
    }
    
    .btn-secondary {
      background: linear-gradient(135deg, #6c757d, #5a6268);
      color: #fff;
    }
    
    .btn-secondary:hover {
      background: linear-gradient(135deg, #5a6268, #495057);
      transform: translateY(-2px);
    }
    
    .btn-danger {
      background: linear-gradient(135deg, #e74c3c, #c0392b);
      color: #fff;
      padding: 8px 12px;
      font-size: 12px;
    }
    
    .btn-danger:hover {
      background: linear-gradient(135deg, #c0392b, #a93226);
      transform: translateY(-2px);
    }
    
    .btn-success {
      background: linear-gradient(135deg, #27ae60, #2ecc71);
      color: #fff;
      padding: 8px 12px;
      font-size: 12px;
    }
    
    .btn-success:hover {
      background: linear-gradient(135deg, #2ecc71, #58d68d);
      transform: translateY(-2px);
    }
    
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group label {
      margin-bottom: 6px;
      color: #ffd700;
      font-weight: 500;
      font-size: 14px;
    }
    
    .form-group input,
    .form-group select {
      padding: 12px;
      border: 2px solid transparent;
      border-radius: 8px;
      background: rgba(255,255,255,0.95);
      color: #333;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #ffd700;
      box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
    }
    
    .disabled-field {
      background: #f5f5f5 !important;
      color: #666 !important;
      cursor: not-allowed;
    }
    
    .table-container {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      color: #333;
    }
    
    th {
      background: linear-gradient(135deg, #34495e, #2c3e50);
      color: #fff;
      padding: 16px 12px;
      text-align: left;
      font-weight: 600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    td {
      padding: 14px 12px;
      border-bottom: 1px solid #ecf0f1;
      vertical-align: middle;
    }
    
    tr:hover {
      background: #f8f9fa;
    }
    
    .product-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .sku-code {
      background: #e9ecef;
      color: #495057;
      padding: 4px 8px;
      border-radius: 4px;
      font-family: monospace;
      font-weight: 600;
      font-size: 12px;
    }
    
    .category-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #f8f9fa;
      color: #495057;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .branch-badge {
      background: #ffd700;
      color: #064420;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
    }
    
    .price-display {
      font-weight: 600;
      color: #2c3e50;
    }
    
    .actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    
    .edit-form {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }
    
    .edit-form input,
    .edit-form select {
      padding: 6px 8px;
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideInDown 0.5s ease-out;
    }
    
    .alert.success {
      background: linear-gradient(135deg, rgba(39,174,96,0.2), rgba(46,204,113,0.2));
      border: 1px solid #27ae60;
      color: #fff;
    }
    
    .alert.error {
      background: linear-gradient(135deg, rgba(231,76,60,0.2), rgba(192,57,43,0.2));
      border: 1px solid #e74c3c;
      color: #fff;
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #ccc;
    }
    
    .empty-state i {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.5;
    }
    
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .stat-card {
      background: rgba(255,255,255,0.1);
      padding: 16px;
      border-radius: 10px;
      text-align: center;
      border: 1px solid rgba(255,215,0,0.2);
    }
    
    .stat-number {
      font-size: 24px;
      font-weight: 700;
      color: #ffd700;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 12px;
      color: #ccc;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    @keyframes slideInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 1200px) {
      .filters {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 800px) {
      .container {
        margin-left: 70px;
        padding: 20px;
      }
      
      .form-grid {
        grid-template-columns: 1fr;
      }
      
      .filters {
        grid-template-columns: 1fr;
      }
      
      .table-container {
        overflow-x: auto;
      }
      
      .edit-form {
        flex-direction: column;
        align-items: stretch;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <!-- Page Header -->
  <div class="page-header">
    <h1 class="page-title">
      <i class="fa fa-box-open"></i>
      Manajemen Produk
    </h1>
    
    <?php if ($u['role'] === 'spv'): ?>
      <div class="role-info">
        <i class="fa fa-info-circle"></i> 
        <strong>Mode SPV:</strong> Anda hanya dapat mengelola produk di cabang "<?= e($branches[0]['name'] ?? 'Unknown') ?>"
      </div>
    <?php else: ?>
      <div class="role-info">
        <i class="fa fa-crown"></i>
        <strong>Mode <?= strtoupper($u['role']) ?>:</strong> 
        <?= $u['role'] === 'superadmin' ? 'Akses penuh ke semua cabang' : 'Akses ke cabang Anda' ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Notifications -->
  <?php if (!empty($error)): ?>
    <div class="alert error">
      <i class="fa fa-exclamation-triangle"></i>
      <?= $error ?>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($_GET['msg'])): ?>
    <div class="alert success">
      <i class="fa fa-check-circle"></i>
      <?= e($_GET['msg']) ?>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($_GET['err'])): ?>
    <div class="alert error">
      <i class="fa fa-exclamation-triangle"></i>
      <?= e($_GET['err']) ?>
    </div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="card">
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-number"><?= count($rows) ?></div>
        <div class="stat-label">Total Produk</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= count(array_filter($rows, fn($r) => $r['category'] === 'food')) ?></div>
        <div class="stat-label">Makanan</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= count(array_filter($rows, fn($r) => $r['category'] === 'drink')) ?></div>
        <div class="stat-label">Minuman</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= count(array_filter($rows, fn($r) => $r['category'] === 'other')) ?></div>
        <div class="stat-label">Lainnya</div>
      </div>
    </div>
  </div>

  <!-- Add Product Form -->
  <div class="card">
    <h2>
      <i class="fa fa-plus-circle"></i>
      Tambah Produk Baru
    </h2>
    
    <form method="post" enctype="multipart/form-data">
      <?php csrf_field(); ?>
      <input type="hidden" name="add" value="1">
      
      <div class="form-grid">
        <div class="form-group">
          <label for="sku">SKU/Kode Produk</label>
          <input type="text" id="sku" name="sku" required placeholder="Contoh: FOOD001" autocomplete="off">
        </div>
        
        <div class="form-group">
          <label for="name">Nama Produk</label>
          <input type="text" id="name" name="name" required placeholder="Nama produk">
        </div>
        
        <div class="form-group">
          <label for="price">Harga Jual</label>
          <input type="text" id="price" name="price" required placeholder="0" oninput="formatRupiah(this)">
        </div>
        
        <div class="form-group">
          <label for="hpp">HPP (Harga Pokok)</label>
          <input type="text" id="hpp" name="hpp" required placeholder="0" oninput="formatRupiah(this)">
        </div>
        
        <div class="form-group">
          <label for="category">Kategori</label>
          <select id="category" name="category" required>
            <option value="food">🍽️ Makanan</option>
            <option value="drink">🥤 Minuman</option>
            <option value="other">📦 Lainnya</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="branch">Cabang</label>
          <?php if ($u['role'] === 'spv'): ?>
            <input type="text" value="<?= e($branches[0]['name'] ?? '') ?>" class="disabled-field" readonly>
            <input type="hidden" name="branch_id" value="<?= $branches[0]['id'] ?? '' ?>">
          <?php else: ?>
            <select name="branch_id" required>
              <?php foreach($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $b['id']==$u['branch_id']?'selected':'' ?>>
                  <?= e($b['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="form-group">
        <label for="image">Gambar Produk (Opsional)</label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
        <small style="color: #ccc; font-size: 12px;">Format: JPG, PNG, WEBP. Maksimal 2MB</small>
      </div>
      
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-save"></i>
        Simpan Produk
      </button>
    </form>
  </div>

  <!-- Products List -->
  <div class="card">
    <h2>
      <i class="fa fa-list"></i>
      Daftar Produk
      <span style="font-size:14px; color:#ccc; font-weight:normal;">
        (<?= count($rows) ?> produk)
      </span>
    </h2>
    
    <!-- Filters -->
    <form method="get" class="filters">
      <div class="filter-group">
        <label for="search">Cari Produk</label>
        <input type="text" id="search" name="search" value="<?= e($search) ?>" placeholder="Nama atau SKU produk...">
      </div>
      
      <div class="filter-group">
        <label for="category-filter">Kategori</label>
        <select id="category-filter" name="category">
          <option value="">Semua Kategori</option>
          <?php foreach($categories as $key => $label): ?>
            <option value="<?= $key ?>" <?= $category_filter === $key ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <?php if ($u['role'] === 'superadmin'): ?>
      <div class="filter-group">
        <label for="branch-filter">Cabang</label>
        <select id="branch-filter" name="branch">
          <option value="">Semua Cabang</option>
          <?php foreach($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $branch_filter == $b['id'] ? 'selected' : '' ?>>
              <?= e($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      
      <div>
        <button type="submit" class="btn btn-secondary">
          <i class="fa fa-search"></i>
          Filter
        </button>
      </div>
    </form>
    
    <?php if (empty($rows)): ?>
      <div class="empty-state">
        <i class="fa fa-box-open"></i>
        <h3>Belum Ada Produk</h3>
        <p>Tambahkan produk pertama untuk memulai penjualan</p>
        <button onclick="document.getElementById('sku').focus(); document.getElementById('sku').scrollIntoView()" class="btn btn-primary">
          <i class="fa fa-plus"></i>
          Tambah Produk Sekarang
        </button>
      </div>
    <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>SKU</th>
              <th>Produk</th>
              <th>Harga</th>
              <th>HPP</th>
              <?php if ($u['role'] === 'superadmin'): ?>
                <th>Cabang</th>
              <?php endif; ?>
              <th>Kategori</th>
              <th>Gambar</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td>
                  <span class="sku-code"><?= e($r['sku']) ?></span>
                </td>
                <td>
                  <strong style="font-size: 14px;"><?= e($r['name']) ?></strong>
                </td>
                <td>
                  <span class="price-display">Rp <?= number_format($r['price'],0,',','.') ?></span>
                </td>
                <td>
                  <span style="color: #666;">Rp <?= number_format($r['hpp'] ?? 0,0,',','.') ?></span>
                </td>
                <?php if ($u['role'] === 'superadmin'): ?>
                  <td>
                    <span class="branch-badge"><?= e($r['branch_name'] ?? '-') ?></span>
                  </td>
                <?php endif; ?>
                <td>
                  <?php 
                    $catIcons = ['food' => 'fa-utensils', 'drink' => 'fa-glass-water', 'other' => 'fa-box'];
                    $cat = $r['category'] ?? 'other';
                  ?>
                  <span class="category-badge">
                    <i class="fa <?= $catIcons[$cat] ?? 'fa-box' ?>"></i>
                    <?= $categories[$cat] ?? 'Lainnya' ?>
                  </span>
                </td>
                <td>
                  <?php if($r['image']): ?>
                    <img src="uploads/products/<?= e($r['image']) ?>" alt="<?= e($r['name']) ?>" class="product-image">
                  <?php else: ?>
                    <div style="width:50px; height:50px; background:#f8f9fa; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#ccc;">
                      <i class="fa fa-image"></i>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="actions">
                    <!-- Edit Form -->
                    <form method="post" enctype="multipart/form-data" class="edit-form">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="edit_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="old_image" value="<?= e($r['image']) ?>">
                      
                      <input name="sku" value="<?= e($r['sku']) ?>" style="width:80px;" required title="SKU">
                      <input name="name" value="<?= e($r['name']) ?>" style="width:120px;" required title="Nama">
                      <input name="price" value="<?= number_format($r['price'],0,',','.') ?>" style="width:90px;" required oninput="formatRupiah(this)" title="Harga">
                      <input name="hpp" value="<?= number_format($r['hpp'] ?? 0,0,',','.') ?>" style="width:80px;" required oninput="formatRupiah(this)" title="HPP">
                      
                      <select name="category" style="width:90px;">
                        <option value="food" <?= $r['category']=='food'?'selected':'' ?>>Makanan</option>
                        <option value="drink" <?= $r['category']=='drink'?'selected':'' ?>>Minuman</option>
                        <option value="other" <?= $r['category']=='other'?'selected':'' ?>>Lainnya</option>
                      </select>
                      
                      <input type="file" name="image" accept="image/*" style="width:100px;" title="Ganti Gambar">
                      
                      <button type="submit" class="btn btn-success" title="Simpan Perubahan">
                        <i class="fa fa-save"></i>
                      </button>
                    </form>
                    
                    <!-- Delete Form -->
                    <form method="post" style="display:inline;" onsubmit="return confirm('⚠️ Yakin hapus produk \'<?= e($r['name']) ?>\'?\n\nTindakan ini tidak dapat dibatalkan!');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn btn-danger" title="Hapus Produk">
                        <i class="fa fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Format rupiah input
function formatRupiah(el) {
  let angka = el.value.replace(/\D/g, '');
  let formatted = '';
  let len = angka.length;
  for(let i=len-1, g=0; i>=0; i--, g++) {
    formatted = angka[i] + formatted;
    if(g%3===2 && i!==0) formatted = '.' + formatted;
  }
  el.value = formatted;
}

// Auto-focus pada SKU input saat load
document.addEventListener('DOMContentLoaded', function() {
  const skuInput = document.getElementById('sku');
  if (skuInput) {
    skuInput.focus();
  }
  
  // Auto-hide notifications after 5 seconds
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transform = 'translateY(-20px)';
      setTimeout(() => {
        alert.style.display = 'none';
      }, 300);
    }, 5000);
  });
});

// Search shortcut
document.addEventListener('keydown', function(e) {
  if (e.ctrlKey && e.key === 'f') {
    e.preventDefault();
    const searchInput = document.getElementById('search');
    if (searchInput) {
      searchInput.focus();
      searchInput.select();
    }
  }
});

// Enhanced form validation
document.querySelector('form[method="post"]').addEventListener('submit', function(e) {
  const sku = document.getElementById('sku').value;
  const name = document.getElementById('name').value;
  const price = document.getElementById('price').value;
  const hpp = document.getElementById('hpp').value;
  
  if (!sku.trim() || !name.trim() || !price.trim() || !hpp.trim()) {
    e.preventDefault();
    alert('⚠️ Mohon lengkapi semua field yang diperlukan');
    return false;
  }
  
  // Show loading state
  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menyimpan...';
    submitBtn.disabled = true;
  }
});
</script>
</body>
</html>
<?php
if (ob_get_level()) {
    ob_end_flush();
}
?>
