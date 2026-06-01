
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/utils.php';

// Customer menu accessible without login
// branch_id bisa diberikan via query string (untuk Cafe Batu/OneL/Oasis)
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;

// Validate branch_id
$stmt = db()->prepare('SELECT id FROM branches WHERE id = ?');
$stmt->execute([$branch_id]);
if (!$stmt->fetch()) {
  $branch_id = 1;
}

// Fetch products for branch
$sql = 'SELECT p.*, IFNULL(sb.quantity,0) stock
        FROM products p
        LEFT JOIN stock_branch sb 
          ON sb.product_id = p.id AND sb.branch_id = ?
        WHERE p.active=1 AND p.branch_id = ?
        ORDER BY p.name ASC';
$st = db()->prepare($sql);
$st->execute([$branch_id, $branch_id]);
$products = $st->fetchAll(PDO::FETCH_ASSOC);



$categoryCount = ['all' => count($products), 'food' => 0, 'drink' => 0, 'other' => 0];
foreach ($products as $p) {
  $cat = $p['category'] ?? 'other';
  if (!isset($categoryCount[$cat])) $cat = 'other';
  $categoryCount[$cat]++;
}

$branchName = db()->prepare('SELECT name FROM branches WHERE id=?');
// Catatan: ID cabang disesuaikan dengan mapping Cafe Batu/OneL/Oasis di database branches.
$branchName->execute([$branch_id]);
$branchName = $branchName->fetchColumn() ?: 'Pusat';

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Customer Menu - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="/pos-web-starter/assets/css/sweetalert2.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; }
  .container { padding:20px; max-width:1200px; margin:0 auto; }
  .header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px; }
  .header h1 { color:#ffd700; margin:0; }
  .meta { color: rgba(255,255,255,.8); font-size:12px; }

  .grid { display:grid; grid-template-columns: 1.3fr 1fr; gap:16px; }
  .card { background:#0b6e4f; border-radius:12px; padding:16px; box-shadow:0 5px 12px rgba(0,0,0,.25); }
  .card h2 { color:#ffd700; margin:0 0 12px 0; display:flex; align-items:center; gap:8px; }

  .search-input { width:100%; padding:12px 15px; border-radius:8px; border:2px solid #27ae60; background:#fff; color:#333; font-size:16px; }
  .category-filters { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
  .category-btn { padding:8px 14px; border:2px solid #27ae60; background:transparent; color:#27ae60; border-radius:20px; cursor:pointer; font-size:13px; font-weight:bold; display:flex; align-items:center; gap:6px; }
  .category-btn.active { background:#ffd700; color:#064420; border-color:#ffd700; }
  .category-btn .badge { background:rgba(255,255,255,.2); color:inherit; padding:2px 6px; border-radius:10px; font-size:11px; }

  .product-scroll { max-height:68vh; overflow-y:auto; padding-right:8px; }
  .product-grid { display:grid; grid-template-columns: repeat(auto-fill,minmax(95px,1fr)); gap:8px; }

  .product-card { background:#085c3a; border-radius:10px; overflow:hidden; box-shadow:0 3px 8px rgba(0,0,0,.25); transition:.2s; }
  .product-card.hidden { display:none; }
  .product-card:hover { transform: translateY(-3px); }
  .product-card img { width:100%; height:65px; object-fit:cover; background:#fff; }
  .product-card .p { padding:8px; }
  .product-name { font-size:12px; font-weight:700; }
  .price { color:#ffd700; font-weight:800; font-size:13px; margin-top:5px; }
  .stock { color:#ffd700; font-size:10px; margin-top:4px; }
  .btn { padding:10px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:800; transition:all 0.2s ease; text-align:center; font-size:13px; display:flex; align-items:center; justify-content:center; gap:6px; min-height:42px; white-space:nowrap; }
  .btn.small { padding:8px 12px; font-size:12px; min-height:38px; }
  .btn.primary { background: linear-gradient(135deg,#007bff,#0056b3); color:#fff; }
  .btn.success { background: linear-gradient(135deg,#28a745,#218838); color:#fff; }
  .btn.warning { background: linear-gradient(135deg,#ffc107,#e0a800); color:#212529; }
  .btn.secondary { background: linear-gradient(135deg,#6c757d,#545b62); color:#fff; }
  .btn.danger { background: linear-gradient(135deg,#dc3545,#c82333); color:#fff; }
  .input { width:100%; padding:10px; border-radius:8px; border:none; margin:6px 0; color:#333; }
  .input-taxsvc { width:80px; padding:8px; border-radius:8px; border:none; color:#333; }

  .cart { display:flex; flex-direction:column; }
  .cart-items { border:2px solid rgba(255,215,0,0.35); border-radius:8px; background: rgba(255,255,255,0.97); color:#064420; padding:10px; max-height:44vh; overflow-y:auto; }
  .total-box { margin-top:10px; padding:10px; border-radius:8px; background:#0b6e4f; border:1px solid rgba(255,215,0,0.25); }
  label { font-weight:800; font-size:12px; display:block; margin-top:10px; margin-bottom:6px; }
  .row { display:flex; gap:10px; flex-wrap:wrap; }
  .row > * { flex:1; }
  .radio-row { display:flex; gap:10px; flex-wrap:wrap; }
  .radio-pill { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.18); padding:10px; border-radius:10px; flex:1; min-width:120px; }
  .radio-pill input { margin-right:8px; }

  .muted { color: rgba(255,255,255,0.75); font-size:12px; }

  @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } .product-scroll{ max-height:unset; } }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1><i class="fa fa-utensils"></i> Menu Customer</h1>
    <div class="meta">Cabang: <b><?= e($branchName) ?></b> | Branch ID: <?= (int)$branch_id ?></div>
  </div>

  <div class="grid">
    <div class="card">
      <h2><i class="fa fa-box"></i> Produk</h2>
      <input class="search-input" id="searchInput" placeholder="Cari produk / SKU..." autocomplete="off">

      <div class="category-filters">
        <button class="category-btn active" data-category="all" onclick="filterByCategory('all')">Semua <span class="badge"><?= $categoryCount['all'] ?></span></button>
        <button class="category-btn" data-category="food" onclick="filterByCategory('food')">Makanan <span class="badge"><?= $categoryCount['food'] ?></span></button>
        <button class="category-btn" data-category="drink" onclick="filterByCategory('drink')">Minuman <span class="badge"><?= $categoryCount['drink'] ?></span></button>
        <button class="category-btn" data-category="other" onclick="filterByCategory('other')">Lainnya <span class="badge"><?= $categoryCount['other'] ?></span></button>
      </div>

      <div class="muted" id="searchInfo" style="margin-top:10px; display:none;"></div>

      <div class="product-scroll" style="margin-top:12px;">
        <div class="product-grid" id="productGrid">
          <?php foreach($products as $p):
            $category = $p['category'] ?? 'other';
            $categoryNames = ['food'=>'Makanan','drink'=>'Minuman','other'=>'Lainnya'];
            $categoryName = $categoryNames[$category] ?? 'Lainnya';
          ?>
            <div class="product-card" data-id="<?= (int)$p['id'] ?>" data-name="<?= strtolower(e($p['name'])) ?>" data-sku="<?= strtolower(e($p['sku'])) ?>" data-category="<?= e($category) ?>">
              <img src="uploads/products/<?= e($p['image'] ?: 'no.jpg') ?>" onerror="this.src='https://picsum.photos/seed/<?= (int)$p['id'] ?>/400/300'">
              <div class="p">
                <div class="product-name"><?= e($p['name']) ?></div>
                <div class="price">Rp <?= money($p['price']) ?></div>
                <div class="stock" style="color:<?= (int)$p['stock']<=0 ? '#ff6b6b' : '#ffd700' ?>;">Stok: <?= (int)$p['stock'] ?></div>
                <button class="btn small primary" style="margin-top:8px;" onclick="addItem(<?= (int)$p['id'] ?>,'<?= e(str_replace("'","\\'",$p['name'])) ?>',<?= (int)$p['price'] ?>);return false;" <?= (int)$p['stock']<=0 ? 'disabled' : '' ?>>
                  <i class="fa fa-plus"></i> <?= (int)$p['stock']<=0 ? 'Habis' : 'Tambah' ?>
                </button>
                <div class="muted" style="margin-top:6px; font-size:10px;">SKU: <?= e($p['sku']) ?> • <?= e($categoryName) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="muted" id="noResults" style="display:none; text-align:center; padding:30px; grid-column:1/-1; opacity:.9;">
            <i class="fa fa-search" style="font-size:40px;"></i>
            <div style="margin-top:8px; font-weight:800;">Tidak ada produk ditemukan</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card cart">
      <h2><i class="fa fa-shopping-cart"></i> Keranjang & Pembayaran</h2>

      <div class="radio-row">
        <div class="radio-pill">
          <label style="margin:0; display:flex; align-items:center; gap:8px; font-size:12px; font-weight:900;">
            <input type="radio" name="order_type" value="dinein" checked> Dine In
          </label>
        </div>
        <div class="radio-pill">
          <label style="margin:0; display:flex; align-items:center; gap:8px; font-size:12px; font-weight:900;">
            <input type="radio" name="order_type" value="takeaway"> Take Away
          </label>
        </div>
      </div>

      <label>No. Meja (opsional)
        <input class="input" type="text" id="table_no" placeholder="Contoh: A1"
          value="<?= isset($_GET['table_no']) ? e(trim((string)$_GET['table_no'])) : '' ?>"
          <?= isset($_GET['table_no']) && trim((string)$_GET['table_no']) !== '' ? 'readonly style="background:#f5f5f5;"' : '' ?>
        >
      </label>

      <label>Nama Customer (opsional)
        <input class="input" type="text" id="cust_name" placeholder="Nama customer">
      </label>


      <div class="row">
        <label style="flex:1;">PPN/Tax (%)(Auto 10%)
          <input class="input-taxsvc" type="number" id="tax" value="10" min="10" max="10" disabled>
        </label>

        <label style="flex:1;">Service (%)
          <input class="input-taxsvc" type="number" id="svc" value="0" min="0" max="100">
        </label>
      </div>

      <label style="margin-top:12px;">Isi Keranjang</label>
      <div class="cart-items" id="cart"></div>

      <div class="total-box" id="totalBox"></div>

      <div class="muted" style="margin-top:10px;">
        Pembayaran diproses langsung (stok akan berkurang).
      </div>

      <div style="display:flex; gap:10px; margin-top:12px; flex-wrap:wrap;">
        <button class="btn secondary" style="flex:1; min-width:180px;" onclick="emptyCart()"><i class="fa fa-trash"></i> Kosongkan</button>
        <button class="btn success" style="flex:1; min-width:180px;" onclick="pay('cash')"><i class="fa fa-money-bill-wave"></i> Bayar Tunai</button>
        <button class="btn success" style="flex:1; min-width:180px;" onclick="pay('qris')"><i class="fa fa-qrcode"></i> Bayar QRIS</button>
      </div>

      <div class="muted" style="margin-top:10px; font-size:12px;">
        Catatan: Pembayaran kredit tidak tersedia untuk customer tanpa login.
      </div>
    </div>
  </div>
</div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
<script>
let cart = JSON.parse(localStorage.getItem('cart')||'[]');
let currentCategory = 'all';

// Reset keranjang kalau user berpindah cabang (branch_id) 
(function syncBranchAndResetCart(){
  const currentBranchId = String(<?= (int)$branch_id ?>);
  const storedBranchId = localStorage.getItem('customer_branch_id');
  if (storedBranchId && storedBranchId !== currentBranchId) {
    cart = [];
    localStorage.removeItem('cart');
  }
  localStorage.setItem('customer_branch_id', currentBranchId);
})();

let currentSearchQuery = '';
let searchTimeout;

const productsData = <?= json_encode($products) ?>;

function save(){
  localStorage.setItem('cart', JSON.stringify(cart));
  render();
}

function addItem(id, name, price) {
  let existing = cart.find(i => i.id == id);
  if(existing){ existing.qty++; }
  else { cart.push({id, name, price, qty:1, disc:0}); }
  save();
}

function emptyCart(){
  cart = [];
  localStorage.removeItem('cart');
  render();
  Swal.fire('Keranjang kosong', '', 'success');
}

function subtotal(i){
  const it = cart[i];
  const subt = it.price * it.qty;
  const diskon = Math.round(subt * ((it.disc||0)/100));
  return subt - diskon;
}

function totalBruto(){
  return cart.reduce((t,_,idx)=> t + subtotal(idx), 0);
}

function render(){
  const el = document.getElementById('cart');
  const totalEl = document.getElementById('totalBox');
  if(cart.length===0){
    el.innerHTML = '<div style="padding:12px; color:#064420; background:rgba(6,68,32,0.04); border-radius:8px; text-align:center;">Keranjang kosong</div>';
    totalEl.innerHTML = '';
    return;
  }

  let html='';
  cart.forEach((item,i)=>{
    const itemSubtotal = subtotal(i);
    html += `
      <div style="background:#f9f9f9; border:1px solid #e5e5e5; border-radius:8px; padding:10px; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
          <div>
            <div style="font-weight:900; color:#064420;">${item.name}</div>
            <div style="font-size:12px; color:#555; margin-top:2px;">Harga: Rp ${item.price.toLocaleString('id-ID')}</div>
          </div>
          <button onclick="removeItem(${i})" style="background:#e74c3c; color:#fff; border:none; border-radius:50%; width:26px; height:26px; cursor:pointer;">&times;</button>
        </div>
        <div style="margin-top:8px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
          <div style="font-size:12px; color:#333; font-weight:800;">Qty
            <input type="number" min="1" value="${item.qty}" onchange="setQty(${i}, this.value)" style="margin-left:8px; width:60px; padding:6px; border:1px solid #ddd; border-radius:6px;" />
          </div>
          <div style="font-size:12px; color:#333; font-weight:800;">Disc%
            <input type="number" min="0" max="100" value="${item.disc||0}" onchange="setDisc(${i}, this.value)" style="margin-left:8px; width:60px; padding:6px; border:1px solid #ddd; border-radius:6px;" />
          </div>
          <div style="font-weight:900; color:#28a745; margin-left:auto;">Rp ${itemSubtotal.toLocaleString('id-ID')}</div>
        </div>
      </div>
    `;
  });
  el.innerHTML = html;
  showTotalBox();
}

function showTotalBox(){
  const bruto = totalBruto();
  const pct_tax = parseFloat(document.getElementById('tax').value)||0;
  const pct_svc = parseFloat(document.getElementById('svc').value)||0;
  const nilai_tax = Math.round(bruto * pct_tax / 100);
  const nilai_svc = Math.round(bruto * pct_svc / 100);
  const tot_all = bruto + nilai_tax + nilai_svc;
  document.getElementById('totalBox').innerHTML = `
    <div><b>Subtotal:</b> Rp ${bruto.toLocaleString('id-ID')}</div>
    <div><b>Pajak:</b> Rp ${nilai_tax.toLocaleString('id-ID')} (${pct_tax}%)</div>
    <div><b>Layanan:</b> Rp ${nilai_svc.toLocaleString('id-ID')} (${pct_svc}%)</div>
    <div style="font-size:16px; margin-top:6px;"><b>Total:</b> Rp ${tot_all.toLocaleString('id-ID')}</div>
  `;
}

function setQty(i, qty){
  const newQty = Math.max(1, parseInt(qty)||1);
  cart[i].qty = newQty;
  save();
}

function setDisc(i, disc){
  const newDisc = Math.max(0, Math.min(100, parseInt(disc)||0));
  cart[i].disc = newDisc;
  save();
}

function removeItem(i){
  cart.splice(i,1);
  save();
}

function applyFilters(){
  const productCards = document.querySelectorAll('.product-card');
  const noResults = document.getElementById('noResults');
  let visible = 0;

  productCards.forEach(card=>{
    const name = (card.dataset.name||'');
    const sku = (card.dataset.sku||'');
    const category = card.dataset.category||'other';
    const categoryMatch = currentCategory==='all' || category===currentCategory;
    const searchMatch = currentSearchQuery==='' || name.includes(currentSearchQuery.toLowerCase()) || sku.includes(currentSearchQuery.toLowerCase());

    if(categoryMatch && searchMatch){
      card.classList.remove('hidden');
      visible++;
    } else {
      card.classList.add('hidden');
    }
  });

  if(currentSearchQuery!=='' || currentCategory!=='all'){
    const info = document.getElementById('searchInfo');
    let txt = `Ditemukan ${visible} produk`;
    if(currentCategory!=='all') txt += ` kategori ${currentCategory}`;
    if(currentSearchQuery!=='') txt += ` untuk "${currentSearchQuery}"`;
    info.textContent = txt;
    info.style.display = 'block';
  } else {
    document.getElementById('searchInfo').style.display='none';
  }

  noResults.style.display = visible===0 ? 'block' : 'none';
}

function filterByCategory(category){
  currentCategory = category;
  document.querySelectorAll('.category-btn').forEach(b=>b.classList.remove('active'));
  const active = document.querySelector(`.category-btn[data-category="${category}"]`);
  if(active) active.classList.add('active');
  applyFilters();
}

document.getElementById('tax').oninput = showTotalBox;
document.getElementById('svc').oninput = showTotalBox;

function totalPayload(){
  const pct_tax = parseFloat(document.getElementById('tax').value)||0;
  const pct_svc = parseFloat(document.getElementById('svc').value)||0;
  const bruto = totalBruto();
  const nilai_tax = Math.round(bruto * pct_tax / 100);
  const nilai_svc = Math.round(bruto * pct_svc / 100);
  const tot_all = bruto + nilai_tax + nilai_svc;
  return {pct_tax, pct_svc, bruto, tot_all};
}

async function pay(method){
  if(cart.length===0){
    Swal.fire('Keranjang kosong', '', 'warning');
    return;
  }

  const order_type = document.querySelector('input[name="order_type"]:checked').value;
  const table_no = document.getElementById('table_no').value || '';
  const customer_name = document.getElementById('cust_name').value || '';
  const transaction_date = new Date().toISOString().slice(0,10);

  const payload = {
    branch_id: <?= (int)$branch_id ?>,
    items: cart,
    customer_name,
    tax: parseFloat(document.getElementById('tax').value)||0,
    service: parseFloat(document.getElementById('svc').value)||0,
    order_type,
    table_no,
    method,
    transaction_type: 'direct',
    transaction_date
  };

  const { tot_all } = totalPayload();

  const confirm = await Swal.fire({
    icon: 'info',
    title: 'Konfirmasi pembayaran',
    html: `<div><b>Total:</b> Rp ${tot_all.toLocaleString('id-ID')}</div><div style="margin-top:8px;">Metode: <b>${method.toUpperCase()}</b></div>`,
    showCancelButton: true,
    confirmButtonText: 'Proses',
    cancelButtonText: 'Batal'
  });

  if(!confirm.isConfirmed) return;

  try {
    Swal.fire({title:'Memproses...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
    const res = await fetch('customer_pos_create_order.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if(!res.ok) throw new Error(data.message || 'Gagal proses');

    localStorage.removeItem('cart');
    cart = [];
    render();

    await Swal.fire({
      icon:'success',
      title:'Transaksi berhasil',
      html:`<div><strong>Order ID:</strong> #${data.order_id}</div><div style="margin-top:8px;">Total: Rp ${data.total.toLocaleString('id-ID')}</div>`,
      timer: 2500,
      showConfirmButton: false
    });

    setTimeout(()=>{
      // optional: print thermal (iframe) if page is secured? we keep as optional.
      try{
        const iframe = document.createElement('iframe');
        iframe.style.display='none';
        iframe.src = `print_escpos.php?id=${data.order_id}&type=cashier`;
        document.body.appendChild(iframe);
        setTimeout(()=>{ if(document.body.contains(iframe)) document.body.removeChild(iframe); }, 5000);
      }catch(e){}
    }, 300);

  } catch(e){
    Swal.fire('Gagal', e.message, 'error');
  }
}

function initializeSearch(){
  const searchInput = document.getElementById('searchInput');
  searchInput.addEventListener('input', function(){
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(()=>{
      currentSearchQuery = this.value.trim();
      applyFilters();
    },150);
  });
}

document.addEventListener('DOMContentLoaded', ()=>{
  render();
  initializeSearch();
  applyFilters();
});
</script>
</body>
</html>

