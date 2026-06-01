<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/utils.php';
require_once __DIR__ . '/../src/lib/csrf.php';
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['admin','superadmin','spv','kasir']);
$u = auth_user();
$branch = $u['branch_id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $customer_name = trim($_POST['customer_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $reservation_date = $_POST['reservation_date'] ?? '';
  $reservation_time = $_POST['reservation_time'] ?? '';
  $party_size = (int)($_POST['party_size'] ?? 1);
  $table_no = trim($_POST['table_no'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if ($customer_name === '') $errors[] = 'Nama customer wajib diisi.';
  if ($reservation_date === '') $errors[] = 'Tanggal reservasi wajib diisi.';
  if ($reservation_time === '') $errors[] = 'Jam reservasi wajib diisi.';
  if ($party_size < 1) $errors[] = 'Jumlah orang minimal 1.';
  if ($table_no === '') $errors[] = 'Nomor meja wajib diisi.';

  // Validasi format sederhana
  if ($reservation_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservation_date)) {
    $errors[] = 'Format tanggal tidak valid.';
  }
  if ($reservation_time && !preg_match('/^\d{2}:\d{2}$/', $reservation_time)) {
    $errors[] = 'Format jam tidak valid.';
  }

  if (empty($errors)) {
    $stmt = db()->prepare("
      INSERT INTO reservations (
        branch_id, customer_name, phone, reservation_date, reservation_time,
        party_size, table_no, notes, status, created_by
      ) VALUES (?,?,?,?,?,?,?,?, 'booked', ?)
    ");

    $stmt->execute([
      $branch,
      $customer_name,
      $phone !== '' ? $phone : null,
      $reservation_date,
      $reservation_time,
      $party_size,
      $table_no,
      $notes !== '' ? $notes : null,
      $u['id']
    ]);

    $newId = (int)db()->lastInsertId();
    header('Location: reservation_detail.php?id=' . $newId . '&msg=Reservasi berhasil dibuat');
    exit;
  }
}

$today = date('Y-m-d');
$defaultTime = date('H:i', strtotime('+1 hour'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Buat Reservasi - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="/pos-web-starter/assets/css/sweetalert2.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; }
  .container { margin-left:240px; padding:20px; max-width:900px; }
  .header-section { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; flex-wrap:wrap; }
  .header-section h1 { color:#ffd700; margin:0; }
  .btn { padding:10px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; display:inline-flex; gap:8px; align-items:center; text-decoration:none; }
  .btn.primary { background:#007bff; color:#fff; }
  .btn.primary:hover { background:#0056b3; }
  .btn.secondary { background:#6c757d; color:#fff; }
  .btn.secondary:hover { background:#545b62; }

  .card { background:#0b6e4f; border-radius:12px; padding:16px; box-shadow:0 5px 12px rgba(0,0,0,.3); margin-bottom:16px; }

  label { display:block; margin-top:10px; margin-bottom:6px; font-weight:bold; }
  .input, select.input, textarea.input { width:100%; padding:10px 12px; border-radius:8px; border:2px solid rgba(39,174,96,1); background:#fff; color:#333; }
  textarea.input { min-height:90px; resize:vertical; }

  .error-box { background:#e74c3c; color:#fff; padding:12px; border-radius:8px; margin-bottom:12px; }
  .success-box { background:#28a745; color:#fff; padding:12px; border-radius:8px; margin-bottom:12px; }

  @media (max-width: 900px) { .container { margin-left:70px; padding:15px; } }
</style>
</head>
<body>
  <div class="container">
    <div class="header-section">
      <h1><i class="fa fa-plus-circle"></i> Buat Reservasi Dine-in</h1>
      <a class="btn secondary" href="reservations.php"><i class="fa fa-arrow-left"></i> Kembali</a>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="error-box">
        <div style="font-weight:bold; margin-bottom:8px;">Periksa input Anda:</div>
        <ul style="margin:0; padding-left:18px;">
          <?php foreach ($errors as $e): ?>
            <li><?= e($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="post">
        <?php csrf_field(); ?>

        <label>Nama Customer</label>
        <input class="input" type="text" name="customer_name" required value="<?= e($_POST['customer_name'] ?? '') ?>" placeholder="Contoh: Budi" />

        <label>No. HP (opsional)</label>
        <input class="input" type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="08xxxxxxxx" />

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px; margin-top:10px;">
          <div>
            <label>Tanggal Reservasi</label>
            <input class="input" type="date" name="reservation_date" required value="<?= e($_POST['reservation_date'] ?? $today) ?>" />
          </div>
          <div>
            <label>Jam Reservasi</label>
            <input class="input" type="time" name="reservation_time" required value="<?= e($_POST['reservation_time'] ?? $defaultTime) ?>" />
          </div>
        </div>

        <label>Jumlah Orang</label>
        <input class="input" type="number" min="1" name="party_size" required value="<?= e($_POST['party_size'] ?? '1') ?>" />

        <label>Nomor Meja</label>
        <input class="input" type="text" name="table_no" required value="<?= e($_POST['table_no'] ?? '') ?>" placeholder="Contoh: 5" />

        <label>Catatan (opsional)</label>
        <textarea class="input" name="notes" placeholder="Contoh: ulang tahun, alergi makanan"><?= e($_POST['notes'] ?? '') ?></textarea>

        <div style="display:flex;gap:10px;justify-content:flex-end; margin-top:16px; flex-wrap:wrap;">
          <button class="btn primary" type="submit"><i class="fa fa-save"></i> Simpan Reservasi</button>
        </div>
      </form>
    </div>
  </div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
</body>
</html>

