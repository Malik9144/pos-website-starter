<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';

// ✅ Tambahkan spv agar bisa hapus
auth_required(['admin','superadmin','spv_warehouse','spv']);

// hanya terima POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (empty($_POST['id'])) {
        header('Location: manage_stock.php?err=ID stok tidak ditemukan');
        exit;
    }

    $id = (int) $_POST['id'];

    // cek apakah stok ada
    $stmt = db()->prepare("SELECT * FROM warehouse_stock WHERE id=?");
    $stmt->execute([$id]);
    $stock = $stmt->fetch();

    if (!$stock) {
        header('Location: manage_stock.php?err=Stok tidak ditemukan');
        exit;
    }

    // hapus stok
    $del = db()->prepare("DELETE FROM warehouse_stock WHERE id=?");
    $del->execute([$id]);

    header("Location: manage_stock.php?msg=Stok '".urlencode($stock['name'])."' berhasil dihapus");
    exit;
} else {
    header('Location: manage_stock.php?err=Invalid request');
    exit;
}
