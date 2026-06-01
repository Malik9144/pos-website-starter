<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';

// Include navigation dengan path yang benar
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['admin','superadmin','spv']);
$u = auth_user();

// Parameter filter dari GET
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$product_filter = $_GET['product_id'] ?? '';
$branch_filter_get = $_GET['branch_id'] ?? '';
$movement_type = $_GET['movement_type'] ?? '';

// Build WHERE clause
$where_conditions = ['1=1'];
$filter_params = [];

// Date filter
if ($date_from) {
    $where_conditions[] = 'sm.created_at >= ?';
    $filter_params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $where_conditions[] = 'sm.created_at <= ?';
    $filter_params[] = $date_to . ' 23:59:59';
}

// Product filter
if ($product_filter) {
    $where_conditions[] = 'sm.product_id = ?';
    $filter_params[] = (int)$product_filter;
}

// Branch filter berdasarkan role
if ($u['role'] === 'superadmin' && $branch_filter_get) {
    $where_conditions[] = 'sm.branch_id = ?';
    $filter_params[] = (int)$branch_filter_get;
} elseif ($u['role'] !== 'superadmin') {
    $where_conditions[] = 'sm.branch_id = ?';
    $filter_params[] = $u['branch_id'];
}

// Movement type filter
if ($movement_type) {
    $where_conditions[] = 'sm.movement_type = ?';
    $filter_params[] = $movement_type;
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination - PERBAIKAN: gunakan integer langsung di SQL
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) as total 
              FROM stock_movements sm 
              JOIN products p ON sm.product_id = p.id 
              JOIN branches b ON sm.branch_id = b.id 
              WHERE $where_clause";
$count_stmt = db()->prepare($count_sql);
$count_stmt->execute($filter_params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Get movements data - PERBAIKAN: Gunakan concatenation untuk LIMIT/OFFSET
$sql = "SELECT sm.*, p.name AS product_name, p.sku, b.name AS branch_name, u.name AS user_name
        FROM stock_movements sm 
        JOIN products p ON sm.product_id = p.id 
        JOIN branches b ON sm.branch_id = b.id 
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE $where_clause
        ORDER BY sm.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = db()->prepare($sql);
$stmt->execute($filter_params);
$movements = $stmt->fetchAll();

// Get filter options
$products = db()->query("SELECT id, name, sku FROM products WHERE active=1 ORDER BY name")->fetchAll();

if ($u['role'] === 'superadmin') {
    $branches = db()->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
} else {
    $branches = db()->prepare("SELECT id, name FROM branches WHERE id = ? ORDER BY name");
    $branches->execute([$u['branch_id']]);
    $branches = $branches->fetchAll();
}

// Summary statistics
$summary_sql = "SELECT 
                    movement_type,
                    COUNT(*) as count,
                    SUM(quantity) as total_qty
                FROM stock_movements sm 
                JOIN products p ON sm.product_id = p.id 
                JOIN branches b ON sm.branch_id = b.id 
                WHERE $where_clause
                GROUP BY movement_type";
$summary_stmt = db()->prepare($summary_sql);
$summary_stmt->execute($filter_params);
$summary_data = $summary_stmt->fetchAll();

// Convert summary to associative array
$summary = [];
foreach ($summary_data as $row) {
    $summary[$row['movement_type']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Stock Movement - <?= $u['role'] === 'spv' ? 'Cabang ' . ($branches[0]['name'] ?? 'N/A') : 'Multi Cabang' ?></title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body {margin:0;font-family:'Segoe UI',sans-serif;background:#064420;color:#fff;}
    .container {margin-left:240px; padding:30px;}
    .card {background:#0b6e4f;border-radius:12px;padding:20px;box-shadow:0 5px 12px rgba(0,0,0,0.3);margin-bottom:20px;}
    h2 {color:#ffd700;margin-top:0;}
    .role-badge { 
      display: inline-block; 
      background: #ffd700; 
      color: #064420; 
      padding: 4px 8px; 
      border-radius: 4px; 
      font-size: 12px; 
      font-weight: bold; 
      margin-left: 10px;
    }
    .filter-form {display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px;}
    label {display:block;margin-bottom:5px;font-weight:bold;}
    select,input {width:100%;padding:8px;border-radius:6px;border:none;box-sizing:border-box;}
    button {margin-top:10px;padding:10px 15px;background:#ffd700;border:none;border-radius:6px;font-weight:bold;cursor:pointer;color:#064420;}
    button:hover {background:#e6c200;}
    .btn-secondary {background:#6c757d;color:#fff;}
    .btn-secondary:hover {background:#5a6268;}
    table {width:100%;border-collapse:collapse;margin-top:15px;background:#fff;color:#000;}
    th,td {padding:10px;border-bottom:1px solid #ddd;text-align:left;}
    th {background:#064420;color:#ffd700;}
    tr:hover {background:#f9f9f9;}
    .movement-in {color:#27ae60;font-weight:bold;}
    .movement-out {color:#e74c3c;font-weight:bold;}
    .movement-adjustment {color:#f39c12;font-weight:bold;}
    .pagination {display:flex;justify-content:center;gap:5px;margin:20px 0;}
    .pagination a, .pagination span {padding:8px 12px;background:#0b6e4f;color:#fff;text-decoration:none;border-radius:4px;}
    .pagination a:hover {background:#ffd700;color:#064420;}
    .pagination .current {background:#ffd700;color:#064420;}
    .summary-cards {display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px;}
    .summary-card {background:#085c3a;padding:15px;border-radius:8px;text-align:center;}
    .summary-number {font-size:24px;font-weight:bold;color:#ffd700;}
    .summary-label {font-size:12px;color:#ccc;text-transform:uppercase;}
    .no-data {text-align:center;padding:40px;color:#ccc;}
    .alert {padding:10px;border-radius:6px;margin-bottom:15px;}
    .alert.info {background:#3498db;color:#fff;}
    .branch-info { background:#27ae60; color:#fff; padding:8px 12px; border-radius:6px; margin-bottom:15px; }
    .export-btn {background:#17a2b8;color:#fff;}
    .export-btn:hover {background:#138496;}
    
    /* Responsive untuk mobile - sesuaikan dengan sidebar */
    @media (max-width: 800px) {
      .container {margin-left:70px; padding:18px;}
      .card { padding:11px;}
      table {font-size:13px;}
      .filter-form {grid-template-columns:1fr;}
      .summary-cards {grid-template-columns:1fr 1fr;}
    }
    
    /* Breadcrumb untuk navigasi yang lebih jelas */
    .breadcrumb {
      background:#085c3a;
      padding:10px 20px;
      border-radius:8px;
      margin-bottom:20px;
      font-size:14px;
    }
    .breadcrumb a {
      color:#ffd700;
      text-decoration:none;
    }
    .breadcrumb a:hover {
      text-decoration:underline;
    }
    .breadcrumb .separator {
      margin:0 8px;
      color:#ccc;
    }
  </style>
</head>
<body>
<div class="container">
  <!-- Breadcrumb Navigation -->
  <div class="breadcrumb">
    <a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <span class="separator">/</span>
    <a href="#">Laporan</a>
    <span class="separator">/</span>
    <span>Riwayat Stok</span>
  </div>

  <!-- Info Role dan Cabang -->
  <?php if ($u['role'] === 'spv'): ?>
    <div class="branch-info">
      <i class="fa fa-info-circle"></i> 
      <strong>Mode SPV:</strong> Menampilkan pergerakan stok cabang "<?= htmlspecialchars($branches[0]['name'] ?? 'N/A') ?>"
    </div>
  <?php endif; ?>

  <!-- Summary Cards -->
  <div class="summary-cards">
    <div class="summary-card">
      <div class="summary-number"><?= number_format($total_records) ?></div>
      <div class="summary-label">Total Pergerakan</div>
    </div>
    <div class="summary-card">
      <div class="summary-number movement-in"><?= $summary['in'] ?? 0 ?></div>
      <div class="summary-label">Stok Masuk</div>
    </div>
    <div class="summary-card">
      <div class="summary-number movement-out"><?= $summary['out'] ?? 0 ?></div>
      <div class="summary-label">Stok Keluar</div>
    </div>
    <div class="summary-card">
      <div class="summary-number movement-adjustment"><?= $summary['adjustment'] ?? 0 ?></div>
      <div class="summary-label">Penyesuaian</div>
    </div>
  </div>

  <!-- Filter -->
  <div class="card">
    <h2>
      <i class="fa fa-filter"></i> Filter Pergerakan Stok
      <span class="role-badge"><?= strtoupper($u['role']) ?></span>
    </h2>
    
    <form method="get" class="filter-form">
      <div>
        <label>Tanggal Dari</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
      </div>
      
      <div>
        <label>Tanggal Sampai</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
      </div>
      
      <div>
        <label>Produk</label>
        <select name="product_id">
          <option value="">-- Semua Produk --</option>
          <?php foreach($products as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $product_filter == $p['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['sku']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <?php if ($u['role'] === 'superadmin'): ?>
      <div>
        <label>Cabang</label>
        <select name="branch_id">
          <option value="">-- Semua Cabang --</option>
          <?php foreach($branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= $branch_filter_get == $b['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      
      <div>
        <label>Tipe Pergerakan</label>
        <select name="movement_type">
          <option value="">-- Semua Tipe --</option>
          <option value="in" <?= $movement_type === 'in' ? 'selected' : '' ?>>Masuk</option>
          <option value="out" <?= $movement_type === 'out' ? 'selected' : '' ?>>Keluar</option>
          <option value="adjustment" <?= $movement_type === 'adjustment' ? 'selected' : '' ?>>Penyesuaian</option>
        </select>
      </div>
      
      <div style="display:flex;gap:10px;align-items:end;">
        <button type="submit"><i class="fa fa-search"></i> Filter</button>
        <a href="stock_movements.php" class="btn-secondary" style="text-decoration:none;padding:10px 15px;border-radius:6px;display:inline-block;">
          <i class="fa fa-refresh"></i> Reset
        </a>
      </div>
    </form>
  </div>

  <!-- Results -->
  <div class="card">
    <h2>
      <i class="fa fa-list"></i> Pergerakan Stok
      <span style="font-size:14px; color:#ccc;">(<?= number_format($total_records) ?> records)</span>
      <?php if ($total_pages > 1): ?>
        <span style="font-size:14px; color:#ccc;">- Halaman <?= $page ?> dari <?= $total_pages ?></span>
      <?php endif; ?>
    </h2>
    
    <?php if (empty($movements)): ?>
      <div class="no-data">
        <i class="fa fa-inbox" style="font-size:48px; margin-bottom:15px;"></i>
        <p>Tidak ada pergerakan stok ditemukan dengan filter ini.</p>
        <a href="stock_movements.php" style="color:#ffd700;text-decoration:none;">
          <i class="fa fa-refresh"></i> Reset Filter
        </a>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <tr>
            <th>Waktu</th>
            <th>Produk</th>
            <th>SKU</th>
            <?php if ($u['role'] === 'superadmin'): ?>
              <th>Cabang</th>
            <?php endif; ?>
            <th>Tipe</th>
            <th>Qty</th>
            <th>Keterangan</th>
            <th>User</th>
          </tr>
          <?php foreach($movements as $m): ?>
            <tr>
              <td>
                <strong><?= date('d/m/Y', strtotime($m['created_at'])) ?></strong><br>
                <small><?= date('H:i:s', strtotime($m['created_at'])) ?></small>
              </td>
              <td><strong><?= htmlspecialchars($m['product_name']) ?></strong></td>
              <td><code><?= htmlspecialchars($m['sku']) ?></code></td>
              <?php if ($u['role'] === 'superadmin'): ?>
                <td>
                  <span style="background:#ffd700; color:#064420; padding:2px 6px; border-radius:3px; font-size:11px;">
                    <?= htmlspecialchars($m['branch_name']) ?>
                  </span>
                </td>
              <?php endif; ?>
              <td>
                <?php 
                  $type_class = '';
                  $type_icon = '';
                  $type_text = '';
                  
                  switch($m['movement_type']) {
                    case 'in':
                      $type_class = 'movement-in';
                      $type_icon = 'fa-arrow-up';
                      $type_text = 'Masuk';
                      break;
                    case 'out':
                      $type_class = 'movement-out';
                      $type_icon = 'fa-arrow-down';
                      $type_text = 'Keluar';
                      break;
                    case 'adjustment':
                      $type_class = 'movement-adjustment';
                      $type_icon = 'fa-edit';
                      $type_text = 'Penyesuaian';
                      break;
                    default:
                      $type_class = '';
                      $type_icon = 'fa-question';
                      $type_text = ucfirst($m['movement_type']);
                  }
                ?>
                <span class="<?= $type_class ?>">
                  <i class="fa <?= $type_icon ?>"></i> <?= $type_text ?>
                </span>
              </td>
              <td>
                <strong style="font-size:16px;">
                  <?= $m['movement_type'] === 'out' ? '-' : '+' ?><?= number_format($m['quantity']) ?>
                </strong>
              </td>
              <td>
                <?php if (!empty($m['note'])): ?>
                  <?= htmlspecialchars($m['note']) ?>
                <?php else: ?>
                  <em style="color:#999;">-</em>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($m['user_name'])): ?>
                  <?= htmlspecialchars($m['user_name']) ?>
                <?php else: ?>
                  <em style="color:#999;">System</em>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
              <i class="fa fa-chevron-left"></i> Prev
            </a>
          <?php endif; ?>
          
          <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            if ($start > 1):
          ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
            <?php if ($start > 2): ?>
              <span>...</span>
            <?php endif; ?>
          <?php endif; ?>
          
          <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i == $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?>
              <span>...</span>
            <?php endif; ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
          <?php endif; ?>
          
          <?php if ($page < $total_pages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
              Next <i class="fa fa-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Quick Actions -->
  <div class="card">
    <h2><i class="fa fa-tools"></i> Quick Actions</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <a href="manage_stockbranch.php" class="btn-secondary" style="text-decoration:none;padding:10px 15px;border-radius:6px;display:inline-block;">
        <i class="fa fa-plus"></i> Tambah Stock Movement
      </a>
      
      <?php if ($u['role'] === 'superadmin' || $u['role'] === 'admin'): ?>
      <a href="reports.php" class="btn-secondary" style="text-decoration:none;padding:10px 15px;border-radius:6px;display:inline-block;">
        <i class="fa fa-chart-line"></i> Laporan Penjualan
      </a>
      <a href="laporan_gudang.php" class="btn-secondary" style="text-decoration:none;padding:10px 15px;border-radius:6px;display:inline-block;">
        <i class="fa fa-warehouse"></i> Laporan Gudang
      </a>
      <?php endif; ?>
      
      <button onclick="window.print()" class="export-btn">
        <i class="fa fa-print"></i> Print Report
      </button>
    </div>
  </div>
</div>

<script>
// Auto refresh setiap 5 menit untuk data real-time
setTimeout(() => {
  if (confirm('Data akan di-refresh untuk update terbaru. Lanjutkan?')) {
    window.location.reload();
  }
}, 300000);

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
  const dateFrom = document.querySelector('input[name="date_from"]').value;
  const dateTo = document.querySelector('input[name="date_to"]').value;
  
  if (dateFrom && dateTo && dateFrom > dateTo) {
    e.preventDefault();
    alert('Tanggal "Dari" tidak boleh lebih besar dari tanggal "Sampai"');
  }
});

// Highlight active menu item in sidebar (jika diperlukan)
document.addEventListener('DOMContentLoaded', function() {
  const currentPath = window.location.pathname;
  const menuLinks = document.querySelectorAll('.sidebar a');
  
  menuLinks.forEach(link => {
    if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
      link.style.background = '#215f46';
      link.style.color = '#fff';
    }
  });
  
  // Auto-expand submenu laporan jika di halaman stock movements
  if (currentPath.includes('stock_movements.php')) {
    toggleSubnav('submenu-laporan');
  }
});
</script>
</body>
</html>