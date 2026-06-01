<?php
/**
 * SIDEBAR FINAL - DENGAN ACTIVE STATE YANG RAPI DAN ROLE KASIR VIEW-ONLY REPORTS
 * ------------------------------------------------------------------------------
 * File ini tidak melakukan pengecekan auth sendiri
 * Pengecekan auth dilakukan di halaman yang memanggil sidebar
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

// Helper: ambil nama cabang
if (!function_exists('getBranchName')) {
    function getBranchName($branch_id) {
        if (!$branch_id) return 'Pusat';
        try {
            $stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
            $stmt->execute([$branch_id]);
            $row = $stmt->fetch();
            return $row ? $row['name'] : 'Unknown';
        } catch (Exception $e) {
            return 'Error';
        }
    }
}

// Helper: alias untuk ambil user login
if (!function_exists('get_auth_user')) {
    function get_auth_user() {
        return auth_user(); // dari lib/auth.php
    }
}

$u = get_auth_user(); // Ambil data user aktif

// Safety check - jika user tidak ada, tampilkan error
if (!$u) {
    echo '<div style="color:red;padding:20px;background:white;">Error: User session tidak ditemukan. Silakan login ulang.</div>';
    return;
}

// Role-based permissions - KASIR BISA LIHAT REPORTS TAPI READ-ONLY
  $permissions = [
    'kasir' => ['dashboard', 'products', 'pos', 'reports_view', 'reservations_view'], // kasir bisa lihat reservasi
    'spv' => ['dashboard', 'products', 'pos', 'reports', 'finance', 'stock', 'reservations_manage'],
    'admin' => ['dashboard', 'products', 'pos', 'reports', 'finance', 'stock', 'master', 'reservations_manage'],
    'superadmin' => ['dashboard', 'products', 'pos', 'reports', 'finance', 'stock', 'master', 'reservations_manage'],
    'spv_warehouse' => ['dashboard', 'products', 'stock']
];

$userPermissions = $permissions[$u['role']] ?? ['dashboard'];
?>
<style>
.sidebar {
  position:fixed; top:0; left:0; bottom:0;
  width:240px;
  background: #0b6e4f;
  color:#ffd700;
  display:flex; flex-direction:column; z-index:100;
  box-shadow:2px 0 8px rgba(0,0,0,0.15);
  font-family:'Segoe UI',sans-serif;
  border-right: 1px solid #215f46;
  overflow-y: auto; /* Scroll jika menu terlalu panjang */
}
.sidebar-brand {
  font-size:18px; color:#ffd700; font-weight:bold;
  text-align:center; padding:15px 10px 20px;
  border-bottom:1px solid #215f46;
  background: #094d37;
  position: relative;
  flex-shrink: 0; /* Tidak menyusut */
}
.sidebar-brand .brand-icon {
  font-size: 22px; margin-bottom: 5px; display: block;
}
.sidebar a, .sidebar-dropdown {
  display:block; color:#ffd700; text-decoration:none;
  padding:11px 15px; font-size:15px; /* Padding dikurangi dari 18px ke 15px */
  font-weight:500; border:none; background:none;
  transition:all 0.2s ease; cursor:pointer;
  width: calc(100% - 30px); /* Lebar disesuaikan dengan padding */
  text-align: left;
  border-left: 3px solid transparent;
  margin: 1px 0;
  border-radius: 0 8px 8px 0; /* Rounded corner kanan */
  position: relative;
  overflow: hidden; /* Mencegah overflow */
}
.sidebar a:hover, .sidebar-dropdown:hover { 
  background: #215f46;
  color:#fff; 
  border-left: 3px solid #ffd700;
}
.sidebar a.active, .sidebar-dropdown.active { 
  background: #27ae60; /* Warna active yang contained */
  color:#fff; 
  border-left: 3px solid #ffd700;
  font-weight: 600;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1); /* Inner shadow untuk definisi */
}
.sidebar a i, .sidebar-dropdown i {
  width: 18px;
  text-align: center;
  margin-right: 10px;
  font-size: 14px;
}
.sidebar-menu { 
  margin-bottom:2px; 
  position: relative; /* Untuk positioning yang proper */
}
.sidebar-dropdown .arrow { 
  float:right; font-size:11px; margin-right:5px; /* Margin dikurangi */
  transition: transform 0.2s;
}
.sidebar-dropdown.active .arrow {
  transform: rotate(180deg);
}
.sidebar-subnav { 
  display:none; flex-direction:column; 
  background: #094d37;
  margin: 0;
  border-radius: 0 0 8px 0; /* Rounded bottom corner */
}
.sidebar-subnav a { 
  font-size:13px; color:#e6c200; 
  padding: 9px 12px 9px 35px; /* Padding disesuaikan */
  border-left: 3px solid transparent;
  margin: 0;
  width: calc(100% - 47px); /* Lebar disesuaikan */
}
.sidebar-subnav a:hover { 
  background: #215f46;
  color:#fff; 
  border-left: 3px solid #e6c200;
}
.sidebar-subnav a.active {
  background: #1e7e34;
  color:#fff;
  border-left: 3px solid #ffd700;
  font-weight: 600;
}
.role-indicator {
  position:absolute; top:8px; right:8px;
  background: #27ae60; color:#fff; font-size:9px;
  padding:3px 6px; border-radius:10px; 
  text-transform:uppercase; font-weight: bold;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.user-info {
  margin-top:auto; 
  background: #094d37;
  padding:15px; color:#ffd700; 
  border-top:1px solid #215f46;
  flex-shrink: 0; /* Tidak menyusut */
}
.user-info .user-name { 
  font-weight:bold; margin-bottom:8px; font-size: 14px;
  display: flex; align-items: center;
}
.user-info .user-name i {
  margin-right: 8px; font-size: 16px;
}
.user-info .user-details { 
  font-size:11px; color:#ccc; line-height: 1.4;
}
.user-info .user-details > div {
  margin-bottom: 3px; display: flex; align-items: center;
}
.user-info .user-details i {
  width: 12px; margin-right: 6px; font-size: 10px;
}
.menu-separator {
  height: 1px;
  background: #215f46;
  margin: 8px 15px;
}
/* Badge untuk menu read-only kasir */
.read-only-badge {
  font-size: 9px;
  background: #f39c12;
  color: white;
  padding: 1px 4px;
  border-radius: 6px;
  margin-left: 5px;
  font-weight: bold;
}
@media (max-width:800px) {
  .sidebar { width:70px; }
  .sidebar-brand { font-size:0; padding: 15px 5px; }
  .sidebar-brand .brand-icon { font-size: 18px; }
  .sidebar a, .sidebar-dropdown { 
    font-size:0; padding:12px 5px; text-align: center;
    width: calc(100% - 10px);
  }
  .sidebar a i, .sidebar-dropdown i { 
    font-size:16px; margin-right: 0; width: auto;
  }
  .sidebar-dropdown .arrow { display: none; }
  .sidebar-subnav { display: none !important; }
  .role-indicator { display:none; }
  .user-info { padding:10px 5px; font-size:0; }
  .user-info .user-name { font-size: 0; justify-content: center; }
  .user-info .user-name i { font-size: 16px; margin-right: 0; }
  .user-info .user-details { display: none; }
  .read-only-badge { display: none; }
}
</style>

<div class="sidebar">
  <div class="sidebar-brand">
    <span class="brand-icon"><i class="fas fa-store"></i></span>
    Dashboard POS
    <div class="role-indicator"><?= htmlspecialchars($u['role']) ?></div>
  </div>

  <!-- Menu Utama - Selalu tampil -->
  <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
  
  <?php if (in_array('products', $userPermissions)): ?>
  <a href="manage_products.php"><i class="fas fa-box"></i> Kelola Produk</a>
  <?php endif; ?>

  <?php if (in_array('pos', $userPermissions)): ?>
  <a href="pos.php"><i class="fas fa-shopping-cart"></i> Point of Sale</a>
  <?php endif; ?>

  <!-- Menu Customer (kiosk/view transaksi untuk pelanggan) -->
  <a href="customer_menu.php"><i class="fas fa-utensils"></i> Menu Customer</a>


  <?php if (count(array_intersect(['reports', 'reports_view', 'finance', 'stock', 'master', 'reservations_view', 'reservations_manage'], $userPermissions)) > 0): ?>
  <div class="menu-separator"></div>
  <?php endif; ?>

  <!-- Menu Reservasi - Dine-in dengan meja -->
  <?php if (in_array('reservations_view', $userPermissions) || in_array('reservations_manage', $userPermissions)): ?>
  <a href="reservations.php"><i class="fas fa-calendar-check"></i> Reservasi 
    <?php if (in_array('reservations_view', $userPermissions) && !in_array('reservations_manage', $userPermissions)): ?>
      <span class="read-only-badge">VIEW</span>
    <?php endif; ?>
  </a>
  <?php endif; ?>

  <!-- Menu Laporan - SPV, Admin, SuperAdmin, dan KASIR (view-only) -->
  <?php if (in_array('reports', $userPermissions) || in_array('reports_view', $userPermissions)): ?>
  <div class="sidebar-menu">
    <button class="sidebar-dropdown" data-target="submenu-laporan">
      <i class="fas fa-chart-line"></i> Laporan 
      <?php if (in_array('reports_view', $userPermissions)): ?>
        <span class="read-only-badge">VIEW</span>
      <?php endif; ?>
      <span class="arrow">▼</span>
    </button>
    <div class="sidebar-subnav" id="submenu-laporan">
      <a href="reports.php"><i class="fas fa-table"></i> Laporan Penjualan</a>
      <?php if (in_array('reports', $userPermissions)): ?> 
      <!-- Menu ini hanya untuk non-kasir -->
      <a href="stock_movements.php"><i class="fas fa-exchange-alt"></i> Riwayat Stok</a>
      <?php if ($u['role'] === 'superadmin'): ?>
      <a href="reports_summary.php"><i class="fas fa-chart-pie"></i> Ringkasan Cabang</a>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Menu Keuangan - SPV, Admin, SuperAdmin -->
  <?php if (in_array('finance', $userPermissions)): ?>
  <div class="sidebar-menu">
    <button class="sidebar-dropdown" data-target="submenu-keuangan">
      <i class="fas fa-money-bill-wave"></i> Keuangan <span class="arrow">▼</span>
    </button>
    <div class="sidebar-subnav" id="submenu-keuangan">
      <a href="manage_credits.php"><i class="fas fa-credit-card"></i> Manajemen Kredit</a>
      <a href="reports_finance.php"><i class="fas fa-chart-bar"></i> Laporan Keuangan</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Menu Stock/Gudang - SPV, Admin, SuperAdmin, SPV_Warehouse -->
  <?php if (in_array('stock', $userPermissions)): ?>
  <div class="sidebar-menu">
    <button class="sidebar-dropdown" data-target="submenu-gudang">
      <i class="fas fa-warehouse"></i> Manajemen Stok <span class="arrow">▼</span>
    </button>
    <div class="sidebar-subnav" id="submenu-gudang">
      <a href="manage_stockbranch.php"><i class="fas fa-boxes"></i> Stok per Cabang</a>
      <?php if (in_array($u['role'], ['admin', 'superadmin', 'spv_warehouse'])): ?>
      <a href="stock_opname.php"><i class="fas fa-clipboard-check"></i> Stock Opname</a>
      <a href="stock_transfer.php"><i class="fas fa-truck"></i> Transfer Stok</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Menu Master Data - Admin, SuperAdmin -->
  <?php if (in_array('master', $userPermissions)): ?>
  <div class="sidebar-menu">
    <button class="sidebar-dropdown" data-target="submenu-master">
      <i class="fas fa-cogs"></i> Master Data <span class="arrow">▼</span>
    </button>
    <div class="sidebar-subnav" id="submenu-master">
      <a href="manage_branches.php"><i class="fas fa-building"></i> Kelola Cabang</a>
      <a href="manage_users.php"><i class="fas fa-users"></i> Kelola Pengguna</a>
      <a href="import_karyawan.php"><i class="fas fa-id-card"></i> Data Karyawan</a>
      <?php if ($u['role']==='superadmin'): ?>
      <a href="system_settings.php"><i class="fas fa-wrench"></i> Pengaturan Sistem</a>
      <a href="backup_restore.php"><i class="fas fa-database"></i> Backup & Restore</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="menu-separator"></div>
  
  <!-- Logout - Selalu tampil -->
  <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>

  <div class="user-info">
    <div class="user-name">
      <i class="fas fa-user-circle"></i> <?= htmlspecialchars($u['name']) ?>
    </div>
    <div class="user-details">
      <div><i class="fas fa-shield-alt"></i> <?= ucfirst(htmlspecialchars($u['role'])) ?></div>
      <?php if (!empty($u['branch_id'])): ?>
      <div><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars(getBranchName($u['branch_id'])) ?></div>
      <?php endif; ?>
      <div><i class="fas fa-clock"></i> <?= isset($_SESSION['login_time']) ? date('H:i', strtotime($_SESSION['login_time'])) : 'N/A' ?></div>
    </div>
  </div>
</div>

<script>
function toggleSubnav(id, btn) {
  const nav = document.getElementById(id);
  if (!nav) return;
  
  // Close all other subnavs
  document.querySelectorAll('.sidebar-subnav').forEach(n => {
    if (n.id !== id) n.style.display = 'none';
  });
  document.querySelectorAll('.sidebar-dropdown').forEach(b => {
    if (b !== btn) b.classList.remove('active');
  });
  
  const isOpen = nav.style.display === 'flex';
  nav.style.display = isOpen ? 'none' : 'flex';
  btn.classList.toggle('active', !isOpen);
}

  document.addEventListener('DOMContentLoaded', () => {
  // Initialize dropdown functionality

  document.querySelectorAll('.sidebar-dropdown').forEach(btn => {
    btn.addEventListener('click', () => toggleSubnav(btn.dataset.target, btn));
  });
  
  // Auto-open submenu based on current page
  const path = window.location.pathname;
  const pageToSubmenu = {
    'reports.php': 'submenu-laporan',
    'stock_movements.php': 'submenu-laporan',
    'reports_summary.php': 'submenu-laporan',
    'manage_credits.php': 'submenu-keuangan',
    'reports_finance.php': 'submenu-keuangan',
    'cash_flow.php': 'submenu-keuangan',
    'manage_stockbranch.php': 'submenu-gudang',
    'stock_opname.php': 'submenu-gudang',
    'stock_transfer.php': 'submenu-gudang',
    'manage_branches.php': 'submenu-master',
    'manage_users.php': 'submenu-master',
    'import_karyawan.php': 'submenu-master',
    'system_settings.php': 'submenu-master',
    'backup_restore.php': 'submenu-master'
  };
  
  // Find matching submenu and open it
  for (const [page, submenuId] of Object.entries(pageToSubmenu)) {
    if (path.includes(page)) {
      const btn = document.querySelector(`[data-target="${submenuId}"]`);
      if (btn) {
        toggleSubnav(submenuId, btn);
        break;
      }
    }
  }
  
  // Highlight active menu item
  document.querySelectorAll('.sidebar a').forEach(link => {
    const href = link.getAttribute('href');
    if (href && path.includes(href)) {
      link.classList.add('active');
    }
  });
  
  // Highlight active submenu item
  document.querySelectorAll('.sidebar-subnav a').forEach(link => {
    const href = link.getAttribute('href');
    if (href && path.includes(href)) {
      link.classList.add('active');
    }
  });
});

// Export user role to window for use in other scripts
window.userRole = '<?= $u['role'] ?>';
</script>