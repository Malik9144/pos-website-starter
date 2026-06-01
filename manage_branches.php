<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/csrf.php';
require_once __DIR__.'/../src/lib/utils.php';

auth_required(['admin','superadmin']);

// TAMBAH CABANG BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name']) && !isset($_POST['id'])) {
    csrf_check();
    $name = trim($_POST['name']);
    if (!empty($name)) {
        $stmt = db()->prepare('INSERT INTO branches(name) VALUES(?)');
        $stmt->execute([$name]);
        header('Location: manage_branches.php?msg=Cabang berhasil ditambahkan');
        exit;
    }
}

// EDIT CABANG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['name'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    
    if ($id > 0 && !empty($name)) {
        $stmt = db()->prepare('UPDATE branches SET name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
        header('Location: manage_branches.php?msg=Cabang berhasil diupdate');
        exit;
    }
}

// AMBIL SEMUA CABANG
$rows = db()->query('SELECT * FROM branches ORDER BY id')->fetchAll();
$u = auth_user();

// LOAD SIDEBAR SETELAH PROSES POST SELESAI
require_once __DIR__ . '/../src/nav/sidebar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Cabang - POS</title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #064420;
      color: #fff;
    }
    
    .container {
      margin-left: 240px;
      padding: 30px;
    }
    
    .card {
      background: #0b6e4f;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 5px 12px rgba(0,0,0,0.3);
      margin-bottom: 20px;
    }
    
    h2 {
      color: #ffd700;
      margin-top: 0;
      margin-bottom: 15px;
    }
    
    label {
      display: block;
      margin-top: 10px;
      margin-bottom: 5px;
      font-weight: 500;
    }
    
    input[type="text"] {
      width: 100%;
      padding: 10px;
      border-radius: 6px;
      border: none;
      font-size: 14px;
    }
    
    button {
      margin-top: 10px;
      padding: 10px 20px;
      background: #ffd700;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      color: #064420;
      font-size: 14px;
    }
    
    button:hover {
      background: #e6c200;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      background: #fff;
      color: #000;
      border-radius: 8px;
      overflow: hidden;
    }
    
    th, td {
      padding: 12px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }
    
    th {
      background: #064420;
      color: #ffd700;
      font-weight: bold;
    }
    
    tr:hover {
      background: #f9f9f9;
    }
    
    tr:last-child td {
      border-bottom: none;
    }
    
    .alert {
      padding: 12px 15px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-weight: 500;
    }
    
    .alert.success {
      background: #27ae60;
      color: #fff;
    }
    
    .alert.error {
      background: #e74c3c;
      color: #fff;
    }
    
    .btn-danger {
      background: #e74c3c;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
    }
    
    .btn-danger:hover {
      background: #c0392b;
    }
    
    .btn-warning {
      background: #f39c12;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
    }
    
    .btn-warning:hover {
      background: #e67e22;
    }
    
    .action-btns {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    
    @media (max-width: 800px) {
      .container {
        margin-left: 70px;
        padding: 18px;
      }
      
      .card {
        padding: 15px;
      }
      
      table {
        font-size: 13px;
      }
      
      th, td {
        padding: 8px;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <?php if (isset($_GET['msg'])): ?>
    <div class="alert success">
      <i class="fa fa-check-circle"></i> <?= e($_GET['msg']) ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['err'])): ?>
    <div class="alert error">
      <i class="fa fa-exclamation-circle"></i> <?= e($_GET['err']) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2><i class="fa fa-store"></i> Tambah Cabang Baru</h2>
    <form method="post">
      <?php csrf_field(); ?>
      <label for="branch_name">Nama Cabang</label>
      <input type="text" name="name" id="branch_name" required placeholder="Contoh: Cabang Jakarta Pusat">
      <button type="submit"><i class="fa fa-plus"></i> Tambah Cabang</button>
    </form>
  </div>

  <div class="card">
    <h2><i class="fa fa-list"></i> Daftar Cabang</h2>
    
    <?php if (empty($rows)): ?>
      <p style="color: #ffd700; text-align: center; padding: 20px;">
        <i class="fa fa-info-circle"></i> Belum ada cabang. Silakan tambahkan cabang baru.
      </p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width: 80px;">ID</th>
            <th>Nama Cabang</th>
            <th style="width: 200px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><strong><?= e($r['name']) ?></strong></td>
              <td>
                <div class="action-btns">
                  <button type="button" 
                          class="btn-warning"
                          data-id="<?= $r['id'] ?>"
                          data-name="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>"
                          onclick="editBranch(this)">
                    <i class="fa fa-edit"></i> Edit
                  </button>
                  
                  <form method="post" 
                        action="delete_branch.php" 
                        id="form-del-<?= $r['id'] ?>" 
                        style="display:inline; margin:0;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="button" 
                            class="btn-danger"
                            onclick="confirmDelete(<?= $r['id'] ?>,'<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>')">
                      <i class="fa fa-trash"></i> Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script src="/pos-web-starter/assets/js/sweetalert2.all.min.js"></script>
<script>
// EDIT BRANCH - Handle special characters safely
function editBranch(btn) {
  const id = btn.getAttribute('data-id');
  const currentName = btn.getAttribute('data-name');
  
  Swal.fire({
    title: 'Edit Nama Cabang',
    input: 'text',
    inputValue: currentName,
    inputPlaceholder: 'Masukkan nama cabang baru',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: '<i class="fa fa-save"></i> Simpan',
    cancelButtonText: 'Batal',
    inputValidator: (value) => {
      if (!value || value.trim() === '') {
        return 'Nama cabang tidak boleh kosong!'
      }
    }
  }).then((result) => {
    if (result.isConfirmed) {
      // Create and submit form
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'manage_branches.php';
      
      // CSRF token
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf_token';
      csrfInput.value = '<?= csrf_token() ?>';
      
      // Branch ID
      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = id;
      
      // Branch name
      const nameInput = document.createElement('input');
      nameInput.type = 'hidden';
      nameInput.name = 'name';
      nameInput.value = result.value.trim();
      
      form.appendChild(csrfInput);
      form.appendChild(idInput);
      form.appendChild(nameInput);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

// DELETE BRANCH
function confirmDelete(id, name) {
  Swal.fire({
    title: 'Hapus Cabang?',
    html: `Apakah Anda yakin ingin menghapus cabang:<br><strong>${name}</strong>?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: '<i class="fa fa-trash"></i> Ya, Hapus',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('form-del-' + id).submit();
    }
  });
}

// Auto hide alert after 3 seconds
window.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 3000);
  });
});
</script>

</body>
</html>
