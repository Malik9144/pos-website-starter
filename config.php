<?php
date_default_timezone_set('Asia/Jakarta');
// kode lain...

// config/config.php

// =====================
// Mulai session & output buffering (FIX Warning: Cannot modify header)
// =====================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

// =====================
// Database connection
// =====================
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'pos_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// =====================
// Base URL (sesuaikan folder project kamu)
// contoh: http://localhost/pos-web-starter/public/
// =====================
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/pos-web-starter/public/');

// =====================
// Session
// =====================
if (!defined('SESSION_NAME')) define('SESSION_NAME', 'pos_session');

// =====================
// Nama Aplikasi
// =====================
if (!defined('APP_NAME')) define('APP_NAME', 'TampomasEcoPark');

// =====================
// Pajak default (misal 11%)
// =====================
if (!defined('PPN_DEFAULT')) define('PPN_DEFAULT', 0.11);

// =====================
// Pembayaran (QRIS, dll.)
// =====================
if (!defined('QRIS_KEY')) define('QRIS_KEY', 'sandbox_key');

// =====================
// Branding Struk
// =====================
if (!defined('STORE_NAME')) define('STORE_NAME', 'Toko Saya');
if (!defined('STORE_ADDRESS')) define('STORE_ADDRESS', 'Jl. Contoh No. 123, Jakarta');
if (!defined('STORE_PHONE')) define('STORE_PHONE', '0812-3456-7890');

// =====================
// Logo Struk (PNG kecil ideal untuk thermal)
// =====================
if (!defined('LOGO_PATH')) define('LOGO_PATH', __DIR__ . '/../public/assets/logo.png');

// =====================
// Default User Role (opsional, untuk registrasi awal)
// =====================
if (!defined('DEFAULT_ROLE')) define('DEFAULT_ROLE', 'kasir');

// =====================
// Default Branch ID (opsional, jika multi cabang belum diatur)
// =====================
if (!defined('DEFAULT_BRANCH_ID')) define('DEFAULT_BRANCH_ID', 1);
// === Fitur Modul Toggle ===
define('ENABLE_TICKET_MODULE',false); // Ubah ke true untuk mengaktifkan modul tiket