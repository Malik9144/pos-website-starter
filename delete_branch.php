<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';

auth_required(['admin','superadmin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        header("Location: manage_branches.php?err=ID cabang tidak valid");
        exit;
    }

    try {
        // validasi: cek apakah dipakai user / orders
        $check = db()->prepare("SELECT COUNT(*) FROM users WHERE branch_id=?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            header("Location: manage_branches.php?err=Cabang masih dipakai oleh user, tidak bisa dihapus");
            exit;
        }

        $st = db()->prepare("DELETE FROM branches WHERE id=?");
        $st->execute([$id]);

        header("Location: manage_branches.php?msg=Cabang berhasil dihapus");
        exit;
    } catch (Exception $e) {
        header("Location: manage_branches.php?err=Gagal hapus cabang: ".$e->getMessage());
        exit;
    }
}
