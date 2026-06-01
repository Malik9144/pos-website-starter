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

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'ID reservasi tidak valid.';
  exit;
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if (!in_array($action, ['cancel', 'set_seated', 'set_completed'], true)) {
    die('Aksi tidak valid');
  }

  // Ambil status saat ini
  $stmt = db()->prepare("SELECT * FROM reservations WHERE id = ? AND branch_id = ?");
  $stmt->execute([$id, $branch]);
  $r = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$r) {
    die('Reservasi tidak ditemukan');
  }

  if ($action === 'cancel') {
    $newStatus = 'cancelled';
  } elseif ($action === 'set_seated') {
    $newStatus = 'seated';
  } else {
    $newStatus = 'completed';
  }

  $up = db()->prepare("UPDATE reservations SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND branch_id = ?");
  $up->execute([$newStatus, $id, $branch]);

  header('Location: reservation_detail.php?id=' . $id . '&msg=Status berhasil diupdate');
  exit;
}

// GET detail
$stmt = db()->prepare("SELECT r.*, u.name AS creator_name FROM reservations r LEFT JOIN users u ON u.id = r.created_by WHERE r.id = ? AND r.branch_id = ? LIMIT 1");
$stmt->execute([$id, $branch]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
  http_response_code(404);
  echo 'Reservasi tidak ditemukan.';
  exit;
}

$status = $reservation['status'];
function status_label($s) {
  return match($s) {
    'booked' => 'BOOKED',
    'seated' => 'SEATED',
    'completed' => 'COMPLETED',
    'cancelled' => 'CANCELLED',
    default => strtoupper((string)$s),
  };
}

$canCancel = $status !== 'cancelled' && $status !== 'completed';
$canSeat = $status === 'booked';
$canComplete = $status === 'seated';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Reservasi #<?= $id ?> - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="/pos-web-starter/assets/css/sweetalert2.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; }
  .container { margin-left:240px; padding:20px; max-width:1000px; }
  .header-section { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; flex-wrap:wrap; }
  .header-section h1 { color:#ffd700; margin:0; }

  .btn { padding:10px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; display:inline-flex; gap:8px; align-items:center; text-decoration:none; }
  .btn.primary { background:#007bff; color:#fff; }
  .btn.secondary { background:#6c757d; color:#fff; }
  .btn.success { background:#28a745; color:#fff; }
  .btn.danger { background:#e74c3c; color:#fff; }
  .btn.small { padding:8px 12px; font-size:12px; }

  .card { background:#0b6e4f; border-radius:12px; padding:16px; box-shadow:0 5px 12px rgba(0,0,0,.3); margin-bottom:16px; }

  .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .kv { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:12px; }
  .kv .k { color:rgba(255,255,255,0.8); font-size:12px; font-weight:bold; }
  .kv .v { margin-top:6px; font-weight:bold; }

  .pill { display:inline-block; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:bold; background:rgba(255,215,0,0.18); border:1px solid rgba(255,215,0,0.3); }
  .pill.booked { background:rgba(241,196,15,0.18); border-color:rgba(241,196,15,0.35); }
  .pill.seated { background:rgba(52,152,219,0.18); border-color:rgba(52,152,219,0.35); }
  .pill.completed { background:rgba(40,167,69,0.18); border-color:rgba(40,167,69,0.35); }
  .pill.cancelled { background:rgba(231,76,60,0.18); border-color:rgba(231,76,60,0.35); }

  .actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }

  .msg { background:#28a745; color:#fff; padding:12px; border-radius:10px; margin-bottom:14px; }

  @media (max-width: 900px) { .container { margin-left:70px; padding:15px; } .grid { grid-template-columns:1fr; } }
</style>
</head>
<body>
  <div class="container">
    <div class="header-section">
      <h1><i class="fa fa-eye"></i> Detail Reservasi #<?= $id ?></h1>
      <a class="btn secondary" href="reservations.php"><i class="fa fa-arrow-left"></i> Kembali</a>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
      <div class="msg"><?= e($_GET['msg']) ?></div>
    <?php endif; ?>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <div class="muted" style="font-size:12px; opacity:0.9;">Customer</div>
          <div style="font-size:20px; font-weight:bold; color:#ffd700; margin-top:6px;"><?= e($reservation['customer_name']) ?></div>
          <?php if (!empty($reservation['phone'])): ?>
            <div style="margin-top:6px; color:rgba(255,255,255,0.85);"><i class="fa fa-phone"></i> <?= e($reservation['phone']) ?></div>
          <?php endif; ?>
        </div>
        <span class="pill <?= e($reservation['status']) ?>"><?= status_label($reservation['status']) ?></span>
      </div>

      <div class="grid" style="margin-top:14px;">
        <div class="kv">
          <div class="k">Tanggal</div>
          <div class="v"><?= e($reservation['reservation_date']) ?></div>
        </div>
        <div class="kv">
          <div class="k">Jam</div>
          <div class="v"><?= e($reservation['reservation_time']) ?></div>
        </div>
        <div class="kv">
          <div class="k">Nomor Meja</div>
          <div class="v"><?= !empty($reservation['table_no']) ? e($reservation['table_no']) : '-' ?></div>
        </div>
        <div class="kv">
          <div class="k">Jumlah Orang</div>
          <div class="v"><?= (int)$reservation['party_size'] ?></div>
        </div>
        <div class="kv">
          <div class="k">Catatan</div>
          <div class="v" style="font-weight:600; color:rgba(255,255,255,0.95);">
            <?= !empty($reservation['notes']) ? nl2br(e($reservation['notes'])) : '-' ?>
          </div>
        </div>
        <div class="kv">
          <div class="k">Dibuat oleh</div>
          <div class="v"><?= e($reservation['creator_name'] ?: '-') ?></div>
        </div>
      </div>

      <div class="actions">
        <?php if ($canSeat): ?>
          <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="set_seated">
            <button class="btn success small" type="submit" onclick="return confirm('Ubah status menjadi SEATED?')">
              <i class="fa fa-chair"></i> Seated
            </button>
          </form>
        <?php endif; ?>

        <?php if ($canComplete): ?>
          <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="set_completed">
            <button class="btn primary small" type="submit" onclick="return confirm('Ubah status menjadi COMPLETED?')">
              <i class="fa fa-check"></i> Completed
            </button>
          </form>
        <?php endif; ?>

        <?php if ($canCancel): ?>
          <form method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="cancel">
            <button class="btn danger small" type="submit" onclick="return confirm('Batalkan reservasi ini?')">
              <i class="fa fa-times"></i> Cancel
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
</body>
</html>

