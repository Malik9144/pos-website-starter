<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/utils.php';

auth_required(['admin','superadmin','spv']); 
$u = me();

$id = (int)($_POST['id'] ?? 0);

$msg = '';
$icon = 'error';

// validasi ID
if (!$id) {
    $msg = "ID order tidak valid!";
} else {
    // jika role spv → wajib input password lagi
    if ($u['role'] === 'spv') {
        $password = $_POST['password'] ?? '';

        // ambil password_hash langsung dari DB
        $st = db()->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
        $st->execute([$u['id']]);
        $row = $st->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            $msg = "❌ Password salah, tidak bisa hapus order!";
        }
    }

    // jika lolos semua → hapus
    if ($msg === '') {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);

            $pdo->commit();
            $msg = "✅ Order berhasil dihapus!";
            $icon = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "❌ Gagal menghapus order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Hapus Order</title>
  <script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
</head>
<body>
<script>
Swal.fire({
    icon: '<?= $icon ?>',
    title: 'Hapus Order',
    text: "<?= $msg ?>",
    confirmButtonText: 'OK'
}).then(() => {
    window.location.href = "reports.php";
});
</script>
</body>
</html>
