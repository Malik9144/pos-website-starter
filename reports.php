<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/permissions.php';
require_once __DIR__ . '/../src/lib/utils.php';

// PASTIKAN auth_required dipanggil sebelum output apapun!
// KASIR bisa akses reports tapi read-only
auth_required(['admin','superadmin','spv','kasir']);

require_once __DIR__ . '/../src/nav/sidebar.php';

$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to'] ?? date('Y-m-d');
$u      = me();
$branch = (int)($_GET['branch'] ?? ($u['role']==='superadmin' ? 0 : $u['branch_id']));

// Check if user is kasir - read-only mode
$isReadOnly = ($u['role'] === 'kasir');

if ($u['role'] === 'superadmin') {
    $branches = db()->query("SELECT * FROM branches ORDER BY name")->fetchAll();
} else {
    $st = db()->prepare("SELECT * FROM branches WHERE id=?");
    $st->execute([$u['branch_id']]);
    $branches = $st->fetchAll();
}

// PERBAIKAN: Tampilkan semua status kecuali cancelled
$where  = ' WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != "cancelled"';
$params = [$from, $to];
if ($branch > 0) {
    $where  .= ' AND o.branch_id = ?';
    $params[] = $branch;
}

// Query utama dengan LEFT JOIN untuk cash transactions
$sql = "SELECT o.*, u.name cashier, b.name branch, 
               ct.cash_given, ct.change_amount
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN branches b ON b.id = o.branch_id
        LEFT JOIN cash_transactions ct ON ct.order_id = o.id
        $where
        ORDER BY o.created_at DESC";
$st = db()->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

$sql_items = "SELECT oi.*, p.sku, p.name
              FROM order_items oi
              JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = ?";
$st_items = db()->prepare($sql_items);

// Query untuk credit payments
$sql_credit_payments = "SELECT cp.*, u.name as user_name 
                       FROM credit_payments cp
                       LEFT JOIN users u ON cp.user_id = u.id
                       WHERE cp.credit_id = (SELECT id FROM credits WHERE order_id = ? LIMIT 1)
                       ORDER BY cp.created_at DESC";
$st_credit_payments = db()->prepare($sql_credit_payments);

// Query untuk credit info
$sql_credit_info = "SELECT * FROM credits WHERE order_id = ? LIMIT 1";
$st_credit_info = db()->prepare($sql_credit_info);

// Hitung statistik
$grand_total = array_sum(array_column($orders, 'total'));
$total_cash_given = 0;
$total_change = 0;
$cash_orders = 0;
$qris_orders = 0;
$credit_orders = 0;
$total_ppn = 0; // Total PPN dari semua transaksi

foreach ($orders as $order) {
    // Tambahkan total PPN
    $total_ppn += isset($order['tax_value']) ? (int)$order['tax_value'] : 0;
    
    switch ($order['payment_method']) {
        case 'cash':
            $cash_orders++;
            $cash_given = (float)($order['cash_given'] ?? $order['total']);
            $change = (float)($order['change_amount'] ?? 0);
            $total_cash_given += $cash_given;
            $total_change += $change;
            break;
        case 'qris':
            $qris_orders++;
            break;
        case 'credit':
            $credit_orders++;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="assets/css/theme.css">
<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
<style>
body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; margin-left: 240px; }
.container { padding:30px; }
.card { background:#0b6e4f; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 5px 12px rgba(0,0,0,0.3); }
.label { font-weight:bold; margin-bottom:5px; display:block; }
.input, select { padding:6px; border:1px solid #ccc; border-radius:6px; width:100%; }
.btn { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; }
.btn.primary { background:#ffd700; color:#064420; font-weight:bold; }
.btn.danger { background:#e74c3c; color:#fff; }
.report-card {
  background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);
  color: #222; margin-bottom: 32px; padding: 24px 32px; max-width: 950px;
}
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}
.summary-item {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  text-align: center;
  border-left: 4px solid #007bff;
}
.summary-item.cash { border-left-color: #28a745; background: #d4edda; }
.summary-item.qris { border-left-color: #17a2b8; background: #d1ecf1; }
.summary-item.credit { border-left-color: #ffc107; background: #fff3cd; }
.summary-item.total { border-left-color: #6f42c1; background: #e2d9f3; }
.summary-item.ppn { border-left-color: #fd7e14; background: #ffe5cc; }
.summary-label {
  font-size: 11px;
  color: #666;
  margin-bottom: 5px;
  font-weight: bold;
  text-transform: uppercase;
}
.summary-value {
  font-size: 16px;
  font-weight: bold;
  color: #333;
}
.summary-count {
  font-size: 12px;
  color: #666;
  margin-top: 2px;
}
.order-header-row { display: flex; gap: 36px; font-size: 17px; font-weight: bold; margin-bottom: 14px; flex-wrap: wrap; }
.order-header-row div { min-width: 145px; }
.price { color: #0b6e4f; font-weight:bold; }

/* PPN Highlight */
.ppn-row {
  background-color: #fff3cd !important;
  font-weight: bold;
}
.dpp-row {
  background-color: #e7f3ff !important;
}

/* Status Badge */
.status-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: bold;
  text-transform: uppercase;
  margin-left: 8px;
}
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.paid { background: #d4edda; color: #155724; }
.status-badge.completed { background: #d1ecf1; color: #0c5460; }
.status-badge.credit { background: #fff3cd; color: #856404; }

/* Payment Method Badge */
.payment-method-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: bold;
  text-transform: uppercase;
  margin-left: 8px;
}
.payment-method-badge.cash { background: #d4edda; color: #155724; }
.payment-method-badge.qris { background: #d1ecf1; color: #0c5460; }
.payment-method-badge.credit { background: #fff3cd; color: #856404; }

.cash-details {
  background: #d4edda;
  border: 1px solid #c3e6cb;
  color: #155724;
  padding: 10px 12px;
  border-radius: 6px;
  margin: 10px 0;
  font-size: 13px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
}
.cash-detail-item {
  text-align: center;
}
.cash-detail-label {
  font-size: 10px;
  color: #0a4622;
  margin-bottom: 2px;
  font-weight: bold;
  text-transform: uppercase;
}
.cash-detail-value {
  font-size: 14px;
  font-weight: bold;
  color: #155724;
}

/* Credit Details */
.credit-details {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  color: #856404;
  padding: 12px;
  border-radius: 6px;
  margin: 10px 0;
  font-size: 13px;
}
.credit-header {
  font-weight: bold;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.credit-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
  margin-bottom: 10px;
}
.credit-summary-item {
  text-align: center;
}
.credit-summary-label {
  font-size: 10px;
  text-transform: uppercase;
  color: #856404;
  margin-bottom: 3px;
}
.credit-summary-value {
  font-weight: bold;
  font-size: 14px;
}
.credit-payments-table {
  background: #fff;
  padding: 10px;
  border-radius: 4px;
  margin-top: 10px;
}
.credit-payments-table table {
  width: 100%;
  margin-top: 8px;
  font-size: 12px;
  border-collapse: collapse;
  background: #fff;
}
.credit-payments-table th {
  background: #856404;
  color: #fff;
  padding: 8px;
  text-align: left;
  border-bottom: 2px solid #ddd;
  font-weight: bold;
}
.credit-payments-table td {
  padding: 8px;
  border-bottom: 1px solid #f0f0f0;
  color: #333;
  background: #fff;
}
.credit-payments-table tr:hover {
  background: #fffbf0;
}

.items-table { width: 100%; border-collapse: separate; border-spacing: 0 6px; margin-bottom: 12px; }
.items-table th { background: #215F46; color: #ffd700; font-size: 14px; border: none; padding: 8px 12px; border-radius: 6px 6px 0 0; }
.items-table td { background: #215F46; color: #fff; font-size: 15px; border: none; padding: 7px 12px; border-bottom: 2px solid #eafbe7; }
.total-row td { background: #27ae60; color: #fff; font-weight: bold; }
.order-action { margin-top: 12px; text-align: right; }
.btn-danger { background:#e74c3c; color:#fff; font-weight:bold; border:none; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:15px; transition:background 0.25s;}
.btn-danger:hover { background:#c0392b !important; }
.btn-print-thermal { background:#ff6b6b; color:#fff; font-weight:bold; border:none; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:15px; margin-right:10px; transition:background 0.25s; position:relative;}
.btn-print-thermal:hover { background:#ee5a52 !important; }
.btn-print-thermal::before { content:'🖨️ '; }
.btn-view { background:#3498db; color:#fff; font-weight:bold; border:none; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:15px; margin-right:10px; transition:background 0.25s;}
.btn-view:hover { background:#2980b9 !important; }
.alert { padding:10px; border-radius:6px; margin-bottom:15px; }
.alert.success { background:#27ae60; color:#fff; }
.alert.error { background:#e74c3c; color:#fff; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); }
.modal-content { background: #fefefe; margin: 5% auto; padding: 20px; border: none; border-radius: 8px; width: 90%; max-width: 400px; color: #333; }
.close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
.close:hover { color: #000; }
.receipt { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; white-space: pre-wrap; }

/* READ-ONLY MODE STYLES */
.read-only-notice {
  background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
  padding: 10px 15px; border-radius: 8px; margin-bottom: 20px;
  font-weight: bold; text-align: center;
}
.btn-danger.disabled, .btn-danger:disabled {
  background: #6c757d !important; cursor: not-allowed; opacity: 0.6;
}

/* Thermal Print Badge */
.thermal-print-badge {
  position: fixed;
  top: 15px;
  right: 20px;
  background: linear-gradient(135deg, #ff6b6b, #ee5a52);
  color: white;
  padding: 8px 12px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: bold;
  z-index: 9999;
  box-shadow: 0 3px 10px rgba(0,0,0,0.3);
  animation: pulse-badge 2s infinite;
}

@keyframes pulse-badge {
  0% { box-shadow: 0 3px 10px rgba(255, 107, 107, 0.3); }
  50% { box-shadow: 0 3px 15px rgba(255, 107, 107, 0.6); }
  100% { box-shadow: 0 3px 10px rgba(255, 107, 107, 0.3); }
}

@media (max-width: 800px) {
  body { margin-left: 70px; }
  .container { padding:18px;}
  .report-card { max-width:100%; padding:14px; }
  .modal-content { width: 95%; margin: 10% auto; }
  .summary-grid { grid-template-columns: repeat(2, 1fr); }
  .cash-details { grid-template-columns: 1fr; }
  .thermal-print-badge { top: 10px; right: 10px; }
}

@media print {
  .ppn-row, .dpp-row {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
}
</style>
</head>
<body>

<div class="container">

<?php if ($isReadOnly): ?>
<div class="read-only-notice">
  <i class="fas fa-eye"></i> Mode Tampilan Saja - Anda dapat melihat laporan tetapi tidak dapat menghapus atau mengedit data
</div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
  <div class="alert success"><i class="fa fa-check-circle"></i> <?= e($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['err'])): ?>
  <div class="alert error"><i class="fa fa-times-circle"></i> <?= e($_GET['err']) ?></div>
<?php endif; ?>

<!-- Filter -->
<div class="card">
  <h2><i class="fa fa-chart-line"></i> Filter Laporan</h2>
  <form method="get" class="flex" style="display:flex; gap:10px; flex-wrap:wrap;">
    <div>
      <label class="label">Dari</label>
      <input class="input" type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div>
      <label class="label">Sampai</label>
      <input class="input" type="date" name="to" value="<?= e($to) ?>">
    </div>
    <div>
      <label class="label">Cabang</label>
      <select class="input" name="branch">
        <?php if ($u['role']==='superadmin'): ?>
          <option value="0" <?= $branch===0?'selected':'' ?>>Semua Cabang</option>
        <?php endif; ?>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $branch==$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="align-self:flex-end;">
      <button class="btn primary">Terapkan</button>
      <a class="btn" href="reports_csv.php?from=<?= e($from) ?>&to=<?= e($to) ?>&branch=<?= e($branch) ?>">Export CSV</a>
    </div>
  </form>
</div>

<!-- Summary Dashboard -->
<?php if ($orders): ?>
<div class="report-card">
  <h3 style="margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px;">
    <i class="fa fa-chart-bar"></i> Dashboard Ringkasan Penjualan
  </h3>
  <div class="summary-grid">
    <div class="summary-item total">
      <div class="summary-label">Total Penjualan</div>
      <div class="summary-value">Rp <?= money($grand_total) ?></div>
      <div class="summary-count"><?= count($orders) ?> Transaksi</div>
    </div>
    
    <div class="summary-item cash">
      <div class="summary-label">💵 Pembayaran Tunai</div>
      <div class="summary-value">Rp <?= money($total_cash_given) ?></div>
      <div class="summary-count"><?= $cash_orders ?> Transaksi</div>
    </div>
    
    <div class="summary-item cash">
      <div class="summary-label">💰 Total Kembalian</div>
      <div class="summary-value">Rp <?= money($total_change) ?></div>
      <div class="summary-count">Dari <?= $cash_orders ?> Tunai</div>
    </div>
    
    <div class="summary-item qris">
      <div class="summary-label">📱 Pembayaran QRIS</div>
      <div class="summary-value"><?= $qris_orders ?> Transaksi</div>
      <div class="summary-count">Digital Payment</div>
    </div>
    
    <div class="summary-item credit">
      <div class="summary-label">💳 Pembayaran Kredit</div>
      <div class="summary-value"><?= $credit_orders ?> Transaksi</div>
      <div class="summary-count">Hutang Karyawan</div>
    </div>
    
    <!-- TAMBAHAN: Total PPN -->
    <div class="summary-item ppn">
      <div class="summary-label">📊 Total PPN (10%)</div>
      <div class="summary-value">Rp <?= money($total_ppn) ?></div>
      <div class="summary-count">Pajak Terkumpul</div>
    </div>
    
    <?php if ($total_cash_given > 0 && $u['role'] !== 'kasir'): ?>
    <div class="summary-item cash">
      <div class="summary-label">📊 Cash Flow Net</div>
      <div class="summary-value">Rp <?= money($total_cash_given - $total_change) ?></div>
      <div class="summary-count">Uang Masuk Bersih</div>
    </div>
    
    <?php endif; ?>
  </div>
</div>

<?php foreach ($orders as $o): ?>
<?php
  $st_items->execute([$o['id']]); $items = $st_items->fetchAll();
  $subtotal = 0;
  foreach($items as $it){
    $harga = $it['price']; $qty = $it['qty']; $disc = floatval($it['discount']);
    $sub_brg = $harga * $qty;
    $diskon_brg = round($sub_brg * $disc / 100);
    $subtotal += ($sub_brg - $diskon_brg);
  }
  
  // Service charge
  $svc_val = isset($o['service_value']) ? (int)$o['service_value'] : 0;
  $svc_pct = isset($o['service_percent']) ? (float)$o['service_percent'] : 0;
  
  // PPN 10% - Hitung DPP dan PPN
  $dpp = $subtotal + $svc_val; // DPP = Subtotal + Service
  $ppn_percent = 10;
  $ppn_calculated = round($dpp * $ppn_percent / 100);
  
  // Gunakan nilai dari database jika ada, atau gunakan perhitungan otomatis
  $tax_val = isset($o['tax_value']) && $o['tax_value'] > 0 ? (int)$o['tax_value'] : $ppn_calculated;
  $tax_pct = isset($o['tax_percent']) && $o['tax_percent'] > 0 ? (float)$o['tax_percent'] : $ppn_percent;
  
  // Data pembayaran tunai
  $cash_given = (float)($o['cash_given'] ?? 0);
  $change_amount = (float)($o['change_amount'] ?? 0);
  $is_cash_payment = ($o['payment_method'] === 'cash');
  
  // Data kredit
  $is_credit_payment = ($o['payment_method'] === 'credit');
  $credit_info = null;
  $credit_payments = [];
  
  if ($is_credit_payment) {
    $st_credit_info->execute([$o['id']]);
    $credit_info = $st_credit_info->fetch();
    
    if ($credit_info) {
      $st_credit_payments->execute([$o['id']]);
      $credit_payments = $st_credit_payments->fetchAll();
    }
  }
?>
<div class="report-card">
  <div class="order-header-row">
    <div><strong>ID:</strong> <?= e($o['id']) ?></div>
    <div><strong>Status:</strong> 
      <span class="status-badge <?= $o['status'] ?>">
        <?php
        switch($o['status']) {
          case 'pending': echo '⏳ UNPAID'; break;
          case 'paid': echo '✅ PAID'; break;
          case 'completed': echo '✔️ COMPLETED'; break;
          case 'credit': echo '💳 CREDIT'; break;
          default: echo strtoupper($o['status']); break;
        }
        ?>
      </span>
    </div>
    <div><strong>Tanggal:</strong> <?= e($o['created_at']) ?></div>
    <div><strong>Cabang:</strong> <?= e($o['branch']) ?></div>
    <div><strong>Kasir:</strong> <?= e($o['cashier']) ?></div>
    <div><strong>Total:</strong> <span class="price">Rp <?= money($o['total']) ?></span></div>
    <div>
      <strong>Metode:</strong> <?= e($o['payment_method']) ?>
      <span class="payment-method-badge <?= $o['payment_method'] ?>">
        <?php
        switch($o['payment_method']) {
          case 'cash': echo '💵 TUNAI'; break;
          case 'qris': echo '📱 QRIS'; break; 
          case 'credit': echo '💳 KREDIT'; break;
          default: echo strtoupper($o['payment_method']); break;
        }
        ?>
      </span>
    </div>
  </div>

  <!-- Detail Pembayaran Tunai -->
  <?php if ($is_cash_payment && $cash_given > 0): ?>
  <div class="cash-details">
    <div class="cash-detail-item">
      <div class="cash-detail-label">Uang Diterima</div>
      <div class="cash-detail-value">Rp <?= money($cash_given) ?></div>
    </div>
    <div class="cash-detail-item">
      <div class="cash-detail-label">Total Belanja</div>
      <div class="cash-detail-value">Rp <?= money($o['total']) ?></div>
    </div>
    <div class="cash-detail-item">
      <div class="cash-detail-label">Kembalian</div>
      <div class="cash-detail-value" style="color: #e67e22;">Rp <?= money($change_amount) ?></div>
    </div>
    <?php if ($cash_given >= $o['total']): ?>
    <div class="cash-detail-item">
      <div class="cash-detail-label">Cash Flow</div>
      <div class="cash-detail-value" style="color: #27ae60;">+Rp <?= money($cash_given - $change_amount) ?></div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Detail Pembayaran Kredit -->
  <?php if ($is_credit_payment && $credit_info): ?>
  <div class="credit-details">
    <div class="credit-header">
      <i class="fa fa-credit-card"></i> 
      Detail Kredit - <?= e($credit_info['employee_name']) ?>
    </div>
    <div class="credit-summary">
      <div class="credit-summary-item">
        <div class="credit-summary-label">Total Hutang</div>
        <div class="credit-summary-value">Rp <?= money($credit_info['total_amount']) ?></div>
      </div>
      <div class="credit-summary-item">
        <div class="credit-summary-label">Terbayar</div>
        <div class="credit-summary-value" style="color:#27ae60;">Rp <?= money($credit_info['paid_amount']) ?></div>
      </div>
      <div class="credit-summary-item">
        <div class="credit-summary-label">Sisa Hutang</div>
        <div class="credit-summary-value" style="color:#e74c3c;">Rp <?= money($credit_info['total_amount'] - $credit_info['paid_amount']) ?></div>
      </div>
      <div class="credit-summary-item">
        <div class="credit-summary-label">Status</div>
        <div class="credit-summary-value">
          <?php
          $status_labels = ['unpaid' => '❌ Belum Bayar', 'partial' => '⏳ Cicilan', 'paid' => '✅ Lunas'];
          echo $status_labels[$credit_info['status']] ?? $credit_info['status'];
          ?>
        </div>
      </div>
    </div>
    
    <?php if (!empty($credit_payments)): ?>
    <div class="credit-payments-table">
      <strong style="font-size:12px;">📋 Riwayat Pembayaran:</strong>
      <table>
        <tr>
          <th>Tanggal</th>
          <th style="text-align:right;">Jumlah</th>
          <th>Metode</th>
          <th>Kasir</th>
        </tr>
        <?php foreach($credit_payments as $cp): ?>
        <tr>
          <td><?= date('d/m/Y H:i', strtotime($cp['created_at'])) ?></td>
          <td style="text-align:right; font-weight:bold; color:#27ae60;">
            Rp <?= money($cp['amount']) ?>
          </td>
          <td><?= e($cp['payment_method']) ?></td>
          <td><?= e($cp['user_name']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php else: ?>
    <div style="background:#fff; padding:10px; border-radius:4px; margin-top:10px; text-align:center; color:#999; font-size:12px;">
      Belum ada pembayaran
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <table class="items-table">
    <thead>
      <tr>
        <th>SKU</th>
        <th>Nama Produk</th>
        <th>Jumlah Terjual</th>
        <th>Harga Satuan</th>
        <th>Diskon (%)</th>
        <th>Subtotal</th>
        <th>Stok Keluar</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it):
        $sub_brg = $it['price'] * $it['qty'];
        $diskon_brg = round($sub_brg * floatval($it['discount'])/100);
        $subtotal_item = $sub_brg - $diskon_brg;
      ?>
      <tr>
        <td><?= e($it['sku']) ?></td>
        <td><?= e($it['name']) ?></td>
        <td><?= e($it['qty']) ?></td>
        <td>Rp <?= money($it['price']) ?></td>
        <td><?= ($it['discount']>0 ? e($it['discount'].'%') : '-') ?></td>
        <td>Rp <?= money($subtotal_item) ?></td>
        <td><?= e($it['qty']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="6" style="text-align:right;"><strong>Subtotal:</strong></td>
        <td><strong>Rp <?= money($subtotal) ?></strong></td>
      </tr>
      <?php if ($svc_val > 0): ?>
      <tr>
        <td colspan="6" style="text-align:right;">Service <?= ($svc_pct ? "({$svc_pct}%)" : "") ?>:</td>
        <td>Rp <?= money($svc_val) ?></td>
      </tr>
      <?php endif; ?>
      <!-- DPP (Dasar Pengenaan Pajak) -->
      <tr class="dpp-row">
        <td colspan="6" style="text-align:right;"><strong>DPP (Dasar Pengenaan Pajak):</strong></td>
        <td><strong>Rp <?= money($dpp) ?></strong></td>
      </tr>
      <!-- PPN 10% -->
      <tr class="ppn-row">
        <td colspan="6" style="text-align:right;"><strong>PPN <?= ($tax_pct ? "({$tax_pct}%)" : "(10%)") ?>:</strong></td>
        <td><strong>Rp <?= money($tax_val) ?></strong></td>
      </tr>
      <tr class="total-row">
        <td colspan="6" style="text-align:right;"><strong>Total Order:</strong></td>
        <td><strong>Rp <?= money($o['total']) ?></strong></td>
      </tr>
    </tbody>
  </table>
  
  <div class="order-action" style="margin-top:9px;font-size:14px;color:#176e3c;">
    <?php if (!empty($o['customer_name'])): ?>
      Customer: <strong><?= e($o['customer_name']) ?></strong>&nbsp;  
    <?php endif; ?>
    <?php if (!empty($o['employee_name'])): ?>
      Karyawan: <strong><?= e($o['employee_name']) ?></strong>
    <?php endif; ?>
    <?php if ($tax_val > 0): ?>
      &nbsp;|&nbsp; <span style="color:#fd7e14;"><strong>PPN: Rp <?= money($tax_val) ?></strong></span>
    <?php endif; ?>
  </div>
  
  <div class="order-action">
    <div style="display: flex; gap: 5px; justify-content: flex-end; flex-wrap: wrap;">
      <!-- THERMAL PRINT BUTTON -->
      <button type="button" class="btn-print-thermal" onclick="window.open('receipt.php?id=<?= e($o['id']) ?>&type=cashier', '_blank', 'width=400,height=600')">
      <i class="fa fa-eye"></i> Print Thermal
      </button>
      
      <button type="button" class="btn-view" onclick="window.open('receipt.php?id=<?= e($o['id']) ?>&type=cashier', '_blank', 'width=400,height=600')">
      <i class="fa fa-eye"></i> Lihat Struk
      </button>
      
      <?php if (!$isReadOnly): ?>
      <form method="post" action="delete_order.php" id="form-del-<?= e($o['id']) ?>" style="display:inline;">
        <input type="hidden" name="id" value="<?= e($o['id']) ?>">
        <button type="button" class="btn-danger" onclick="confirmDelete(<?= e($o['id']) ?>,'<?= $u['role'] ?>')">
          <i class="fa fa-trash"></i> Hapus
        </button>
      </form>
      <?php else: ?>
      <button type="button" class="btn-danger disabled" disabled title="Aksi tidak tersedia untuk role kasir">
        <i class="fa fa-ban"></i> Hapus (Tidak Tersedia)
      </button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach ?>
<?php else: ?>
<div class="report-card">
  <div style="font-weight:bold; text-align:center; color:#e74c3c;">Tidak ada data</div>
</div>
<?php endif ?>
</div>

<!-- Modal Struk -->
<div id="receiptModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <h3>Preview Struk</h3>
    <div id="receiptContent" class="receipt"></div>
    <div style="text-align:center; margin-top:20px;">
      <button class="btn primary" onclick="printModalContent()">
        <i class="fa fa-print"></i> Cetak
      </button>
      <button class="btn" onclick="closeModal()">Tutup</button>
    </div>
  </div>
</div>

<script>
// CONTINUOUS THERMAL PRINT FUNCTION
function printThermalDirect(orderId) {
  console.log('Direct thermal print for order:', orderId);
  
  Swal.fire({
    title: '🖨️ Mencetak Thermal',
    html: '<div style="padding:20px;"><div style="font-size:48px;">🖨️</div><p>Mencetak struk thermal continuous...</p><small>Order #' + orderId + '</small></div>',
    allowOutsideClick: false,
    showConfirmButton: false,
    timer: 1500,
    didOpen: () => {
      Swal.showLoading();
    }
  });
  
  setTimeout(() => {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.style.width = '1px';
    iframe.style.height = '1px';
    iframe.src = `print_escpos.php?id=${orderId}&type=cashier`;
    
    document.body.appendChild(iframe);
    
    iframe.onload = function() {
      Swal.fire({
        icon: 'success',
        title: '✅ Print Berhasil',
        text: 'Struk thermal telah dicetak',
        timer: 2000,
        showConfirmButton: false
      });
      
      setTimeout(() => {
        if (document.body.contains(iframe)) {
          document.body.removeChild(iframe);
        }
      }, 5000);
    };
    
    iframe.onerror = function() {
      Swal.fire({
        icon: 'error',
        title: '❌ Print Gagal',
        text: 'Gagal mencetak struk thermal',
        confirmButtonText: 'OK'
      });
      
      if (document.body.contains(iframe)) {
        document.body.removeChild(iframe);
      }
    };
    
    setTimeout(() => {
      if (document.body.contains(iframe)) {
        document.body.removeChild(iframe);
      }
    }, 15000);
  }, 500);
}

<?php if (!$isReadOnly): ?>
function confirmDelete(orderId, role){
  if(role === 'spv'){
    Swal.fire({
      title: 'Konfirmasi Hapus',
      text: 'Masukkan password untuk hapus order #' + orderId,
      input: 'password',
      inputPlaceholder: 'Password',
      showCancelButton: true,
      confirmButtonText: 'Hapus',
      cancelButtonText: 'Batal',
      icon: 'warning',
      preConfirm: (pwd) => {
        if(!pwd) {
          Swal.showValidationMessage('Password wajib diisi');
          return false;
        }
        let form = document.getElementById('form-del-'+orderId);
        let hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'password';
        hidden.value = pwd;
        form.appendChild(hidden);
        disableDeleteButton(orderId);
        form.submit();
      }
    });
  } else {
    Swal.fire({
      title: 'Yakin?',
      text: "Hapus order #" + orderId + "?",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, hapus!',
      cancelButtonText: 'Batal'
    }).then((result) => {
      if (result.isConfirmed) {
        disableDeleteButton(orderId);
        document.getElementById('form-del-'+orderId).submit();
      }
    });
  }
}

function disableDeleteButton(orderId){
  const btn = document.querySelector('#form-del-'+orderId+' button');
  if(btn) btn.disabled = true;
}
<?php endif; ?>

function printReceipt(orderId) {
  Swal.fire({
    title: 'Pilih Cara Cetak',
    text: 'Pilih metode untuk mencetak struk',
    showDenyButton: true,
    showCancelButton: true,
    confirmButtonText: '<i class="fa fa-print"></i> Thermal Print',
    denyButtonText: '<i class="fa fa-eye"></i> Preview',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) printThermalDirect(orderId);
    else if (result.isDenied) window.open(`receipt.php?id=${orderId}&type=cashier`, '_blank', 'width=400,height=600');
  });
}

function openModal() {
  document.getElementById('receiptModal').style.display = "block";
}

function closeModal() {
  document.getElementById('receiptModal').style.display = "none";
}

window.addEventListener('click', function(event) {
  const modal = document.getElementById('receiptModal');
  if (event.target === modal) closeModal();
});

document.addEventListener('keydown', function(event) {
  if (event.key === "Escape") closeModal();
});

function printModalContent() {
  const receiptContent = document.getElementById('receiptContent').innerHTML;
  const printWindow = window.open('', '_blank', 'width=400,height=600');

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Struk Cetak</title>
      <style>
        body { font-family: 'Courier New', monospace; margin: 20px; font-size: 12px; }
        .receipt { width: 300px; margin: 0 auto; }
        @media print { body { margin: 0; } .no-print { display: none; } }
      </style>
    </head>
    <body>
      <div class="receipt">${receiptContent}</div>
      <div class="no-print" style="text-align:center;margin-top:20px;">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">❌ Tutup</button>
      </div>
    </body>
    </html>
  `);

  printWindow.document.close();
  printWindow.onload = () => {
    printWindow.focus();
    printWindow.print();
  };
}

console.log('Reports page loaded - PPN 10% integration complete');
</script>
</body>
</html>
