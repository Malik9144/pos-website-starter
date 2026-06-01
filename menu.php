<?php
function render_menu($u) {
  $role = $u['role'] ?? '';

  echo '<nav>';

  if ($role === 'kasir') {
    // Kasir hanya bisa akses POS
    echo '<a href="pos.php"><i class="fa fa-shopping-cart"></i> Transaksi</a>';
    echo '<a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>';
  } else {
    // Role lain menu lengkap
    echo '<a href="dashboard.php"><i class="fa fa-home"></i> Dashboard</a>';
    echo '<a href="manage_products.php"><i class="fa fa-box"></i> Produk</a>';
    echo '<a href="pos.php"><i class="fa fa-shopping-cart"></i> Transaksi</a>';
    echo '<a href="reports.php"><i class="fa fa-chart-line"></i> Laporan</a>';
    echo '<a href="manage_branches.php"><i class="fa fa-store"></i> Cabang</a>';
    echo '<a href="manage_users.php"><i class="fa fa-users"></i> User</a>';
    echo '<a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>';
  }

  echo '</nav>';
}
