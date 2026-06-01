<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/utils.php';
require_once __DIR__ . '/../src/lib/permissions.php';
require_once __DIR__ . '/../src/lib/csrf.php';
require_once __DIR__ . '/../src/nav/sidebar.php';
auth_required(['admin','superadmin','spv','kasir']);
$u = auth_user();
$branch = $u['branch_id'];

// Ambil daftar karyawan
$karyawan = db()->query("SELECT id, name FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Ambil SEMUA produk sesuai branch untuk JavaScript filtering
$sql = 'SELECT p.*, IFNULL(sb.quantity,0) stock
        FROM products p
        LEFT JOIN stock_branch sb 
          ON sb.product_id = p.id AND sb.branch_id = ?
        WHERE p.active=1 AND p.branch_id = ?
        ORDER BY p.name ASC';
$st = db()->prepare($sql);
$st->execute([$branch, $branch]);
$products = $st->fetchAll(PDO::FETCH_ASSOC);

// Hitung produk per kategori untuk badge counter
$categoryCount = [
    'all' => count($products),
    'food' => 0,
    'drink' => 0,
    'other' => 0
];

foreach($products as $p) {
    $cat = $p['category'] ?? 'other';
    if(isset($categoryCount[$cat])) {
        $categoryCount[$cat]++;
    } else {
        $categoryCount['other']++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>POS - <?= e(APP_NAME) ?> - SOLUSI FINAL</title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="/pos-web-starter/assets/css/sweetalert2.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; overflow:hidden; }
.container { 
  margin-left:240px; 
  padding:20px; 
  display:grid; 
  grid-template-columns:1.3fr 1fr; 
  gap:30px; 
  height:100vh; 
  box-sizing:border-box;
}
.card { 
  background:#0b6e4f; 
  border-radius:12px; 
  padding:16px; 
  box-shadow:0 5px 12px rgba(0,0,0,.3); 
  display:flex;
  flex-direction:column;
}
.card h2 { color:#ffd700; margin-top:0; margin-bottom:16px; }
.card.products { 
  overflow:hidden; 
}
.search-section {
  margin-bottom:16px;
  flex-shrink:0;
}
.search-input {
  width:100%; 
  padding:12px 45px 12px 15px; 
  border-radius:8px; 
  border:2px solid #27ae60; 
  margin:4px 0 10px 0; 
  font-size:16px;
  background:#fff;
  color:#333;
  transition: border-color 0.3s;
}
.search-input:focus {
  outline:none;
  border-color:#ffd700;
  box-shadow: 0 0 0 3px rgba(255,215,0,0.2);
}
.search-icon {
  position:absolute;
  right:15px;
  top:50%;
  transform:translateY(-50%);
  color:#666;
  font-size:18px;
}
.category-filters {
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-bottom:12px;
}
.category-btn {
  padding:8px 16px;
  border:2px solid #27ae60;
  background:transparent;
  color:#27ae60;
  border-radius:20px;
  cursor:pointer;
  font-size:13px;
  font-weight:bold;
  transition:all 0.3s;
  display:flex;
  align-items:center;
  gap:6px;
}
.category-btn:hover {
  background:#27ae60;
  color:#fff;
  transform:translateY(-2px);
}
.category-btn.active {
  background:#ffd700;
  color:#064420;
  border-color:#ffd700;
}
.category-btn .badge {
  background:rgba(255,255,255,0.2);
  color:inherit;
  padding:2px 6px;
  border-radius:10px;
  font-size:11px;
  min-width:20px;
  text-align:center;
}
.category-btn.active .badge {
  background:rgba(6,68,32,0.2);
}
.search-results-info {
  color:#ffd700;
  font-size:12px;
  margin-top:8px;
  padding:4px 8px;
  background:rgba(255,215,0,0.1);
  border-radius:4px;
  display:none;
}
.product-scroll-container {
  flex:1;
  overflow-y:auto;
  padding-right:8px;
}
.product-scroll-container::-webkit-scrollbar {
  width:8px;
}
.product-scroll-container::-webkit-scrollbar-track {
  background:#064420;
  border-radius:4px;
}
.product-scroll-container::-webkit-scrollbar-thumb {
  background:#ffd700;
  border-radius:4px;
}
.product-scroll-container::-webkit-scrollbar-thumb:hover {
  background:#e6c200;
}
.product-grid { 
  display:grid; 
  grid-template-columns: repeat(auto-fill,minmax(95px,1fr)); 
  gap:8px; 
}
.product-card { 
  background:#085c3a; 
  border-radius:10px; 
  overflow:hidden; 
  box-shadow:0 3px 8px rgba(0,0,0,.3); 
  transition:.3s; 
  position:relative;
}
.product-card:hover { transform:translateY(-5px); }
.product-card.hidden { display:none; }
.product-card img { width:100%; height:65px; object-fit:cover; background:#fff; }
.product-card .p { padding:8px; }
.product-card .price { color:#ffd700; font-weight:bold; margin-top:5px; font-size:13px; }
.product-category-badge {
  position:absolute;
  top:6px;
  right:6px;
  padding:3px 6px;
  border-radius:10px;
  font-size:9px;
  font-weight:bold;
  text-transform:uppercase;
}
.category-food { background:#e74c3c; color:#fff; }
.category-drink { background:#3498db; color:#fff; }
.category-other { background:#95a5a6; color:#fff; }
.highlight {
  background-color:#ffd700;
  color:#064420;
  padding:1px 3px;
  border-radius:3px;
  font-weight:bold;
}
.no-results {
  text-align:center;
  padding:40px 20px;
  color:#ccc;
  grid-column: 1 / -1;
  display:none;
}
.no-results.show {
  display:block;
}

/* Button Styling */
.btn {
  padding: 10px 16px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  transition: all 0.2s ease;
  text-align: center;
  font-size: 13px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 42px;
  white-space: nowrap;
  position: relative;
  overflow: hidden;
}

.btn.small {
  font-size: 12px;
  padding: 8px 14px;
  min-height: 38px;
}

.btn.primary {
  background: linear-gradient(135deg, #007bff, #0056b3);
  color: #fff;
  box-shadow: 0 2px 4px rgba(0,123,255,0.3);
}

.btn.success {
  background: linear-gradient(135deg, #28a745, #218838);
  color: #fff;
  box-shadow: 0 2px 4px rgba(40,167,69,0.3);
}

.btn.warning {
  background: linear-gradient(135deg, #ffc107, #e0a800);
  color: #212529;
  box-shadow: 0 2px 4px rgba(255,193,7,0.3);
}

.btn.info {
  background: linear-gradient(135deg, #17a2b8, #138496);
  color: #fff;
  box-shadow: 0 2px 4px rgba(23,162,184,0.3);
}

.btn.secondary {
  background: linear-gradient(135deg, #6c757d, #545b62);
  color: #fff;
  box-shadow: 0 2px 4px rgba(108,117,125,0.3);
}

.btn.danger {
  background: linear-gradient(135deg, #dc3545, #c82333);
  color: #fff;
  box-shadow: 0 2px 4px rgba(220,53,69,0.3);
}

.btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}


.btn:active:not(:disabled) {
  transform: translateY(0);
}


.btn:disabled {
  background: linear-gradient(135deg, #6c757d, #545b62) !important;
  color: #fff !important;
  opacity: 0.65;
  cursor: not-allowed;
  transform: none !important;
  box-shadow: none !important;
}


.input { width:100%; padding:8px; border-radius:6px; border:none; margin:4px 0; color:#333; }
select.input { width:100%; padding:8px; border-radius:6px; border:none; margin:4px 0; color:#333; }


/* PERBAIKAN UTAMA: SELURUH CARD CART BISA SCROLL */
.card.cart {
  display: flex;
  flex-direction: column;
  /* TAMBAHKAN OVERFLOW PADA SELURUH CARD CART */
  overflow: hidden;
}


/* Cart Scroll Container - INI YANG PENTING! */
.cart-scroll-container {
  flex: 1;
  overflow-y: auto;
  padding-right: 8px;
  display: flex;
  flex-direction: column;
}


/* Custom Scrollbar untuk Cart */
.cart-scroll-container::-webkit-scrollbar {
  width: 8px;
}


.cart-scroll-container::-webkit-scrollbar-track {
  background: rgba(255,215,0,0.1);
  border-radius: 4px;
}


.cart-scroll-container::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #ffd700, #e6c200);
  border-radius: 4px;
  border: 1px solid #e6c200;
}


.cart-scroll-container::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #e6c200, #d4ac00);
}


/* Cart Items Section - TIDAK PERLU HEIGHT CONSTRAINT LAGI */
.cart-items-section {
  border: 2px solid rgba(255,215,0,0.4);
  border-radius: 8px;
  margin: 10px 0;
  background: rgba(255,255,255,0.95);
  /* HAPUS MAX-HEIGHT DAN OVERFLOW - BIARKAN MENGIKUTI CONTENT */
  flex-shrink: 0;
}


.total-rincian {
  flex-shrink: 0;
  margin-top: 10px;
  padding-top: 10px;
  border-top: 2px solid rgba(255,215,0,0.3);
  background: #0b6e4f;
}
.total-rincian p { margin:2px 0; line-height:1.5; font-size:14px; }
.input-taxsvc{ width:70px; padding:6px; border-radius:6px; border:none; color:#000; }
label { font-weight:bold; display:block; margin-bottom:5px; }
.credit-warning {background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:6px;border-radius:6px;margin:5px 0;font-size:12px;display:none;}
.credit-warning.show {display:block;}

/* Action Buttons Container */
.action-buttons-container {
  margin-top: 15px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
  background: rgba(255,215,0,0.05);
  border-radius: 8px;
  border: 1px solid rgba(255,215,0,0.2);
  flex-shrink: 0;
}

.management-actions {
  display: flex;
  gap: 8px;
  justify-content: space-between;
  align-items: center;
}

.separator-line {
  height: 1px;
  background: linear-gradient(to right, transparent, rgba(255,215,0,0.4), transparent);
  margin: 5px 0;
  position: relative;
}

.separator-line::before {
  content: '';
  position: absolute;
  top: -1px;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 3px;
  background: linear-gradient(to right, #ffd700, #e6c200, #ffd700);
  border-radius: 2px;
}

.payment-actions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  align-items: stretch;
}

.orders-badge {
  position: relative;
}

.orders-badge::after {
  content: attr(data-count);
  position: absolute;
  top: -8px;
  right: -8px;
  background: linear-gradient(135deg, #dc3545, #c82333);
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  z-index: 10;
  border: 2px solid #0b6e4f;
  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.print-status-container {
  position: fixed;
  top: 15px;
  right: 15px;
  z-index: 9999;
}

.print-status {
  background: linear-gradient(135deg, #28a745, #218838);
  color: white;
  padding: 8px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: bold;
  margin-bottom: 5px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  animation: slideInRight 0.3s ease;
  border: 1px solid rgba(255,255,255,0.2);
}

.print-status.error {
  background: linear-gradient(135deg, #dc3545, #c82333);
}

.print-status.info {
  background: linear-gradient(135deg, #17a2b8, #138496);
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* Responsive adjustments */
@media (max-width: 900px) {
  .container { 
    margin-left:70px; 
    grid-template-columns:1fr; 
    height:auto;
    overflow-y:auto;
    gap:16px;
  }
  body { overflow:auto; }
  .card.products, .card.cart {
    max-height:50vh;
  }
  .category-filters {
    justify-content:center;
  }
  .category-btn {
    font-size:12px;
    padding:6px 12px;
  }
  .product-grid { 
    grid-template-columns: repeat(auto-fill,minmax(85px,1fr)); 
    gap:6px; 
  }
  .management-actions {
    flex-direction: column;
    gap: 8px;
  }
  
  .payment-actions {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .btn {
    font-size: 12px;
    padding: 8px 12px;
    min-height: 40px;
  }
  
  .btn.small {
    font-size: 11px;
    padding: 6px 10px;
    min-height: 36px;
  }
}

@media (min-width: 1400px) {
  .payment-actions {
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
  }
}
</style>
</head>
<body>

<div class="container">
  <!-- Katalog Produk -->
  <div class="card products">
    <h2><i class="fa fa-box"></i> Daftar Produk</h2>
    <div class="search-section">
      <div style="position:relative;">
        <input 
          class="search-input" 
          id="searchInput" 
          placeholder="Ketik untuk mencari produk atau SKU..." 
          autocomplete="off"
        >
        <i class="fa fa-search search-icon"></i>
      </div>
      
      <div class="category-filters">
        <button class="category-btn active" data-category="all" onclick="filterByCategory('all')">
          <i class="fa fa-th"></i> Semua <span class="badge"><?= $categoryCount['all'] ?></span>
        </button>
        <button class="category-btn" data-category="food" onclick="filterByCategory('food')">
          <i class="fa fa-utensils"></i> Makanan <span class="badge"><?= $categoryCount['food'] ?></span>
        </button>
        <button class="category-btn" data-category="drink" onclick="filterByCategory('drink')">
          <i class="fa fa-glass-water"></i> Minuman <span class="badge"><?= $categoryCount['drink'] ?></span>
        </button>
        <button class="category-btn" data-category="other" onclick="filterByCategory('other')">
          <i class="fa fa-box"></i> Lainnya <span class="badge"><?= $categoryCount['other'] ?></span>
        </button>
      </div>
      
      <div class="search-results-info" id="searchInfo"></div>
    </div>
    <div class="product-scroll-container">
      <div class="product-grid" id="productGrid">
        <?php foreach($products as $p): 
          $category = $p['category'] ?? 'other';
          $categoryClass = "category-{$category}";
          $categoryNames = ['food' => 'Makanan', 'drink' => 'Minuman', 'other' => 'Lainnya'];
          $categoryName = $categoryNames[$category] ?? 'Lainnya';
        ?>
        <div class="product-card" 
             data-id="<?= $p['id'] ?>" 
             data-name="<?= strtolower(e($p['name'])) ?>" 
             data-sku="<?= strtolower(e($p['sku'])) ?>"
             data-category="<?= $category ?>">
          <img src="uploads/products/<?= e($p['image'] ?: 'no.jpg') ?>" onerror="this.src='https://picsum.photos/seed/<?= e($p['id']) ?>/400/300'">
          <div class="product-category-badge <?= $categoryClass ?>"><?= $categoryName ?></div>
          <div class="p">
            <div class="product-name"><strong style="font-size:12px;"><?= e($p['name']) ?></strong></div>
            <div class="small product-sku" style="font-size:10px;">SKU: <?= e($p['sku']) ?></div>
            <div class="price">Rp <?= money($p['price']) ?></div>
            <div class="small stock-display" style="color:<?= $p['stock'] <= 0 ? '#e74c3c' : '#ffd700' ?>; font-size:10px;">
              Stok: <?= (int)$p['stock'] ?>
            </div>
            <button class="btn small primary"
                    onclick="addItem(<?= (int)$p['id'] ?>,'<?= e(str_replace("'", "\\'", $p['name'])) ?>',<?= (int)$p['price'] ?>);return false;"
                    <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
              <i class="fa fa-plus"></i> <?= $p['stock'] <= 0 ? 'Habis' : 'Tambah' ?>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="no-results" id="noResults">
          <i class="fa fa-search" style="font-size:48px; margin-bottom:15px; opacity:0.5;"></i>
          <p>Tidak ada produk yang ditemukan</p>
          <p style="font-size:12px; color:#999;">Coba kata kunci atau kategori yang berbeda</p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Keranjang -->
  <div class="card cart">
    <h2><i class="fa fa-shopping-cart"></i> Keranjang</h2>
    
    <!-- TAMBAHAN: Input Tanggal Transaksi -->
    <div class="cart-scroll-container">
      <label for="transaction_date" style="margin-bottom:10px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
          <i class="fa fa-calendar-alt" style="color:#ffd700;"></i>
          <strong style="color:#ffd700;">📅 Tanggal Transaksi</strong>
        </div>
        <input class="input" type="date" id="transaction_date" 
               value="<?= date('Y-m-d') ?>" 
               max="<?= date('Y-m-d') ?>" 
               style="font-weight:bold;color:#064420;border:2px solid #ffd700;">
        <small style="color:#ffd700;font-size:11px;display:block;margin-top:4px;">
          <i class="fa fa-info-circle"></i> Gunakan untuk input transaksi mundur (backdate)
        </small>
      </label>
      
      <label for="cust_name">Nama Customer
        <input class="input" id="cust_name" placeholder="Nama customer (opsional)">
      </label>
      
      <label for="employee_name">Nama Karyawan (untuk kredit)
        <select class="input" id="employee_name" onchange="toggleCreditWarning()">
          <option value="">--Pilih Karyawan--</option>
          <?php foreach($karyawan as $emp): ?>
            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="credit-warning" id="creditWarning">
          ⚠️ Pilih karyawan dulu untuk pembayaran kredit
        </div>
      </label>

      <div style="display:flex;gap:10px;margin-bottom:10px;margin-top:10px;">
        <label style="flex:1;font-weight:bold;">
          <input type="radio" name="order_type" value="dinein" checked> Dine In
        </label>
        <label style="flex:1;font-weight:bold;">
          <input type="radio" name="order_type" value="takeaway"> Take Away
        </label>
        <label style="flex:1;font-weight:bold;min-width:90px;">
          No. Meja
          <input class="input" type="text" id="table_no" placeholder="Nomor Meja (Jika Dine In)">
        </label>
      </div>
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:7px;">
        <label>PPN/Tax (%) <input class="input-taxsvc" type="number" id="tax" value="0" min="0" max="100"></label>
        <label>Service (%) <input class="input-taxsvc" type="number" id="svc" value="0" min="0" max="100"></label>
      </div>
      
      <!-- Cart Items -->
      <div class="cart-items-section">
        <div id="cart"></div>
      </div>
      
      <!-- Total -->
      <div class="total-rincian" id="totalBox"></div>

      <!-- Action Buttons -->
      <div class="action-buttons-container">
        <div class="management-actions">
          <button class="btn secondary small" onclick="emptyCart()" title="Kosongkan keranjang">
            <i class="fa fa-trash"></i> Kosongkan
          </button>
          <button class="btn info small" onclick="window.location.href='orders_list.php'" title="Lihat orders pending" id="ordersBtn">
            <i class="fa fa-list-alt"></i> Orders Pending
          </button>
        </div>
        
        <div class="separator-line"></div>
        
        <div class="payment-actions" id="paymentButtons">
          <button class="btn success" onclick="pay('cash')" id="btnCash" title="Bayar dengan tunai">
            <i class="fa fa-money-bill-wave"></i> Tunai
          </button>
          <button class="btn success" onclick="pay('qris')" id="btnQris" title="Bayar dengan QRIS">
            <i class="fa fa-qrcode"></i> QRIS
          </button>
          <button class="btn warning" onclick="pay('credit')" id="btnCredit" disabled title="Bayar dengan kredit karyawan">
            <i class="fa fa-credit-card"></i> Kredit
          </button>
          <button class="btn primary" onclick="createOpenBill()" title="Buat order tanpa pembayaran">
            <i class="fa fa-file-invoice"></i> Open Bill
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
<script>
console.log('POS Script loaded - SOLUSI FINAL: FULL CART SCROLL');

let cart = JSON.parse(localStorage.getItem('cart')||'[]');
let isProcessing = false;
let searchTimeout;
let currentCategory = 'all';
let currentSearchQuery = '';

function showPrintStatus(message, type = 'success') {
  const container = document.querySelector('.print-status-container') || createPrintStatusContainer();
  
  const printStatus = document.createElement('div');
  printStatus.className = `print-status ${type}`;
  printStatus.textContent = message;
  
  container.appendChild(printStatus);
  
  setTimeout(() => {
    if (container.contains(printStatus)) {
      container.removeChild(printStatus);
    }
  }, 3000);
}

function createPrintStatusContainer() {
  const container = document.createElement('div');
  container.className = 'print-status-container';
  document.body.appendChild(container);
  return container;
}

function directThermalPrint(orderId, type = 'cashier') {
  console.log('Direct thermal print:', orderId, type);
  
  showPrintStatus('🖨️ Mencetak struk thermal...', 'info');
  
  const iframe = document.createElement('iframe');
  iframe.style.display = 'none';
  iframe.style.width = '1px';
  iframe.style.height = '1px';
  iframe.src = `print_escpos.php?id=${orderId}&type=${type}`;
  
  document.body.appendChild(iframe);
  
  iframe.onload = function() {
    setTimeout(() => {
      showPrintStatus('✅ Struk thermal berhasil dicetak!', 'success');
      document.body.removeChild(iframe);
    }, 1000);
  };
  
  iframe.onerror = function() {
    showPrintStatus('❌ Gagal mencetak struk thermal', 'error');
    document.body.removeChild(iframe);
  };
  
  setTimeout(() => {
    if (document.body.contains(iframe)) {
      document.body.removeChild(iframe);
    }
  }, 10000);
}

function toggleCreditWarning(){
  const emp=document.getElementById('employee_name');
  const warn=document.getElementById('creditWarning');
  const btnCredit=document.getElementById('btnCredit');
  if(emp.value){
    warn.classList.remove('show');
    btnCredit.disabled=false;
  } else {
    warn.classList.add('show');
    btnCredit.disabled=true;
  }
}

async function pay(method) {
  console.log('🎯 Payment button clicked:', method);
  
  if(cart.length === 0) {
    Swal.fire('Keranjang kosong', '', 'warning');
    return;
  }
  if(isProcessing) return;

  if(method === 'credit') {
    if(!document.getElementById('employee_name').value) {
      Swal.fire('Harap pilih karyawan untuk pembayaran kredit', '', 'warning');
      return;
    }
  }
  
  await processDirectPayment(method);
}

async function createOpenBill(){
  console.log('🎯 Open Bill button clicked');
  
  if(cart.length === 0) {
    Swal.fire('Keranjang kosong', '', 'warning');
    return;
  }
  if(isProcessing) return;

  const payload={
    items: cart,
    customer_name: document.getElementById('cust_name').value || '',
    tax: parseFloat(document.getElementById('tax').value) || 0,
    service: parseFloat(document.getElementById('svc').value) || 0,
    order_type: document.querySelector('input[name="order_type"]:checked').value,
    table_no: document.getElementById('table_no').value || '',
    transaction_type: 'open_bill',
    transaction_date: document.getElementById('transaction_date').value
  };
  
  try{
    isProcessing = true;
    Swal.fire({title:'Membuat open bill...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    
    const res = await fetch('pos_create_order.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(payload)
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.message || 'Error membuat order');
    
    Swal.close();
    await Swal.fire({
      icon: 'success',
      title: 'Open Bill berhasil dibuat!',
      html: `<div><strong>Order ID:</strong> #${data.order_id}</div><div style="margin-top:10px;"><strong>Total:</strong> Rp ${data.total.toLocaleString('id-ID')}</div>`,
      confirmButtonText: 'Lihat Orders',
      showCancelButton: true,
      cancelButtonText: 'Buat Lagi',
    }).then((result) => {
      if (result.isConfirmed) window.location.href = 'orders_list.php';
    });
    
    resetCart();
  }catch(e){
    Swal.close();
    Swal.fire('Gagal', e.message, 'error');
  }finally{
    isProcessing = false;
  }
}

async function processDirectPayment(method){
  if(cart.length===0){
    Swal.fire('Keranjang kosong','','warning');
    return;
  }
  if(isProcessing) return;

  let bruto = 0;
  cart.forEach(it => {
    const itemSubtotal = it.price * it.qty;
    const discount = itemSubtotal * ((it.disc || 0) / 100);
    bruto += (itemSubtotal - discount);
  });

  const pct_tax=parseFloat(document.getElementById('tax').value)||0;
  const pct_svc=parseFloat(document.getElementById('svc').value)||0;
  const nilai_tax=Math.round(bruto*pct_tax/100);
  const nilai_svc=Math.round(bruto*pct_svc/100);
  const total_all=bruto+nilai_tax+nilai_svc;

  let cashGiven = 0;
  if(method==='cash'){
    const { value: uangDiberikan } = await Swal.fire({
      title: 'Masukkan Uang Diberikan',
      input: 'text',
      inputLabel: `Total harus dibayar: Rp ${total_all.toLocaleString('id-ID')}`,
      inputPlaceholder: 'Masukkan jumlah uang...',
      showCancelButton: true,
      inputValidator: (value) => {
        const parsed = parseInt(value.replace(/\D/g, ''));
        if(!parsed || parsed < total_all){
          return `Uang kurang! Minimal Rp ${total_all.toLocaleString('id-ID')}`;
        }
      }
    });
    if(!uangDiberikan) return;
    cashGiven = parseInt(uangDiberikan.replace(/\D/g, ''));
    const kembalian = cashGiven - total_all;
    const { isConfirmed } = await Swal.fire({
      icon: 'info',
      title: 'Konfirmasi Pembayaran',
      html: `
        <p>Total belanja: Rp ${total_all.toLocaleString('id-ID')}</p>
        <p>Uang diterima: Rp ${cashGiven.toLocaleString('id-ID')}</p>
        <p style="font-size:18px;margin-top:10px;color:#28a745;"><b>Kembalian: Rp ${kembalian.toLocaleString('id-ID')}</b></p>
      `,
      confirmButtonText: 'Proses Pembayaran',
      showCancelButton: true
    });
    if(!isConfirmed) return processDirectPayment(method);
  }

  try {
    isProcessing = true;
    Swal.fire({title:'Memproses pembayaran...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});

    let customerName = document.getElementById('cust_name').value || '';
    if(method === 'credit') {
      const selectedEmployee = document.querySelector('#employee_name option:checked');
      customerName = selectedEmployee.text;
    }

    const payload = {
      items: cart,
      method,
      customer_name: customerName,
      tax: pct_tax,
      service: pct_svc,
      order_type: document.querySelector('input[name="order_type"]:checked').value,
      table_no: document.getElementById('table_no').value || '',
      cash_given: cashGiven,
      employee_id: (method === 'credit') ? parseInt(document.getElementById('employee_name').value) : null,
      transaction_type: 'direct',
      transaction_date: document.getElementById('transaction_date').value
    };

    const res = await fetch('pos_create_order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if(!res.ok) throw new Error(data.message || 'Error proses pembayaran');

    let successHtml = `<div><strong>Order ID:</strong> #${data.order_id}</div>`;
    if(method === 'cash' && cashGiven > 0){
      const kembalian = cashGiven - total_all;
      successHtml += `<div style="margin-top:10px;padding:10px;background:#d4edda;border-radius:6px;color:#155724;">
        <div><strong>Uang Diterima:</strong> Rp ${cashGiven.toLocaleString('id-ID')}</div>
        <div style="font-size:18px;"><b>Kembalian: Rp ${kembalian.toLocaleString('id-ID')}</b></div>
      </div>`;
    }
    if(method === 'credit'){
      successHtml += `<div style="margin-top:10px;padding:10px;background:#fff3cd;border-radius:6px;color:#856404;">
        <i class="fa fa-credit-card"></i> <strong>Kredit berhasil dicatat</strong><br>
        Customer: ${customerName}
      </div>`;
    }

    await Swal.fire({
      icon: 'success',
      title: 'Pembayaran berhasil!',
      html: successHtml + '<div style="margin-top:10px;padding:8px;background:#17a2b8;color:#fff;border-radius:6px;font-size:12px;"><i class="fa fa-print"></i> Struk thermal akan dicetak otomatis</div>',
      confirmButtonText: 'OK',
      timer: 2500
    });

    resetCart();
    
    setTimeout(() => directThermalPrint(data.order_id, 'cashier'), 500);

  } catch(e) {
    Swal.fire('Gagal', e.message, 'error');
  } finally {
    isProcessing = false;
  }
}

async function loadPendingOrdersCount() {
  try {
    const res = await fetch('get_pending_count.php');
    const data = await res.json();
    if (data.success && data.count > 0) {
      const ordersBtn = document.getElementById('ordersBtn');
      ordersBtn.classList.add('orders-badge');
      ordersBtn.setAttribute('data-count', data.count);
    }
  } catch(e) {
    console.log('Could not load pending orders count');
  }
}

function resetCart() {
  cart = [];
  localStorage.removeItem('cart');
  document.getElementById('cust_name').value = '';
  document.getElementById('employee_name').value = '';
  document.getElementById('table_no').value = '';
  document.getElementById('transaction_date').value = '<?= date('Y-m-d') ?>'; // reset transaksi tanggal ke hari ini
  toggleCreditWarning();
  render();
}

function save(){
  localStorage.setItem('cart',JSON.stringify(cart));
  render();
}

function addItem(id, name, price){
  console.log('🛒 SOLUSI FINAL - Adding item:', {id, name, price});
  
  if(isProcessing) {
    console.log('❌ Processing blocked');
    return;
  }
  
  let existingItem = cart.find(i => i.id == id);
  if(existingItem) {
    existingItem.qty++;
    console.log('➕ Item quantity increased:', existingItem);
  } else {
    const newItem = {id, name, price, qty:1, disc:0};
    cart.push(newItem);
    console.log('🆕 New item added:', newItem);
  }
  
  save();
  debugCart();
}

function setQty(i, qty) {
  if(isProcessing) return;
  
  const newQty = Math.max(1, parseInt(qty) || 1);
  cart[i].qty = newQty;
  console.log(`📊 Updated quantity for item ${i}:`, cart[i]);
  save();
}

function setDisc(i, disc) {
  if(isProcessing) return;
  
  const newDisc = Math.max(0, Math.min(100, parseInt(disc) || 0));
  cart[i].disc = newDisc;
  console.log(`🏷️ Updated discount for item ${i}:`, cart[i]);
  save();
}

function removeItem(i) {
  if(isProcessing) return;
  
  const itemName = cart[i].name;
  console.log(`🗑️ Removing item: ${itemName}`);
  cart.splice(i, 1);
  save();
  
  showPrintStatus(`${itemName} dihapus dari keranjang`, 'info');
}

function emptyCart(){
  cart=[];
  save();
  Swal.fire('Keranjang dikosongkan','','success');
}

function subtotal(i){
  let it=cart[i];
  let subtot=it.price*it.qty;
  let diskon=Math.round(subtot*(parseFloat(it.disc||0)/100));
  return subtot-diskon;
}

function totalBruto(){
  return cart.reduce((total,it)=>total+subtotal(cart.indexOf(it)),0);
}

function render(){
  console.log('🎨 SOLUSI FINAL - FULL CART SCROLL, cart items:', cart.length);
  let el = document.getElementById('cart');
  
  if(cart.length == 0){
    el.innerHTML = '<div style="padding:20px;text-align:center;color:#ffd700;background:rgba(255,215,0,0.1);border-radius:6px;margin:10px;">Keranjang kosong - Pilih produk untuk menambah</div>';
    document.getElementById('totalBox').innerHTML='';
    return;
  }
  
  let html = '<div style="padding:10px;">';
  
  cart.forEach((item, i) => {
    const itemSubtotal = subtotal(i);
    html += `
      <div style="background:#f9f9f9;padding:12px;margin-bottom:10px;border-radius:6px;border:1px solid #ddd;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <strong style="color:#064420;font-size:14px;">${item.name}</strong>
          <button onclick="removeItem(${i})" style="background:#e74c3c;color:white;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;display:flex;align-items:center;justify-content:center;" title="Hapus item">×</button>
        </div>
        <div style="display:flex;gap:15px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
          <div style="font-size:12px;color:#666;">
            <strong>Qty:</strong> <input type="number" value="${item.qty}" min="1" onchange="setQty(${i}, this.value)" style="width:50px;padding:4px;text-align:center;border:1px solid #ddd;border-radius:4px;">
          </div>
          <div style="font-size:12px;color:#666;"><strong>Harga:</strong> Rp ${item.price.toLocaleString('id-ID')}</div>
          <div style="font-size:12px;color:#666;">
            <strong>Disc:</strong> <input type="number" value="${item.disc}" min="0" max="100" onchange="setDisc(${i}, this.value)" style="width:40px;padding:4px;text-align:center;border:1px solid #ddd;border-radius:4px;">%
          </div>
          <div style="font-weight:bold;color:#28a745;font-size:13px;"><strong>Rp ${itemSubtotal.toLocaleString('id-ID')}</strong></div>
        </div>
      </div>
    `;
  });
  
  html += '</div>';
  el.innerHTML = html;
  showTotalBox();
  
  console.log('✅ SOLUSI FINAL - Cart rendered with full scroll support');
}

function showTotalBox(){
  let bruto=totalBruto();
  let pct_tax=parseFloat(document.getElementById('tax').value)||0;
  let pct_svc=parseFloat(document.getElementById('svc').value)||0;
  let nilai_tax=Math.round(bruto*pct_tax/100);
  let nilai_svc=Math.round(bruto*pct_svc/100);
  let tot_all=bruto+nilai_tax+nilai_svc;
  let html=`<p>Subtotal: <b>Rp ${bruto.toLocaleString('id-ID')}</b></p>
  <p>Pajak: <b>Rp ${nilai_tax.toLocaleString('id-ID')}</b> (${pct_tax}%)</p>
  <p>Layanan: <b>Rp ${nilai_svc.toLocaleString('id-ID')}</b> (${pct_svc}%)</p>
  <p style="font-size:16px;margin-top:5px;">Total: <b>Rp ${tot_all.toLocaleString('id-ID')}</b></p>`;
  document.getElementById('totalBox').innerHTML=html;
}

function debugCart() {
  console.log('=== SOLUSI FINAL DEBUG ===');
  console.log('Current cart state:', cart);
  console.log('Cart length:', cart.length);
  console.log('LocalStorage cart:', localStorage.getItem('cart'));
  console.log('Cart element:', document.getElementById('cart'));
  console.log('Payment buttons:', document.querySelectorAll('.payment-actions button'));
  console.log('===========================');
}

function filterByCategory(category) {
  currentCategory = category;
  
  document.querySelectorAll('.category-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  document.querySelector(`[data-category="${category}"]`).classList.add('active');
  
  applyFilters();
}

function applyFilters() {
  const productCards = document.querySelectorAll('.product-card:not(#noResults)');
  const searchInfo = document.getElementById('searchInfo');
  const noResults = document.getElementById('noResults');
  let visibleCount = 0;
  
  productCards.forEach(card => {
    const name = card.dataset.name || '';
    const sku = card.dataset.sku || '';
    const category = card.dataset.category || 'other';
    
    const categoryMatch = currentCategory === 'all' || category === currentCategory;
    const searchMatch = currentSearchQuery === '' || 
                       name.includes(currentSearchQuery.toLowerCase()) || 
                       sku.includes(currentSearchQuery.toLowerCase());
    
    if (categoryMatch && searchMatch) {
      card.classList.remove('hidden');
      if (currentSearchQuery !== '') {
        highlightText(card, currentSearchQuery);
      } else {
        clearHighlights(card);
      }
      visibleCount++;
    } else {
      card.classList.add('hidden');
      clearHighlights(card);
    }
  });
  
  if (currentSearchQuery !== '' || currentCategory !== 'all') {
    let infoText = `Ditemukan ${visibleCount} produk`;
    if (currentCategory !== 'all') {
      const categoryNames = {'food': 'Makanan', 'drink': 'Minuman', 'other': 'Lainnya'};
      infoText += ` kategori ${categoryNames[currentCategory]}`;
    }
    if (currentSearchQuery !== '') {
      infoText += ` untuk "${currentSearchQuery}"`;
    }
    searchInfo.textContent = infoText;
    searchInfo.style.display = 'block';
  } else {
    searchInfo.style.display = 'none';
  }
  
  if (visibleCount === 0) {
    noResults.classList.add('show');
  } else {
    noResults.classList.remove('show');
  }
}

function initializeSearch() {
  const searchInput = document.getElementById('searchInput');
  
  if (!searchInput) {
    console.error('Search input not found!');
    return;
  }
  
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      currentSearchQuery = this.value.trim();
      applyFilters();
    }, 150);
  });
}

function highlightText(card, query) {
  const nameEl = card.querySelector('.product-name');
  const skuEl = card.querySelector('.product-sku');
  
  if (nameEl) {
    const originalName = nameEl.textContent;
    nameEl.innerHTML = highlightMatch(originalName, query);
  }
  
  if (skuEl) {
    const originalSku = skuEl.textContent;
    skuEl.innerHTML = highlightMatch(originalSku, query);
  }
}

function clearHighlights(card) {
  const nameEl = card.querySelector('.product-name');
  const skuEl = card.querySelector('.product-sku');
  
  if (nameEl) {
    const text = nameEl.textContent;
    nameEl.innerHTML = `<strong style="font-size:12px;">${text}</strong>`;
  }
  
  if (skuEl) {
    const text = skuEl.textContent;
    skuEl.innerHTML = text;
  }
}

function highlightMatch(text, query) {
  const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
  return text.replace(regex, '<span class="highlight">$1</span>');
}

function escapeRegExp(string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

document.getElementById('tax').oninput = showTotalBox;
document.getElementById('svc').oninput = showTotalBox;

document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 SOLUSI FINAL - DOM loaded, initializing...');
  toggleCreditWarning();
  render();
  initializeSearch();
  loadPendingOrdersCount();
  debugCart();
  console.log('✅ SOLUSI FINAL - POS initialization complete WITH FULL CART SCROLL');
});
</script>
</body>
</html>
