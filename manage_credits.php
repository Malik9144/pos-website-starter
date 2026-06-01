<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['admin','superadmin','spv']);
$u = auth_user();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add_payment') {
            // Tambah pembayaran kredit
            $credit_id = (int)$_POST['credit_id'];
            $amount = (float)str_replace(['.', ','], ['', '.'], $_POST['amount']);
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $note = trim($_POST['note'] ?? '');
            
            if ($amount <= 0) {
                $error = 'Jumlah pembayaran harus lebih dari 0';
            } else {
                // Cek sisa hutang
                $credit = db()->prepare("SELECT * FROM credits WHERE id = ? AND branch_id = ?");
                $credit->execute([$credit_id, $u['branch_id']]);
                $credit_data = $credit->fetch();
                
                if (!$credit_data) {
                    $error = 'Data kredit tidak ditemukan';
                } else {
                    $remaining = $credit_data['total_amount'] - $credit_data['paid_amount'];
                    
                    if ($amount > $remaining) {
                        $error = 'Jumlah pembayaran melebihi sisa hutang (Rp ' . number_format($remaining, 0, ',', '.') . ')';
                    } else {
                        try {
                            // Start transaction
                            db()->beginTransaction();
                            
                            // Insert pembayaran
                            $stmt = db()->prepare("INSERT INTO credit_payments (credit_id, amount, payment_method, note, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([$credit_id, $amount, $payment_method, $note, $u['id']]);
                            
                            // Update total paid di credits dan status
                            $new_status = ($credit_data['paid_amount'] + $amount >= $credit_data['total_amount']) ? 'paid' : 'partial';
                            $stmt = db()->prepare("UPDATE credits SET paid_amount = paid_amount + ?, status = ? WHERE id = ?");
                            $stmt->execute([$amount, $new_status, $credit_id]);
                            
                            // **SINKRONISASI STATUS KE ORDERS**
                            if ($credit_data['order_id']) {
                                $order_status = ($new_status === 'paid') ? 'completed' : 'credit';
                                $stmt_order = db()->prepare("UPDATE orders SET status = ? WHERE id = ?");
                                $stmt_order->execute([$order_status, $credit_data['order_id']]);
                            }
                            
                            // Commit transaction
                            db()->commit();
                            
                            $success = 'Pembayaran berhasil dicatat';
                        } catch (Exception $e) {
                            // Rollback on error
                            db()->rollback();
                            $error = 'Terjadi kesalahan: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
        
        if ($action === 'update_status') {
            // Update status kredit
            $credit_id = (int)$_POST['credit_id'];
            $status = $_POST['status'];
            
            try {
                // Start transaction
                db()->beginTransaction();
                
                // Get credit data first
                $credit_stmt = db()->prepare("SELECT order_id FROM credits WHERE id = ? AND branch_id = ?");
                $credit_stmt->execute([$credit_id, $u['branch_id']]);
                $credit_data = $credit_stmt->fetch();
                
                if ($credit_data) {
                    // Update credits status
                    $stmt = db()->prepare("UPDATE credits SET status = ? WHERE id = ? AND branch_id = ?");
                    $stmt->execute([$status, $credit_id, $u['branch_id']]);
                    
                    // **SINKRONISASI STATUS KE ORDERS**
                    if ($credit_data['order_id']) {
                        $order_status = match($status) {
                            'paid' => 'completed',
                            'cancelled' => 'cancelled', 
                            'unpaid' => 'credit',
                            'partial' => 'credit',
                            default => 'credit'
                        };
                        
                        $stmt_order = db()->prepare("UPDATE orders SET status = ? WHERE id = ?");
                        $stmt_order->execute([$order_status, $credit_data['order_id']]);
                    }
                    
                    // Commit transaction
                    db()->commit();
                    
                    $success = 'Status berhasil diupdate';
                } else {
                    db()->rollback();
                    $error = 'Data kredit tidak ditemukan';
                }
            } catch (Exception $e) {
                // Rollback on error
                db()->rollback();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$customer_filter = $_GET['customer'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

// FILTER 1: Status
if ($status_filter) {
    $where_conditions[] = 'c.status = ?';
    $params[] = $status_filter;
}

// FILTER 2: Customer name
if ($customer_filter) {
    $where_conditions[] = 'c.customer_name LIKE ?';
    $params[] = "%$customer_filter%";
}

// FILTER 3: Date from
if ($date_from) {
    $where_conditions[] = 'DATE(c.created_at) >= ?';
    $params[] = $date_from;
}

// FILTER 4: Date to
if ($date_to) {
    $where_conditions[] = 'DATE(c.created_at) <= ?';
    $params[] = $date_to;
}

// FILTER 5: Branch (untuk SPV)
if ($u['role'] !== 'superadmin') {
    $where_conditions[] = 'c.branch_id = ?';
    $params[] = $u['branch_id'];
}

$where_clause = implode(' AND ', $where_conditions);

// Get credits data
$sql = "SELECT c.*, b.name as branch_name, u.name as user_name,
               o.id as order_id, o.created_at as order_date
        FROM credits c
        LEFT JOIN branches b ON c.branch_id = b.id
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN orders o ON c.order_id = o.id
        WHERE $where_clause
        ORDER BY c.created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$credits = $stmt->fetchAll();

// Get summary statistics
$summary_sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(total_amount) as total_amount,
                    SUM(paid_amount) as paid_amount,
                    SUM(total_amount - paid_amount) as remaining_amount
                FROM credits c
                WHERE 1=1";

// Add branch filter untuk summary jika bukan superadmin
$summary_params = [];
if ($u['role'] !== 'superadmin') {
    $summary_sql .= " AND c.branch_id = ?";
    $summary_params[] = $u['branch_id'];
}

$summary_sql .= " GROUP BY status";

$summary_stmt = db()->prepare($summary_sql);
$summary_stmt->execute($summary_params);
$summary_data = $summary_stmt->fetchAll();

$summary = [];
foreach ($summary_data as $row) {
    $summary[$row['status']] = $row;
}

// Get branches for superadmin
$branches = [];
if ($u['role'] === 'superadmin') {
    $branches = db()->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manajemen Kredit - <?= $u['role'] === 'spv' ? 'Cabang' : 'Multi Cabang' ?></title>
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
    select,input,textarea {width:100%;padding:8px;border-radius:6px;border:none;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
    button {margin-top:10px;padding:10px 15px;background:#ffd700;border:none;border-radius:6px;font-weight:bold;cursor:pointer;color:#064420;}
    button:hover {background:#e6c200;}
    .btn-secondary {background:#6c757d;color:#fff;}
    .btn-secondary:hover {background:#5a6268;}
    .btn-success {background:#27ae60;color:#fff;}
    .btn-success:hover {background:#219a52;}
    .btn-warning {background:#f39c12;color:#fff;}
    .btn-warning:hover {background:#e67e22;}
    table {width:100%;border-collapse:collapse;margin-top:15px;background:#fff;color:#000;}
    th,td {padding:10px;border-bottom:1px solid #ddd;text-align:left;}
    th {background:#064420;color:#ffd700;}
    tr:hover {background:#f9f9f9;}
    .status-unpaid {color:#e74c3c;font-weight:bold;}
    .status-partial {color:#f39c12;font-weight:bold;}
    .status-paid {color:#27ae60;font-weight:bold;}
    .status-cancelled {color:#95a5a6;font-weight:bold;}
    .summary-cards {display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;margin-bottom:20px;}
    .summary-card {background:#085c3a;padding:15px;border-radius:8px;text-align:center;}
    .summary-number {font-size:20px;font-weight:bold;color:#ffd700;}
    .summary-label {font-size:12px;color:#ccc;text-transform:uppercase;}
    .no-data {text-align:center;padding:40px;color:#ccc;}
    .alert {padding:10px;border-radius:6px;margin-bottom:15px;}
    .alert.success {background:#27ae60;color:#fff;}
    .alert.error {background:#e74c3c;color:#fff;}
    .branch-info { background:#27ae60; color:#fff; padding:8px 12px; border-radius:6px; margin-bottom:15px; }
    .modal {display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);}
    .modal-content {background:#0b6e4f; margin:5% auto; padding:20px; border-radius:12px; width:90%; max-width:600px; color:#fff;}
    .modal-header {display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
    .modal-title {color:#ffd700; margin:0;}
    .close {color:#fff; font-size:28px; font-weight:bold; cursor:pointer;}
    .close:hover {color:#ffd700;}
    .form-group {margin-bottom:15px;}
    .progress-bar {background:#085c3a; border-radius:10px; overflow:hidden; height:20px; margin:5px 0;}
    .progress-fill {background:#27ae60; height:100%; transition:width 0.3s;}
    .sync-indicator {
      background: #3498db;
      color: #fff;
      padding: 6px 10px;
      border-radius: 12px;
      font-size: 11px;
      margin-left: 8px;
      display: inline-block;
    }
    @media (max-width: 800px) {
      .container {margin-left:70px; padding:18px;}
      .card { padding:11px;}
      table {font-size:13px;}
      .filter-form {grid-template-columns:1fr;}
      .summary-cards {grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
<div class="container">
  <!-- Info Role dan Cabang -->
  <?php if ($u['role'] === 'spv'): ?>
    <div class="branch-info">
      <i class="fa fa-info-circle"></i> 
      <strong>Mode SPV:</strong> Menampilkan data kredit cabang Anda
      <span class="sync-indicator">
        <i class="fa fa-sync"></i> Auto-Sync Orders
      </span>
    </div>
  <?php endif; ?>

  <!-- Notifikasi -->
  <?php if (isset($success)): ?>
    <div class="alert success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if (isset($error)): ?>
    <div class="alert error"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Summary Cards -->
  <div class="summary-cards">
    <div class="summary-card">
      <div class="summary-number"><?= count($credits) ?></div>
      <div class="summary-label">Total Kredit</div>
    </div>
    <div class="summary-card">
      <div class="summary-number status-unpaid"><?= $summary['unpaid']['count'] ?? 0 ?></div>
      <div class="summary-label">Belum Bayar</div>
      <div style="font-size:12px;margin-top:5px;">Rp <?= number_format($summary['unpaid']['remaining_amount'] ?? 0, 0, ',', '.') ?></div>
    </div>
    <div class="summary-card">
      <div class="summary-number status-partial"><?= $summary['partial']['count'] ?? 0 ?></div>
      <div class="summary-label">Bayar Sebagian</div>
      <div style="font-size:12px;margin-top:5px;">Rp <?= number_format($summary['partial']['remaining_amount'] ?? 0, 0, ',', '.') ?></div>
    </div>
    <div class="summary-card">
      <div class="summary-number status-paid"><?= $summary['paid']['count'] ?? 0 ?></div>
      <div class="summary-label">Lunas</div>
      <div style="font-size:12px;margin-top:5px;">Rp <?= number_format($summary['paid']['total_amount'] ?? 0, 0, ',', '.') ?></div>
    </div>
  </div>

  <!-- Filter -->
  <div class="card">
    <h2>
      <i class="fa fa-filter"></i> Filter Data Kredit
      <span class="role-badge"><?= strtoupper($u['role']) ?></span>
      <span class="sync-indicator">
        <i class="fa fa-link"></i> Sinkron dengan Orders
      </span>
    </h2>
    
    <form method="get" class="filter-form">
      <div>
        <label>Status</label>
        <select name="status">
          <option value="">-- Semua Status --</option>
          <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Belum Bayar</option>
          <option value="partial" <?= $status_filter === 'partial' ? 'selected' : '' ?>>Bayar Sebagian</option>
          <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Lunas</option>
          <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
        </select>
      </div>
      
      <div>
        <label>Customer</label>
        <input type="text" name="customer" value="<?= htmlspecialchars($customer_filter) ?>" placeholder="Nama customer">
      </div>
      
      <div>
        <label>Tanggal Dari</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
      </div>
      
      <div>
        <label>Tanggal Sampai</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
      </div>
      
      <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
        <button type="submit"><i class="fa fa-search"></i> Filter</button>
        <a href="manage_credits.php" class="btn-secondary" style="text-decoration:none;padding:10px 15px;border-radius:6px;display:inline-block;">
          <i class="fa fa-refresh"></i> Reset
        </a>
        <a href="export_credits_csv.php?status=<?= urlencode($status_filter) ?>&customer=<?= urlencode($customer_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
           class="btn-success" 
           style="text-decoration:none;padding:10px 15px;border-radius:6px;display:inline-block;background:#27ae60;color:#fff;">
          <i class="fa fa-file-csv"></i> Export CSV
        </a>
      </div>
    </form>
  </div>

  <!-- Data Kredit -->
  <div class="card">
    <h2>
      <i class="fa fa-credit-card"></i> Data Kredit
      <span style="font-size:14px; color:#ccc;">(<?= count($credits) ?> records)</span>
    </h2>
    
    <?php if (empty($credits)): ?>
      <div class="no-data">
        <i class="fa fa-credit-card" style="font-size:48px; margin-bottom:15px;"></i>
        <p>Tidak ada data kredit ditemukan.</p>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <tr>
            <th>Tanggal</th>
            <th>Customer</th>
            <th>Order ID</th>
            <?php if ($u['role'] === 'superadmin'): ?>
              <th>Cabang</th>
            <?php endif; ?>
            <th>Total</th>
            <th>Terbayar</th>
            <th>Sisa</th>
            <th>Progress</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
          <?php foreach($credits as $credit): ?>
            <?php 
              $remaining = $credit['total_amount'] - $credit['paid_amount'];
              $progress = $credit['total_amount'] > 0 ? ($credit['paid_amount'] / $credit['total_amount']) * 100 : 0;
            ?>
            <tr>
              <td>
                <strong><?= date('d/m/Y', strtotime($credit['created_at'])) ?></strong><br>
                <small><?= date('H:i', strtotime($credit['created_at'])) ?></small>
              </td>
              <td>
                <strong><?= htmlspecialchars($credit['customer_name']) ?></strong>
                <?php if (!empty($credit['customer_phone'])): ?>
                  <br><small><?= htmlspecialchars($credit['customer_phone']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($credit['order_id']): ?>
                  <a href="receipt.php?id=<?= $credit['order_id'] ?>" target="_blank" style="color:#3498db;">
                    #<?= $credit['order_id'] ?>
                  </a>
                  <br><small style="color:#3498db;">
                    <i class="fa fa-link" title="Tersinkronisasi dengan Orders"></i> Sync
                  </small>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <?php if ($u['role'] === 'superadmin'): ?>
                <td>
                  <span style="background:#ffd700; color:#064420; padding:2px 6px; border-radius:3px; font-size:11px;">
                    <?= htmlspecialchars($credit['branch_name']) ?>
                  </span>
                </td>
              <?php endif; ?>
              <td><strong>Rp <?= number_format($credit['total_amount'], 0, ',', '.') ?></strong></td>
              <td>Rp <?= number_format($credit['paid_amount'], 0, ',', '.') ?></td>
              <td>
                <?php if ($remaining > 0): ?>
                  <strong style="color:#e74c3c;">Rp <?= number_format($remaining, 0, ',', '.') ?></strong>
                <?php else: ?>
                  <span style="color:#27ae60;">Lunas</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="progress-bar">
                  <div class="progress-fill" style="width: <?= number_format($progress, 1) ?>%"></div>
                </div>
                <small><?= number_format($progress, 1) ?>%</small>
              </td>
              <td>
                <span class="status-<?= $credit['status'] ?>">
                  <?php
                    $status_labels = [
                      'unpaid' => 'Belum Bayar',
                      'partial' => 'Sebagian',
                      'paid' => 'Lunas',
                      'cancelled' => 'Dibatalkan'
                    ];
                    echo $status_labels[$credit['status']] ?? ucfirst($credit['status']);
                  ?>
                </span>
              </td>
              <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                  <?php if ($credit['status'] !== 'paid' && $credit['status'] !== 'cancelled'): ?>
                    <button 
                      class="btn-success btn-pay-credit" 
                      style="font-size:12px;padding:5px 8px;"
                      data-credit-id="<?= $credit['id'] ?>"
                      data-customer-name="<?= htmlspecialchars($credit['customer_name'], ENT_QUOTES, 'UTF-8') ?>"
                      data-remaining="<?= $remaining ?>"
                    >
                      <i class="fa fa-money-bill"></i> Bayar
                    </button>
                  <?php endif; ?>
                  
                  <button onclick="viewPaymentHistory(<?= $credit['id'] ?>)" 
                          class="btn-secondary" style="font-size:12px;padding:5px 8px;">
                    <i class="fa fa-history"></i> Riwayat
                  </button>
                  
                  <?php if ($credit['status'] !== 'cancelled'): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Yakin batalkan kredit ini? Status pada Orders juga akan berubah menjadi Cancelled.')">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="credit_id" value="<?= $credit['id'] ?>">
                      <input type="hidden" name="status" value="cancelled">
                      <button type="submit" class="btn-warning" style="font-size:12px;padding:5px 8px;">
                        <i class="fa fa-ban"></i> Batal
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Pembayaran</h3>
      <span class="close" onclick="closePaymentModal()">&times;</span>
    </div>
    <form method="post">
      <?php csrf_field(); ?>
      <input type="hidden" name="action" value="add_payment">
      <input type="hidden" name="credit_id" id="modal_credit_id">
      
      <div class="form-group">
        <label>Customer</label>
        <input type="text" id="modal_customer" readonly style="background:#085c3a;color:#ffd700;">
      </div>
      
      <div class="form-group">
        <label>Sisa Hutang</label>
        <input type="text" id="modal_remaining" readonly style="background:#085c3a;color:#e74c3c;font-weight:bold;">
      </div>
      
      <div class="form-group">
        <label>Jumlah Pembayaran *</label>
        <input type="text" name="amount" id="modal_amount" required placeholder="0" oninput="formatRupiah(this)">
      </div>
      
      <div class="form-group">
        <label>Metode Pembayaran</label>
        <select name="payment_method" required>
          <option value="cash">Tunai</option>
          <option value="transfer">Transfer</option>
          <option value="qris">QRIS</option>
          <option value="other">Lainnya</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Keterangan</label>
        <textarea name="note" rows="3" placeholder="Keterangan pembayaran (opsional)"></textarea>
      </div>
      
      <div style="background:#085c3a; padding:10px; border-radius:6px; margin:15px 0; font-size:12px;">
        <i class="fa fa-info-circle" style="color:#3498db;"></i> 
        <strong>Info:</strong> Status kredit dan order akan otomatis tersinkronisasi setelah pembayaran.
      </div>
      
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closePaymentModal()" class="btn-secondary">Batal</button>
        <button type="submit" class="btn-success">
          <i class="fa fa-save"></i> Simpan Pembayaran
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Payment History Modal -->
<div id="historyModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">Riwayat Pembayaran</h3>
      <span class="close" onclick="closeHistoryModal()">&times;</span>
    </div>
    <div id="historyContent">
      <div style="text-align:center;padding:20px;">
        <i class="fa fa-spinner fa-spin"></i> Loading...
      </div>
    </div>
  </div>
</div>

<script>
function formatRupiah(input) {
  let value = input.value.replace(/[^\d]/g, '');
  input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function openPaymentModal(creditId, customerName, remaining) {
  document.getElementById('modal_credit_id').value = creditId;
  document.getElementById('modal_customer').value = customerName;
  document.getElementById('modal_remaining').value = 'Rp ' + remaining.toLocaleString('id-ID');
  document.getElementById('modal_amount').value = '';
  document.getElementById('paymentModal').style.display = 'block';
}

function closePaymentModal() {
  document.getElementById('paymentModal').style.display = 'none';
}

async function viewPaymentHistory(creditId) {
  document.getElementById('historyModal').style.display = 'block';

  try {
    const res = await fetch(`./credit_payment_api.php?credit_id=${creditId}`);

    if (!res.ok) {
      const errorText = await res.text();
      throw new Error(`HTTP ${res.status}: ${errorText}`);
    }

    const data = await res.json();

    if (!data.success) {
      throw new Error(data.error || 'Failed to fetch payment history');
    }

    if (!Array.isArray(data.payments)) {
      throw new Error('Invalid API response: payments not found');
    }

    let html;
    if (data.payments.length === 0) {
      html = `<div style="text-align:center;padding:40px;color:#ccc;">
                <i class="fa fa-inbox" style="font-size:64px;opacity:0.3;margin-bottom:20px;"></i>
                <p style="font-size:16px;margin:0;">Belum ada pembayaran</p>
                <small style="color:#999;">Riwayat pembayaran akan muncul di sini</small>
              </div>`;
    } else {
      html = '<div style="background:#085c3a; padding:15px; border-radius:8px; margin-bottom:15px; text-align:center; color:#ffd700; box-shadow:0 2px 8px rgba(0,0,0,0.2);">';
      html += '<div style="font-size:24px;font-weight:bold;margin-bottom:5px;">Rp ' + data.total_paid.toLocaleString('id-ID') + '</div>';
      html += '<div style="font-size:12px;color:#ccc;text-transform:uppercase;letter-spacing:1px;">Total Terbayar</div>';
      html += '<div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,0.2);">';
      html += '<i class="fa fa-receipt"></i> ' + data.total_payments + ' Transaksi Pembayaran';
      html += '</div></div>';
      
      html += '<table style="width:100%;border-collapse:collapse;color:#000;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
      html += '<thead><tr style="background:#064420;color:#ffd700;">';
      html += '<th style="padding:12px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:1px;"><i class="fa fa-calendar"></i> Tanggal</th>';
      html += '<th style="padding:12px;text-align:right;font-size:12px;text-transform:uppercase;letter-spacing:1px;"><i class="fa fa-money-bill"></i> Jumlah</th>';
      html += '<th style="padding:12px;text-align:center;font-size:12px;text-transform:uppercase;letter-spacing:1px;"><i class="fa fa-credit-card"></i> Metode</th>';
      html += '<th style="padding:12px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:1px;"><i class="fa fa-user"></i> Kasir</th>';
      html += '<th style="padding:12px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:1px;"><i class="fa fa-sticky-note"></i> Keterangan</th>';
      html += '</tr></thead><tbody>';

      data.payments.forEach((p, index) => {
        const dt = new Date(p.created_at);
        const date = dt.toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'});
        const time = dt.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});
        const rowBg = index % 2 === 0 ? '#f9f9f9' : '#fff';
        
        html += `<tr style="border-bottom:1px solid #e0e0e0;background:${rowBg};transition:background 0.2s;" onmouseover="this.style.background='#fffbf0'" onmouseout="this.style.background='${rowBg}'">`;
        
        html += `<td style="padding:12px;">
          <div style="font-weight:bold;color:#333;font-size:13px;">${date}</div>
          <small style="color:#999;font-size:11px;"><i class="fa fa-clock"></i> ${time}</small>
        </td>`;
        
        html += `<td style="padding:12px;text-align:right;">
          <div style="color:#27ae60;font-weight:bold;font-size:15px;">Rp ${parseFloat(p.amount).toLocaleString('id-ID')}</div>
        </td>`;
        
        html += `<td style="padding:12px;text-align:center;">`;
        const methodIcons = {
          'cash': '💵',
          'qris': '📱',
          'transfer': '🏦',
          'other': '💳'
        };
        const methodColors = {
          'cash': {bg: '#d4edda', text: '#155724'},
          'qris': {bg: '#d1ecf1', text: '#0c5460'},
          'transfer': {bg: '#fff3cd', text: '#856404'},
          'other': {bg: '#e2e3e5', text: '#383d41'}
        };
        const method = p.payment_method.toLowerCase();
        const icon = methodIcons[method] || '💳';
        const colors = methodColors[method] || methodColors['other'];
        
        html += `<span style="background:${colors.bg};color:${colors.text};padding:5px 12px;border-radius:15px;font-size:11px;font-weight:bold;white-space:nowrap;">${icon} ${p.payment_method.toUpperCase()}</span>`;
        html += `</td>`;
        
        html += `<td style="padding:12px;">
          <div style="font-weight:600;color:#333;font-size:13px;">${p.user_name || '-'}</div>
        </td>`;
        
        html += `<td style="padding:12px;">
          <small style="color:#666;font-style:${p.note ? 'normal' : 'italic'};">${p.note || 'Tidak ada catatan'}</small>
        </td>`;
        
        html += `</tr>`;
      });

      html += '</tbody></table>';
    }

    document.getElementById('historyContent').innerHTML = html;
    
  } catch (err) {
    document.getElementById('historyContent').innerHTML =
      `<div style="text-align:center;padding:40px;background:#fff5f5;border:2px solid #e74c3c;border-radius:12px;margin:20px;">
         <i class="fa fa-exclamation-triangle" style="font-size:64px;color:#e74c3c;margin-bottom:20px;"></i>
         <h3 style="color:#e74c3c;margin:0 0 10px 0;">Error Loading Payment History</h3>
         <p style="color:#666;margin:0;font-size:14px;">${err.message}</p>
         <button onclick="viewPaymentHistory(${creditId})" style="margin-top:20px;padding:10px 20px;background:#e74c3c;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">
           <i class="fa fa-refresh"></i> Coba Lagi
         </button>
       </div>`;
    console.error('viewPaymentHistory Error:', err);
  }
}

function closeHistoryModal() {
  document.getElementById('historyModal').style.display = 'none';
}

window.onclick = function(event) {
  const paymentModal = document.getElementById('paymentModal');
  const historyModal = document.getElementById('historyModal');
  
  if (event.target === paymentModal) {
    closePaymentModal();
  }
  if (event.target === historyModal) {
    closeHistoryModal();
  }
}

<?php if (isset($success)): ?>
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert.success');
  alerts.forEach(alert => {
    alert.innerHTML += ' <i class="fa fa-sync" style="margin-left:10px;"></i> Status Orders telah tersinkronisasi.';
  });
}, 1000);
<?php endif; ?>

// Event listener untuk tombol bayar (aman dari karakter khusus)
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.btn-pay-credit').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const creditId = parseInt(this.getAttribute('data-credit-id'));
      const customerName = this.getAttribute('data-customer-name');
      const remaining = parseFloat(this.getAttribute('data-remaining'));
      
      console.log('Opening payment modal:', creditId, customerName, remaining);
      openPaymentModal(creditId, customerName, remaining);
    });
  });
});

console.log('Manage Credits loaded - Safe from special characters in names');
</script>
</body>
</html>
