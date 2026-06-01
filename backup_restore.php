<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
// Sidebar WAJIB sebelum konten utama!
require_once __DIR__ . '/../src/nav/sidebar.php';

auth_required(['superadmin']);

define('BACKUP_DIR', 'D:/xampp/htdocs/pos-web-starter/backups/');
define('MYSQLDUMP_PATH', 'D:/xampp/mysql/bin/mysqldump.exe');
define('MYSQL_PATH',     'D:/xampp/mysql/bin/mysql.exe');

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);
    if (!is_writable(BACKUP_DIR)) {
        $err = 'Folder backup tidak bisa ditulis.';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'backup') {
            $file = 'backup_' . date('Y_m_d_H_i_s') . '.sql';
            $path = BACKUP_DIR . $file;
            $cmd = sprintf(
                '%s -u %s %s %s > %s',
                MYSQLDUMP_PATH,
                DB_USER,
                DB_PASS === '' ? '' : '-p"'.DB_PASS.'"',
                DB_NAME,
                $path
            );
            exec($cmd, $out, $ret);
            $msg = $ret === 0 ? "Backup berhasil: $file" : 'Backup gagal.';
        }
        if (!empty($_FILES['restore_file']['tmp_name']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['restore_file']['tmp_name'];
            $cmd = sprintf(
                '%s -u %s %s %s < %s',
                MYSQL_PATH,
                DB_USER,
                DB_PASS === '' ? '' : '-p"'.DB_PASS.'"',
                DB_NAME,
                $tmp
            );
            exec($cmd, $out, $ret);
            $msg = $ret === 0 ? 'Restore berhasil.' : 'Restore gagal.';
        }
    }
}

$files = [];
if (is_dir(BACKUP_DIR)) {
    foreach (scandir(BACKUP_DIR) as $f) {
        if (preg_match('/\.sql$/', $f)) $files[] = $f;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Backup & Restore – <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { margin:0; font-family:'Segoe UI',sans-serif; background:#064420; color:#fff; margin-left:240px; }
.container { padding:30px; max-width:800px; }
h2 { color:#ffd700; margin-bottom:20px; }
.card { background:#0b6e4f; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.2); margin-bottom:30px; }
.btn { padding:10px 20px; border:none; border-radius:6px; cursor:pointer; font-weight:bold; }
.btn-backup { background:#ffd700; color:#064420; }
.btn-restore{ background:#e67e22; color:#fff; }
.alert { padding:10px; border-radius:6px; margin-bottom:15px; }
.alert.success { background:#27ae60; color:#fff; }
.alert.error   { background:#e74c3c; color:#fff; }
ul { list-style:none; padding:0; }
li{ background:#215f46; padding:10px; margin:8px 0; border-radius:6px; display:flex; justify-content:space-between; align-items:center; }
li a { background:#27ae60; color:#fff; padding:6px 12px; border-radius:4px; text-decoration:none; }
input[type=file] { margin-bottom:10px; }
/* Overlay Spinner */
#overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); display:none; align-items:center; justify-content:center; z-index:1000; }
.spinner { border:8px solid #f3f3f3; border-top:8px solid #ffd700; border-radius:50%; width:60px; height:60px; animation: spin 1s linear infinite;}
@keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }
#overlay p { color:#fff; margin-top:15px; font-size:16px; }
</style>
</head>
<body>
<div id="overlay">
  <div>
    <div class="spinner"></div>
    <p>Mohon tunggu, proses sedang berjalan...</p>
  </div>
</div>
<div class="container">
  <h2><i class="fas fa-database"></i> Backup & Restore Database</h2>
  <?php if ($msg): ?><div class="alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert error"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>
  <div class="card">
    <h3><i class="fas fa-arrow-down"></i> Backup</h3>
    <form id="backupForm" method="post">
      <input type="hidden" name="action" value="backup">
      <button type="submit" class="btn btn-backup"><i class="fas fa-file-export"></i> Buat Backup</button>
    </form>
  </div>
  <div class="card">
    <h3><i class="fas fa-upload"></i> Restore</h3>
    <form id="restoreForm" method="post" enctype="multipart/form-data" novalidate>
      <input type="file" name="restore_file" accept=".sql" required>
      <button type="submit" class="btn btn-restore"><i class="fas fa-file-import"></i> Mulai Restore</button>
    </form>
  </div>
  <div class="card">
    <h3><i class="fas fa-archive"></i> Daftar Backup</h3>
    <?php if ($files): ?>
      <ul>
        <?php foreach ($files as $f): ?>
        <li>
          <span><?= htmlspecialchars($f) ?></span>
          <a href="../backups/<?= urlencode($f) ?>" download><i class="fas fa-download"></i> Unduh</a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p>Tidak ada backup tersedia.</p>
    <?php endif; ?>
  </div>
</div>
<script>
document.getElementById('backupForm').addEventListener('submit',function(){
  document.getElementById('overlay').style.display = 'flex';
});
document.getElementById('restoreForm').addEventListener('submit',function(e){
  var fi = this.querySelector('input[type="file"]');
  if (!fi.files.length) { e.preventDefault(); return; }
  document.getElementById('overlay').style.display = 'flex';
});
</script>
</body>
</html>
