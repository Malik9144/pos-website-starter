<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../lib/permissions.php';
auth_required(['admin','superadmin','spv','kasir']);
?><!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="assets/css/theme.css"><title>Pengaturan</title></head>
<body><div class="nav"><img src="assets/logo.png"><h1>Pengaturan</h1></div>
<div class="grid cols-2" style="padding:16px">
  <div class="card"><h3>Akun</h3><div class="small">Aktifkan 2FA TOTP dari admin (placeholder).</div></div>
  <div class="card"><h3>Integrasi</h3>
    <ul>
      <li>QRIS Gateway (isi API key di <code>config/config.php</code>)</li>
      <li>SSO LDAP / WebAuthn (placeholder)</li>
      <li>Printer ESC/POS via QZ Tray (contoh di <code>print_escpos.php</code>)</li>
    </ul>
  </div>
</div></body></html>