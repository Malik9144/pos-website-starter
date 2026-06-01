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

$status = $_GET['status'] ?? 'booked';
$allowed = ['booked','seated','completed','cancelled'];
if (!in_array($status, $allowed)) $status = 'booked';

// Query list reservasi
$sql = "
  SELECT r.*, u.name AS creator_name
  FROM reservations r
  LEFT JOIN users u ON u.id = r.created_by
  WHERE r.branch_id = ? AND r.status = ?
  ORDER BY r.reservation_date ASC, r.reservation_time ASC, r.id DESC
";
$stmt = db()->prepare($sql);
$stmt->execute([$branch, $status]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Untuk badge filter (hitung per status)
$counts = [];
foreach ($allowed as $s) {
  $cstmt = db()->prepare("SELECT COUNT(*) AS cnt FROM reservations WHERE branch_id = ? AND status = ?");
  $cstmt->execute([$branch, $s]);
  $counts[$s] = (int)($cstmt->fetch()['cnt'] ?? 0);
}

function status_label($s) {
  if ($s === 'booked') return 'BOOKED';
  if ($s === 'seated') return 'SEATED';
  if ($s === 'completed') return 'COMPLETED';
  if ($s === 'cancelled') return 'CANCELLED';
  return strtoupper((string)$s);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Reservasi - <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<link rel="stylesheet" href="/pos-web-starter/assets/css/sweetalert2.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; }
  .container { margin-left:240px; padding:20px; max-width:1400px; }
  .header-section { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; flex-wrap:wrap; }
  .header-section h1 { color:#ffd700; margin:0; }
  .btn { padding:10px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; display:inline-flex; gap:8px; align-items:center; text-decoration:none; }
  .btn.primary { background:#007bff; color:#fff; }
  .btn.primary:hover { background:#0056b3; }
  .btn.success { background:#28a745; color:#fff; }
  .btn.danger { background:#e74c3c; color:#fff; }
  .btn.info { background:#17a2b8; color:#fff; }
  .btn.small { padding:8px 12px; font-size:12px; }

  .card { background:#0b6e4f; border-radius:12px; padding:16px; box-shadow:0 5px 12px rgba(0,0,0,.3); margin-bottom:16px; }

  .filters { display:flex; gap:8px; flex-wrap:wrap; }
  .filter-link {
    background:rgba(255,215,0,0.12);
    border:1px solid rgba(255,215,0,0.35);
    color:#ffd700;
    padding:8px 12px;
    border-radius:999px;
    text-decoration:none;
    font-size:12px;
    font-weight:bold;
  }
  .filter-link.active { background:#ffd700; color:#064420; border-color:#ffd700; }

  table { width:100%; border-collapse:collapse; background:#085c3a; border-radius:12px; overflow:hidden; }
  th, td { padding:12px; border-bottom:1px solid rgba(255,255,255,0.12); }
  th { background:#064420; color:#ffd700; text-align:left; }
  td { color:#fff; }
  tr:hover td { background:rgba(255,255,255,0.04); }

  .muted { color:rgba(255,255,255,0.8); }
  .pill {
    display:inline-block; padding:4px 10px; border-radius:999px;
    font-size:11px; font-weight:bold;
    background:rgba(255,215,0,0.18); border:1px solid rgba(255,215,0,0.3);
  }
  .pill.booked { background:rgba(241,196,15,0.18); border-color:rgba(241,196,15,0.35); }
  .pill.seated { background:rgba(52,152,219,0.18); border-color:rgba(52,152,219,0.35); }
  .pill.completed { background:rgba(40,167,69,0.18); border-color:rgba(40,167,69,0.35); }
  .pill.cancelled { background:rgba(231,76,60,0.18); border-color:rgba(231,76,60,0.35); }

  .actions { display:flex; gap:8px; flex-wrap:wrap; }

  @media (max-width: 900px) {
    .container { margin-left:70px; padding:15px; }
  }
</style>
</head>
<body>
  <div class="container">
    <div class="header-section">
      <h1><i class="fa fa-calendar-alt"></i> Daftar Reservasi</h1>
      <a class="btn primary" href="reservation_create.php">
        <i class="fa fa-plus"></i> Buat Reservasi
      </a>
    </div>

    <div class="card">
      <div class="filters">
        <?php foreach ($allowed as $s): ?>
          <a class="filter-link <?= $s === $status ? 'active' : '' ?>" href="reservations.php?status=<?= $s ?>">
            <?= status_label($s) ?> (<?= $counts[$s] ?? 0 ?>)
          </a>
        <?php endforeach; ?>
      </div>
      <div class="muted" style="margin-top:10px; font-size:12px;">
        Menampilkan reservasi untuk cabang Anda.
      </div>
    </div>

    <?php if (empty($reservations)): ?>
      <div class="card">
        <div style="text-align:center; padding:40px 10px;">
          <i class="fa fa-inbox" style="font-size:54px; opacity:0.5;"></i>
          <div style="color:#ffd700; font-weight:bold; margin-top:10px;">Tidak ada reservasi</div>
          <div class="muted" style="margin-top:8px; font-size:13px;">Filter status: <b><?= status_label($status) ?></b></div>
        </div>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Tanggal &amp; Jam</th>
            <th>Meja</th>
            <th>Jumlah Orang</th>
            <th>Status</th>
            <th>Kasir/Pembuat</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
            <tr>
              <td>#<?= (int)$r['id'] ?></td>
              <td>
                <div style="font-weight:bold;"><?= e($r['customer_name']) ?></div>
                <?php if (!empty($r['phone'])): ?>
                  <div class="muted" style="font-size:12px;"><?= e($r['phone']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:bold;"><?= e($r['reservation_date']) ?></div>
                <div class="muted" style="font-size:12px;"><?= e($r['reservation_time']) ?></div>
              </td>
              <td><?= !empty($r['table_no']) ? e($r['table_no']) : '-' ?></td>
              <td><?= (int)$r['party_size'] ?></td>
              <td>
                <span class="pill <?= e($r['status']) ?>"><?= status_label($r['status']) ?></span>
              </td>
              <td class="muted"><?= e($r['creator_name'] ?: '-') ?></td>
              <td>
                <div class="actions">
                  <a class="btn info small" href="reservation_detail.php?id=<?= (int)$r['id'] ?>">
                    <i class="fa fa-eye"></i> Detail
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
</body>
</html>

