<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__ . '/../src/nav/sidebar_functions.php';

$u = get_auth_user();

if ($u['role'] !== 'superadmin' && $u['role'] !== 'admin') {
    header('Location: dashboard.php?err=Anda tidak memiliki akses ke pengaturan sistem');
    exit;
}

// Ambil data pengaturan dari DB
$settings = [];
try {
    $rows = db()->query("SELECT `key`, `value` FROM system_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    $settings = [];
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $app_name = $_POST['app_name'] ?? '';
    $tax_percent = $_POST['tax_percent'] ?? '';
    $service_percent = $_POST['service_percent'] ?? '';
    $enable_cash = isset($_POST['enable_cash']) ? 1 : 0;
    $enable_qris = isset($_POST['enable_qris']) ? 1 : 0;
    $enable_credit = isset($_POST['enable_credit']) ? 1 : 0;

    $errors = [];
    if (trim($app_name) === '') {
        $errors[] = "Nama aplikasi tidak boleh kosong";
    }
    if (!is_numeric($tax_percent) || $tax_percent < 0 || $tax_percent > 100) {
        $errors[] = "PPN harus angka antara 0-100";
    }
    if (!is_numeric($service_percent) || $service_percent < 0 || $service_percent > 100) {
        $errors[] = "Service charge harus angka antara 0-100";
    }

    if (empty($errors)) {
        $settings_data = [
            'app_name' => $app_name,
            'tax_percent' => $tax_percent,
            'service_percent' => $service_percent,
            'enable_cash' => $enable_cash,
            'enable_qris' => $enable_qris,
            'enable_credit' => $enable_credit,
        ];

        try {
            db()->beginTransaction();
            foreach ($settings_data as $key => $value) {
                $stmt = db()->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
                $stmt->execute([$key, $value]);
            }
            db()->commit();
            header("Location: system_settings.php?msg=Pengaturan berhasil disimpan");
            exit;
        } catch (Exception $e) {
            db()->rollback();
            $errors[] = "Gagal menyimpan pengaturan: " . $e->getMessage();
        }
    }
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengaturan Sistem</title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<style>
body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #064420; color: #fff; }
.container { margin-left: 240px; padding: 30px; max-width: 600px; }
.card { background: #0b6e4f; border-radius: 12px; padding: 20px 30px; box-shadow: 0 5px 12px rgba(0,0,0,0.3); }
h2 { color: #ffd700; margin-top: 0; margin-bottom: 24px; }
label { display: block; margin-bottom: 8px; font-weight: bold; color: #ffd700; }
input[type="text"], input[type="number"] { width: 100%; padding: 10px 12px; border-radius: 6px; border: none; box-sizing: border-box; margin-bottom: 20px; }
input[type="checkbox"] { margin-right: 8px; }
button { background: #ffd700; color: #064420; border: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; cursor: pointer; }
button:hover { background: #e6c200; }
.alert { margin-bottom: 20px; padding: 12px; border-radius: 8px; font-weight: bold; }
.alert-success { background: #27ae60; color: white; }
.alert-error { background: #e74c3c; color: white; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/../src/nav/sidebar.php'; ?>
<div class="container">
  <div class="card">
    <h2><i class="fa fa-cogs"></i> Pengaturan Sistem</h2>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
          <div><?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success"><?= e($_GET['msg']) ?></div>
    <?php endif; ?>

    <form method="post">
      <?php csrf_field(); ?>
      <label>Nama Aplikasi</label>
      <input type="text" name="app_name" value="<?= e($settings['app_name'] ?? 'POS System') ?>" required>

      <label>PPN (%)</label>
      <input type="number" name="tax_percent" min="0" max="100" step="0.01" value="<?= e($settings['tax_percent'] ?? '12') ?>" required>

      <label>Service Charge (%)</label>
      <input type="number" name="service_percent" min="0" max="100" step="0.01" value="<?= e($settings['service_percent'] ?? '10') ?>" required>

      <label>Metode Pembayaran Aktif</label>
      <div>
        <label><input type="checkbox" name="enable_cash" <?= !empty($settings['enable_cash']) ? 'checked' : '' ?>> Tunai</label>
        <label><input type="checkbox" name="enable_qris" <?= !empty($settings['enable_qris']) ? 'checked' : '' ?>> QRIS</label>
        <label><input type="checkbox" name="enable_credit" <?= !empty($settings['enable_credit']) ? 'checked' : '' ?>> Kredit</label>
      </div>

      <button type="submit"><i class="fa fa-save"></i> Simpan Pengaturan</button>
    </form>
  </div>
</div>
</body>
</html>
