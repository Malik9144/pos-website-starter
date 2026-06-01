<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';

// Pastikan user sudah login
if (!auth_user()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Ambil data user yang sedang login
$current_user = auth_user();
$user_id = $current_user['id'];

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new       = trim($_POST['new_password'] ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');

    // Validasi input
    if (empty($new) || empty($confirm)) {
        $msg = "Semua field harus diisi!";
        $msg_type = 'error';
    }
    // Validasi panjang password baru
    elseif (strlen($new) < 6) {
        $msg = "Password baru minimal 6 karakter!";
        $msg_type = 'error';
    }
    // Validasi konfirmasi password
    elseif ($new !== $confirm) {
        $msg = "Konfirmasi password baru tidak sama!";
        $msg_type = 'error';
    }
    else {
        // Gunakan function auth_change_password yang sudah disederhanakan
        $result = auth_change_password($user_id, $new);
        $msg = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Ganti Password - <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'POS System') ?></title>
 <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body { 
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
      background: linear-gradient(135deg, #064420, #0b6e4f);
      color: #fff;
      margin: 0;
      padding: 20px;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .container {
      background: linear-gradient(135deg, #0b6e4f, #085c3a);
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 15px 50px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 450px;
      position: relative;
      overflow: hidden;
    }
    
    .container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #ffd700, #ffed4e, #ffd700);
    }
    
    .header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .header h1 {
      margin: 0 0 10px 0;
      color: #ffd700;
      font-size: 28px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }
    
    .header p {
      margin: 0;
      color: #ccc;
      font-size: 14px;
    }
    
    .user-info {
      background: rgba(255, 215, 0, 0.1);
      padding: 20px;
      border-radius: 12px;
      border-left: 4px solid #ffd700;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .user-avatar {
      font-size: 48px;
      color: #ffd700;
      margin-bottom: 15px;
    }
    
    .user-name {
      font-size: 20px;
      font-weight: 600;
      color: #ffd700;
      margin-bottom: 5px;
    }
    
    .user-role {
      font-size: 12px;
      color: #ccc;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .form-group {
      margin-bottom: 25px;
      position: relative;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #ffd700;
      font-weight: 600;
      font-size: 14px;
    }
    
    .input-wrapper {
      position: relative;
    }
    
    .form-group input {
      width: 100%;
      padding: 15px 50px 15px 20px;
      border: 2px solid transparent;
      border-radius: 12px;
      box-sizing: border-box;
      font-size: 14px;
      background: rgba(255, 255, 255, 0.95);
      color: #333;
      transition: all 0.3s ease;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #ffd700;
      box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
      background: #fff;
    }
    
    .toggle-password {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      font-size: 16px;
      transition: color 0.3s ease;
    }
    
    .toggle-password:hover {
      color: #333;
    }
    
    .submit-btn {
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      color: #064420;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      font-size: 16px;
      transition: all 0.3s ease;
      margin-bottom: 20px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .submit-btn:hover {
      background: linear-gradient(135deg, #ffed4e, #ffd700);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(255, 215, 0, 0.3);
    }
    
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 25px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideInDown 0.5s ease-out;
    }
    
    .alert.success {
      background: linear-gradient(135deg, rgba(39, 174, 96, 0.2), rgba(46, 204, 113, 0.2));
      border: 1px solid #27ae60;
      color: #fff;
    }
    
    .alert.error {
      background: linear-gradient(135deg, rgba(231, 76, 60, 0.2), rgba(192, 57, 43, 0.2));
      border: 1px solid #e74c3c;
      color: #fff;
    }
    
    .back-btn {
      width: 100%;
      padding: 12px;
      background: rgba(108, 117, 125, 0.2);
      color: #fff;
      border: 2px solid #6c757d;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      font-size: 14px;
      text-decoration: none;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s ease;
    }
    
    .back-btn:hover {
      background: rgba(108, 117, 125, 0.3);
      border-color: #5a6268;
      transform: translateY(-1px);
    }
    
    .notice {
      background: rgba(52, 152, 219, 0.1);
      border: 1px solid #3498db;
      color: #87ceeb;
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 25px;
      font-size: 13px;
      text-align: center;
    }
    
    @keyframes slideInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @media (max-width: 600px) {
      body { padding: 10px; }
      .container { padding: 30px 20px; }
      .header h1 { font-size: 24px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>
        <i class="fa fa-key"></i>
        Ganti Password
      </h1>
      <p>Reset password akun tanpa verifikasi - Sistem Lokal</p>
    </div>

    <div class="user-info">
      <div class="user-avatar">
        <i class="fa fa-user-circle"></i>
      </div>
      <div class="user-name"><?= htmlspecialchars($current_user['name']) ?></div>
      <div class="user-role"><?= htmlspecialchars($current_user['role']) ?></div>
    </div>

    <div class="notice">
      <i class="fa fa-info-circle"></i>
      Mode lokal: Tidak perlu verifikasi password lama
    </div>

    <?php if ($msg): ?>
      <div class="alert <?= $msg_type ?>" id="alertMessage">
        <i class="fa fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <form method="post" id="changePasswordForm">
      <div class="form-group">
        <label for="new_password">
          <i class="fa fa-key"></i> Password Baru
        </label>
        <div class="input-wrapper">
          <input type="password" id="new_password" name="new_password" 
                 placeholder="Masukkan password baru (min. 6 karakter)" required autofocus>
          <button type="button" class="toggle-password" data-target="new_password">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label for="confirm_password">
          <i class="fa fa-check-double"></i> Konfirmasi Password Baru
        </label>
        <div class="input-wrapper">
          <input type="password" id="confirm_password" name="confirm_password" 
                 placeholder="Ulangi password baru Anda" required>
          <button type="button" class="toggle-password" data-target="confirm_password">
            <i class="fa fa-eye"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="submit-btn" id="submitBtn">
        <i class="fa fa-save"></i> Ganti Password
      </button>
    </form>

    <a href="dashboard.php" class="back-btn">
      <i class="fa fa-arrow-left"></i>
      Kembali ke Dashboard
    </a>
  </div>

  <script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
      button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
          input.type = 'text';
          icon.className = 'fa fa-eye-slash';
        } else {
          input.type = 'password';
          icon.className = 'fa fa-eye';
        }
      });
    });

    // Password confirmation matching
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    function checkPasswordMatch() {
      const newPassword = newPasswordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      
      if (confirmPassword.length > 0) {
        if (newPassword === confirmPassword) {
          confirmPasswordInput.style.borderColor = '#27ae60';
        } else {
          confirmPasswordInput.style.borderColor = '#e74c3c';
        }
      } else {
        confirmPasswordInput.style.borderColor = 'transparent';
      }
    }
    
    newPasswordInput.addEventListener('input', checkPasswordMatch);
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);

    // Form validation
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
      const newPassword = newPasswordInput.value;
      const confirmPassword = confirmPasswordInput.value;
      
      if (newPassword !== confirmPassword) {
        e.preventDefault();
        confirmPasswordInput.style.borderColor = '#e74c3c';
        confirmPasswordInput.focus();
        alert('Konfirmasi password tidak sama!');
        return false;
      }
      
      // Show loading state
      const submitBtn = document.getElementById('submitBtn');
      submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
      submitBtn.disabled = true;
    });

    // Auto hide success message
    <?php if ($msg_type === 'success'): ?>
    setTimeout(() => {
      const alert = document.getElementById('alertMessage');
      if (alert) {
        alert.style.opacity = '0';
        setTimeout(() => {
          alert.style.display = 'none';
        }, 300);
      }
    }, 3000);
    <?php endif; ?>
  </script>
</body>
</html>
