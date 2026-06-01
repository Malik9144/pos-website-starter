<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../lib/permissions.php';
auth_required(['admin','superadmin','spv']);
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/css/theme.css"><title>Admin</title></head>
<body><div class="nav"><img src="assets/logo.png"><h1>Admin</h1><div class="right"><a class="btn" href="logout.php">Keluar</a></div></div>
<div class="grid cols-3" style="padding:16px">
  <a class="card" href="manage_users.php"><h3>Manajemen User</h3><div class="small">CRUD + role + assign cabang</div></a>
  <a class="card" href="manage_branches.php"><h3>Cabang</h3><div class="small">Multi-lokasi</div></a>
  <a class="card" href="manage_products.php"><h3>Produk</h3><div class="small">Gambar, harga, stok</div></a>
</div></body></html>