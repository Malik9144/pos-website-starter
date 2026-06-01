<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/utils.php';
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['admin','superadmin','spv','kasir']);
$u = auth_user();
$branch = $u['branch_id'];

// Ambil daftar orders dengan status 'pending'
$sql = "SELECT o.id, o.branch_id, o.user_id, o.order_type, o.table_no, o.customer_name, 
        o.employee_name, o.subtotal, o.tax_percent, o.tax_amount, o.service_percent, 
        o.service_amount, o.total, o.status, o.created_at,
        u.name as kasir_name, 
        COALESCE(NULLIF(o.customer_name, ''), c.employee_name, o.employee_name) as display_customer_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN credits c ON c.order_id = o.id
        WHERE o.branch_id = ? AND o.status = 'pending'
        ORDER BY o.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute([$branch]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil karyawan untuk kredit
$karyawan = db()->query("SELECT id, name FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Ambil produk untuk tambah item
$sql = 'SELECT p.*, IFNULL(sb.quantity,0) stock
        FROM products p
        LEFT JOIN stock_branch sb 
          ON sb.product_id = p.id AND sb.branch_id = ?
        WHERE p.active=1 AND p.branch_id = ?
        ORDER BY p.name ASC';
$st = db()->prepare($sql);
$st->execute([$branch, $branch]);
$products = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Daftar Orders - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="/pos-web-starter/assets/css/sweetalert2.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; }
.container { 
  margin-left:240px; 
  padding:20px; 
  max-width:1400px;
}
.header-section {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:20px;
}
.header-section h1 {
  color:#ffd700;
  margin:0;
}
.btn { 
  padding:10px 20px; 
  border:none; 
  border-radius:6px; 
  cursor:pointer; 
  font-weight:bold; 
  transition:all 0.2s;
  text-decoration:none;
  display:inline-block;
}
.btn.primary { background:#007bff; color:#fff; }
.btn.primary:hover { background:#0056b3; }
.btn.success { background:#28a745; color:#fff; }
.btn.success:hover { background:#218838; }
.btn.danger { background:#e74c3c; color:#fff; }
.btn.danger:hover { background:#c0392b; }
.btn.info { background:#17a2b8; color:#fff; }
.btn.info:hover { background:#138496; }
.btn.warning { background:#ffc107; color:#212529; }
.btn.warning:hover { background:#e0a800; }
.btn.small { padding:6px 12px; font-size:12px; }

.card {
  background:#0b6e4f;
  border-radius:12px;
  padding:16px;
  margin-bottom:20px;
  box-shadow:0 5px 12px rgba(0,0,0,.3);
}
.orders-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(350px, 1fr));
  gap:20px;
}
.order-card {
  background:#085c3a;
  border-radius:10px;
  padding:16px;
  border:2px solid #27ae60;
  transition:all 0.3s;
}
.order-card:hover {
  transform:translateY(-3px);
  box-shadow:0 6px 15px rgba(0,0,0,.4);
  border-color:#ffd700;
}
.order-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:12px;
  padding-bottom:10px;
  border-bottom:2px solid rgba(255,215,0,0.3);
}
.order-id {
  font-size:20px;
  font-weight:bold;
  color:#ffd700;
}
.order-status {
  background:#ffc107;
  color:#000;
  padding:4px 12px;
  border-radius:15px;
  font-size:11px;
  font-weight:bold;
  text-transform:uppercase;
}
.order-info {
  margin:8px 0;
  font-size:13px;
  line-height:1.8;
}
.order-info strong {
  color:#ffd700;
  display:inline-block;
  min-width:100px;
}
.order-total {
  font-size:18px;
  font-weight:bold;
  color:#ffd700;
  margin:12px 0;
  padding:10px;
  background:rgba(255,215,0,0.1);
  border-radius:6px;
  text-align:center;
}
.order-actions {
  display:grid;
  grid-template-columns:2fr 1fr 1fr 1fr;
  gap:8px;
  margin-top:12px;
}
.empty-state {
  text-align:center;
  padding:60px 20px;
  color:#999;
}
.empty-state i {
  font-size:64px;
  margin-bottom:20px;
  opacity:0.5;
}

.print-status {
  position: fixed;
  top: 15px;
  right: 15px;
  background: #28a745;
  color: white;
  padding: 8px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: bold;
  z-index: 9999;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  animation: slideInRight 0.3s ease;
}

.print-status.error {
  background: #dc3545;
}

.print-status.info {
  background: #17a2b8;
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

@media (max-width: 900px) {
  .container {
    margin-left:70px;
    padding:15px;
  }
  .orders-grid {
    grid-template-columns:1fr;
  }
  .header-section {
    flex-direction:column;
    gap:15px;
  }
  .order-actions {
    grid-template-columns:1fr;
    gap:6px;
  }
}
</style>
</head>
<body>

<div class="container">
  <div class="header-section">
    <h1><i class="fa fa-list-alt"></i> Daftar Orders (Pending Payment)</h1>
    <a href="pos.php" class="btn primary">
      <i class="fa fa-arrow-left"></i> Kembali ke POS
    </a>
  </div>
  
  <?php if (empty($orders)): ?>
    <div class="card">
      <div class="empty-state">
        <i class="fa fa-inbox"></i>
        <h2>Tidak Ada Order Pending</h2>
        <p>Semua order sudah dibayar atau belum ada order baru</p>
        <a href="pos.php" class="btn success" style="margin-top:20px;">
          <i class="fa fa-plus"></i> Buat Order Baru
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="orders-grid">
      <?php foreach ($orders as $order): ?>
        <div class="order-card">
          <div class="order-header">
            <div class="order-id">#<?= $order['id'] ?></div>
            <div class="order-status">⏳ Pending</div>
          </div>
          
          <div class="order-info">
            <div><strong>Customer:</strong> <?= e($order['display_customer_name'] ?: '-') ?></div>
            <div><strong>Tipe:</strong> <?= ucfirst($order['order_type']) ?></div>
            <?php if ($order['table_no']): ?>
              <div><strong>No. Meja:</strong> <?= e($order['table_no']) ?></div>
            <?php endif; ?>
            <div><strong>Kasir:</strong> <?= e($order['kasir_name'] ?? '-') ?></div>
            <div><strong>Waktu:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
          </div>
          
          <div class="order-total">
            Total: Rp <?= number_format($order['total'], 0, ',', '.') ?>
          </div>
          
          <div class="order-actions">
            <button class="btn success" onclick="payOrder(<?= $order['id'] ?>, <?= $order['total'] ?>)">
              <i class="fa fa-credit-card"></i> Bayar
            </button>
            <button class="btn info small" onclick="viewOrderDetail(<?= $order['id'] ?>)">
              <i class="fa fa-eye"></i> Detail
            </button>
            <button class="btn warning small" onclick="addItemsToOrder(<?= $order['id'] ?>)">
              <i class="fa fa-plus"></i> Tambah
            </button>
            <button class="btn danger small" onclick="cancelOrder(<?= $order['id'] ?>)">
              <i class="fa fa-times"></i> Batal
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
<script>
console.log('Orders List - FINAL VERSION WITH EMPLOYEE SEARCH');

const karyawanData = <?= json_encode($karyawan) ?>;
const productsData = <?= json_encode($products) ?>;

// THERMAL PRINT FUNCTIONS
function showPrintStatus(message, type = 'success') {
  const existingStatus = document.querySelector('.print-status');
  if (existingStatus) {
    document.body.removeChild(existingStatus);
  }
  
  const printStatus = document.createElement('div');
  printStatus.className = `print-status ${type}`;
  printStatus.textContent = message;
  
  document.body.appendChild(printStatus);
  
  setTimeout(() => {
    if (document.body.contains(printStatus)) {
      document.body.removeChild(printStatus);
    }
  }, 3000);
}

function directThermalPrint(orderId, type = 'cashier') {
  console.log('Direct thermal print:', orderId);
  showPrintStatus('🖨️ Mencetak struk thermal...', 'info');
  
  const iframe = document.createElement('iframe');
  iframe.style.display = 'none';
  iframe.src = `print_escpos.php?id=${orderId}&type=${type}`;
  document.body.appendChild(iframe);
  
  iframe.onload = () => {
    setTimeout(() => {
      showPrintStatus('✅ Struk berhasil dicetak!', 'success');
      if (document.body.contains(iframe)) document.body.removeChild(iframe);
    }, 1000);
  };
  
  iframe.onerror = () => {
    showPrintStatus('❌ Gagal mencetak', 'error');
    if (document.body.contains(iframe)) document.body.removeChild(iframe);
  };
  
  setTimeout(() => {
    if (document.body.contains(iframe)) document.body.removeChild(iframe);
  }, 10000);
}

// ADD ITEMS TO ORDER
async function addItemsToOrder(orderId) {
  const productOptions = {};
  productsData.forEach(product => {
    if(product.stock > 0) {
      productOptions[product.id] = `${product.name} - Rp ${parseInt(product.price).toLocaleString('id-ID')} (Stok: ${product.stock})`;
    }
  });
  
  if(Object.keys(productOptions).length === 0) {
    Swal.fire('Tidak ada produk tersedia', '', 'warning');
    return;
  }
  
  const { value: selectedProductId } = await Swal.fire({
    title: 'Pilih Produk',
    input: 'select',
    inputOptions: productOptions,
    inputPlaceholder: 'Pilih produk',
    showCancelButton: true,
    confirmButtonText: 'Lanjutkan',
    cancelButtonText: 'Batal',
    inputValidator: (value) => !value && 'Pilih produk terlebih dahulu!'
  });
  
  if (!selectedProductId) return;
  
  const selectedProduct = productsData.find(p => p.id == selectedProductId);
  
  const { value: quantity } = await Swal.fire({
    title: 'Masukkan Jumlah',
    html: `<div style="text-align:left;margin-bottom:15px;padding:10px;background:#f8f9fa;border-radius:6px;color:#333;">
        <strong>${selectedProduct.name}</strong><br>
        <small>Harga: Rp ${parseInt(selectedProduct.price).toLocaleString('id-ID')}</small><br>
        <small>Stok: ${selectedProduct.stock}</small>
      </div>`,
    input: 'number',
    inputAttributes: { min: 1, max: selectedProduct.stock },
    showCancelButton: true,
    confirmButtonText: 'Tambahkan',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      const qty = parseInt(value);
      if (!qty || qty < 1) return 'Jumlah minimal 1!';
      if (qty > selectedProduct.stock) return `Stok maksimal ${selectedProduct.stock}`;
    }
  });
  
  if (!quantity) return;
  
  try {
    Swal.fire({title:'Menambahkan item...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    
    const res = await fetch('add_items_to_order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        order_id: orderId,
        items: [{ id: selectedProduct.id, name: selectedProduct.name, price: selectedProduct.price, qty: parseInt(quantity), disc: 0 }]
      })
    });
    
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Error');
    
    await Swal.fire({
      icon: 'success',
      title: 'Item Ditambahkan!',
      html: `<strong>Total baru:</strong> Rp ${data.new_total.toLocaleString('id-ID')}`,
      confirmButtonText: 'OK'
    });
    
    window.location.reload();
  } catch (e) {
    Swal.fire('Gagal', e.message, 'error');
  }
}

// PAY ORDER - WITH EMPLOYEE SEARCH
async function payOrder(orderId, total) {
  const { value: method } = await Swal.fire({
    title: 'Pilih Metode Pembayaran',
    html: `<div style="padding:12px;background:#fff3cd;border-radius:6px;color:#856404;border:1px solid #ffeaa7;margin-bottom:15px;">
           <i class="fa fa-info-circle"></i> <strong>Total: Rp ${total.toLocaleString('id-ID')}</strong>
         </div>`,
    input: 'radio',
    inputOptions: {
      'cash': '💵 Tunai',
      'qris': '📱 QRIS',
      'credit': '💳 Kredit (Karyawan)'
    },
    inputValidator: (value) => !value && 'Pilih metode pembayaran!',
    showCancelButton: true,
    confirmButtonText: 'Lanjutkan',
    cancelButtonText: 'Batal'
  });
  
  if (!method) return;
  
  // CASH
  if (method === 'cash') {
    const { value: cashGiven } = await Swal.fire({
      title: 'Masukkan Uang Tunai',
      html: `<div style="margin-bottom:15px;">
             <label style="font-weight:bold;margin-bottom:8px;display:block;color:#333;">
               Total: Rp ${total.toLocaleString('id-ID')}
             </label>
             <input id="swal2-input" class="swal2-input" type="number" placeholder="Jumlah uang..." style="text-align:right;font-size:16px;">
           </div>`,
      showCancelButton: true,
      confirmButtonText: 'Hitung Kembalian',
      cancelButtonText: 'Batal',
      preConfirm: () => {
        const value = parseInt(document.getElementById('swal2-input').value);
        if (!value || value < total) {
          Swal.showValidationMessage(`Uang kurang! Total: Rp ${total.toLocaleString('id-ID')}`);
          return false;
        }
        return value;
      }
    });
    
    if (!cashGiven) return;
    
    const change = cashGiven - total;
    const { isConfirmed } = await Swal.fire({
      icon: 'info',
      title: 'Konfirmasi Pembayaran',
      html: `<div style="padding:15px;background:#d4edda;border-radius:6px;color:#155724;">
             <div><strong>Total:</strong> Rp ${total.toLocaleString('id-ID')}</div>
             <div><strong>Uang Diberikan:</strong> Rp ${cashGiven.toLocaleString('id-ID')}</div>
             <div style="font-size:18px;margin-top:10px;padding:8px;background:rgba(40,167,69,0.1);border-radius:4px;">
               <strong>Kembalian: Rp ${change.toLocaleString('id-ID')}</strong>
             </div>
           </div>`,
      showCancelButton: true,
      confirmButtonText: 'Proses',
      cancelButtonText: 'Koreksi',
      confirmButtonColor: '#28a745'
    });
    
    if (!isConfirmed) return payOrder(orderId, total);
    processPayment(orderId, method, {cash_given: cashGiven, change_amount: change});
  } 
  // CREDIT - WITH SEARCH
  else if (method === 'credit') {
    if (karyawanData.length === 0) {
      Swal.fire('Tidak ada karyawan', '', 'warning');
      return;
    }
    
    let html = `
      <div style="padding:12px;background:#fff3cd;border-radius:6px;color:#856404;border:1px solid #ffeaa7;margin-bottom:15px;">
        <i class="fa fa-info-circle"></i> <strong>Nama karyawan = customer name</strong>
      </div>
      
      <input 
        type="text" 
        id="emp-search" 
        class="swal2-input" 
        placeholder="🔍 Cari nama karyawan..." 
        style="margin:0 0 15px 0;padding:12px;width:100%;"
        oninput="filterEmps()"
      >
      
      <div id="emp-list" style="max-height:300px;overflow-y:auto;border:1px solid #ddd;border-radius:6px;background:#fff;">
    `;
    
    karyawanData.forEach(emp => {
      html += `
        <div 
          class="emp-item" 
          data-id="${emp.id}" 
          data-name="${emp.name.toLowerCase()}"
          style="padding:12px;border-bottom:1px solid #f0f0f0;cursor:pointer;color:#333;"
          onmouseover="this.style.background='#f8f9fa'"
          onmouseout="if(!this.classList.contains('sel')) this.style.background='#fff'"
          onclick="selEmp(${emp.id}, '${emp.name.replace(/'/g, "\\'")}')"
        >
          <i class="fa fa-user" style="margin-right:8px;color:#007bff;"></i>
          <strong>${emp.name}</strong>
        </div>
      `;
    });
    
    html += `
      </div>
      <div style="margin-top:10px;text-align:center;color:#999;font-size:12px;">
        <i class="fa fa-users"></i> ${karyawanData.length} karyawan
      </div>
      <input type="hidden" id="sel-id">
      <input type="hidden" id="sel-name">
    `;
    
    const { value: result } = await Swal.fire({
      title: 'Pilih Karyawan',
      html: html,
      width: 600,
      showCancelButton: true,
      confirmButtonText: '<i class="fa fa-arrow-right"></i> Lanjut',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#f39c12',
      didOpen: () => {
        document.getElementById('emp-search').focus();
        
        window.filterEmps = () => {
          const search = document.getElementById('emp-search').value.toLowerCase();
          const items = document.querySelectorAll('.emp-item');
          let count = 0;
          
          items.forEach(item => {
            const name = item.getAttribute('data-name');
            if (name.includes(search)) {
              item.style.display = 'block';
              count++;
            } else {
              item.style.display = 'none';
            }
          });
          
          const noRes = document.getElementById('no-res');
          if (count === 0 && !noRes) {
            const msg = document.createElement('div');
            msg.id = 'no-res';
            msg.style.cssText = 'padding:20px;text-align:center;color:#999;';
            msg.innerHTML = '<i class="fa fa-search"></i> Tidak ditemukan';
            document.getElementById('emp-list').appendChild(msg);
          } else if (count > 0 && noRes) {
            noRes.remove();
          }
        };
        
        window.selEmp = (id, name) => {
          document.querySelectorAll('.emp-item').forEach(item => {
            item.style.background = '#fff';
            item.style.borderLeft = 'none';
            item.classList.remove('sel');
          });
          
          const item = document.querySelector(`[data-id="${id}"]`);
          if (item) {
            item.style.background = '#e7f3ff';
            item.style.borderLeft = '4px solid #007bff';
            item.classList.add('sel');
          }
          
          document.getElementById('sel-id').value = id;
          document.getElementById('sel-name').value = name;
        };
        
        document.getElementById('emp-search').addEventListener('keypress', e => {
          if (e.key === 'Enter') {
            const first = Array.from(document.querySelectorAll('.emp-item'))
              .find(item => item.style.display !== 'none');
            if (first) {
              const id = first.getAttribute('data-id');
              const name = first.querySelector('strong').textContent;
              selEmp(id, name);
            }
          }
        });
      },
      preConfirm: () => {
        const id = document.getElementById('sel-id').value;
        const name = document.getElementById('sel-name').value;
        if (!id) {
          Swal.showValidationMessage('Pilih karyawan!');
          return false;
        }
        return { id: parseInt(id), name };
      }
    });
    
    if (!result) return;
    
    const { isConfirmed } = await Swal.fire({
      title: 'Konfirmasi Kredit',
      html: `<div style="padding:15px;background:#fff3cd;border-radius:6px;color:#856404;">
             <div style="margin-bottom:8px;"><i class="fa fa-user"></i> <strong>Karyawan:</strong> ${result.name}</div>
             <div style="margin-bottom:8px;"><i class="fa fa-money-bill"></i> <strong>Total:</strong> Rp ${total.toLocaleString('id-ID')}</div>
             <hr style="margin:10px 0;">
             <small><i class="fa fa-info-circle"></i> Kredit dicatat atas nama <strong>${result.name}</strong></small>
           </div>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: '<i class="fa fa-check"></i> Ya, Proses',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#f39c12'
    });
    
    if (!isConfirmed) return;
    processPayment(orderId, method, { employee_id: result.id, customer_name: result.name });
  } 
  // QRIS
  else {
    processPayment(orderId, method, {});
  }
}

// PROCESS PAYMENT
async function processPayment(orderId, method, extraData = {}) {
  Swal.fire({
    title:'Memproses...',
    html: 'Mohon tunggu',
    allowOutsideClick:false,
    didOpen:()=>Swal.showLoading()
  });
  
  try {
    const res = await fetch('pos_process_payment.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ order_id: orderId, method, ...extraData })
    });
    
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Error');
    
    let html = `<div><strong>Order:</strong> #${orderId}</div>`;
    
    if (method === 'cash' && extraData.change_amount) {
      html += `<div style="margin:10px 0;padding:12px;background:#d4edda;border-radius:6px;color:#155724;">
               <strong>Kembalian: Rp ${extraData.change_amount.toLocaleString('id-ID')}</strong>
             </div>`;
    }
    
    if (method === 'credit') {
      html += `<div style="margin:10px 0;padding:12px;background:#fff3cd;border-radius:6px;color:#856404;">
               <strong>Kredit dicatat untuk ${extraData.customer_name}</strong>
             </div>`;
    }
    
    html += '<div style="margin:10px 0;padding:10px;background:#17a2b8;color:#fff;border-radius:6px;"><i class="fa fa-print"></i> Auto print thermal</div>';
    
    await Swal.fire({
      icon: 'success',
      title: 'Berhasil!',
      html,
      confirmButtonText: 'OK',
      timer: 3000
    });
    
    setTimeout(() => directThermalPrint(orderId, 'cashier'), 500);
    setTimeout(() => window.location.reload(), 2500);
  } catch (e) {
    Swal.fire('Gagal', e.message, 'error');
  }
}

// VIEW DETAIL
async function viewOrderDetail(orderId) {
  Swal.fire({title:'Loading...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  
  try {
    const res = await fetch(`pos_order_detail.php?id=${orderId}`);
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Error');
    
    let html = '<table style="width:100%;border-collapse:collapse;margin-top:15px;">';
    html += '<tr style="background:#064420;color:#ffd700;"><th style="padding:10px;border:1px solid #27ae60;">Item</th><th style="padding:10px;border:1px solid #27ae60;">Qty</th><th style="padding:10px;border:1px solid #27ae60;">Harga</th><th style="padding:10px;border:1px solid #27ae60;">Diskon</th><th style="padding:10px;border:1px solid #27ae60;">Subtotal</th></tr>';
    
    if(data.items?.length > 0) {
      data.items.forEach(item => {
        const disc = item.discount > 0 ? `${item.discount}%` : '-';
        html += `<tr style="border-bottom:1px solid #ddd;">
                  <td style="padding:10px;color:#333;border:1px solid #ddd;">${item.product_name}</td>
                  <td style="padding:10px;text-align:center;color:#333;border:1px solid #ddd;">${item.quantity}</td>
                  <td style="padding:10px;text-align:right;color:#333;border:1px solid #ddd;">Rp ${parseInt(item.price).toLocaleString('id-ID')}</td>
                  <td style="padding:10px;text-align:center;color:#333;border:1px solid #ddd;">${disc}</td>
                  <td style="padding:10px;text-align:right;color:#333;border:1px solid #ddd;font-weight:bold;">Rp ${parseInt(item.subtotal).toLocaleString('id-ID')}</td>
                </tr>`;
      });
    }
    html += '</table>';
    
    html += `<div style="margin-top:20px;padding:15px;background:#f8f9fa;border-radius:8px;color:#333;">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>Subtotal:</span><strong>Rp ${parseInt(data.order.subtotal).toLocaleString('id-ID')}</strong></div>
              <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>Pajak (${data.order.tax_percent}%):</span><strong>Rp ${parseInt(data.order.tax_amount).toLocaleString('id-ID')}</strong></div>
              <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span>Service (${data.order.service_percent}%):</span><strong>Rp ${parseInt(data.order.service_amount).toLocaleString('id-ID')}</strong></div>
              <hr style="margin:10px 0;">
              <div style="display:flex;justify-content:space-between;font-size:18px;color:#28a745;"><strong>Total:</strong><strong>Rp ${parseInt(data.order.total).toLocaleString('id-ID')}</strong></div>
            </div>`;
    
    Swal.fire({
      title: `Detail Order #${orderId}`,
      html,
      width: 750,
      confirmButtonText: 'Tutup'
    });
  } catch (e) {
    Swal.fire('Gagal', e.message, 'error');
  }
}

// CANCEL ORDER
async function cancelOrder(orderId) {
  const { isConfirmed } = await Swal.fire({
    title: 'Batalkan Order?',
    html: '<div style="margin:10px 0;"><i class="fa fa-exclamation-triangle" style="color:#e74c3c;font-size:48px;"></i></div>Order dihapus & stok dikembalikan',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Batalkan',
    cancelButtonText: 'Tidak',
    confirmButtonColor: '#e74c3c'
  });
  
  if (!isConfirmed) return;
  
  Swal.fire({title:'Membatalkan...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
  
  try {
    const res = await fetch('pos_cancel_order.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({order_id: orderId})
    });
    
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Error');
    
    await Swal.fire('Berhasil', 'Order dibatalkan', 'success');
    window.location.reload();
  } catch (e) {
    Swal.fire('Gagal', e.message, 'error');
  }
}

console.log('Order List Ready - Employee Search Enabled');
</script>
</body>
</html>
