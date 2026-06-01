<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Login user - Tanpa kolom active
 */
function auth_login($email, $password) {
    try {
        $st = db()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'branch_id' => $user['branch_id']
            ];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            return $_SESSION['user'];
        }
    } catch (Exception $e) {
        error_log("Auth login error: " . $e->getMessage());
    }
    return false;
}

/**
 * Change password - TANPA VERIFIKASI PASSWORD LAMA
 */
function auth_change_password($user_id, $new_password) {
    try {
        // Validasi input
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'Password baru minimal 6 karakter!'];
        }

        // Langsung hash password baru dan update
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        
        if ($update->execute([$new_hash, $user_id])) {
            return ['success' => true, 'message' => 'Password berhasil diganti!'];
        }
        return ['success' => false, 'message' => 'Gagal mengganti password!'];
        
    } catch (Exception $e) {
        error_log("Auth change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem!'];
    }
}

/**
 * Logout user
 */
function auth_logout() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}

/**
 * Ambil user yang sedang login
 */
function auth_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Alias supaya lebih singkat
 */
function me() {
    return auth_user();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return auth_user() !== null;
}

/**
 * Wajib login + cek role - DENGAN TAMPILAN ERROR YANG JELAS
 */
function auth_required($roles = []) {
    $u = auth_user();
    if (!$u) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
    if ($roles && !in_array($u['role'], $roles)) {
        // Tampilkan halaman error yang jelas dan menarik
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <title>Akses Ditolak</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0; padding: 0; min-height: 100vh;
                    display: flex; align-items: center; justify-content: center;
                }
                .error-container { 
                    max-width: 500px; background: white; 
                    padding: 40px 30px; border-radius: 15px; 
                    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                    text-align: center; animation: fadeIn 0.5s ease;
                }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
                .error-icon { font-size: 80px; color: #e74c3c; margin-bottom: 20px; }
                .error-title { color: #2c3e50; font-size: 28px; font-weight: bold; margin-bottom: 15px; }
                .error-message { color: #555; font-size: 16px; line-height: 1.6; margin-bottom: 25px; }
                .role-info { 
                    background: #f8f9fa; padding: 15px; border-radius: 8px; 
                    margin: 20px 0; border-left: 4px solid #e74c3c;
                }
                .role-current { color: #e74c3c; font-weight: bold; }
                .role-required { color: #27ae60; font-weight: bold; }
                .back-button { 
                    background: linear-gradient(45deg, #667eea, #764ba2);
                    color: white; padding: 12px 25px; text-decoration: none; 
                    border-radius: 25px; display: inline-block; font-weight: bold;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .back-button:hover { 
                    transform: translateY(-2px); 
                    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
                }
                .logout-button {
                    background: #e74c3c; color: white; padding: 10px 20px;
                    text-decoration: none; border-radius: 20px; margin-left: 10px;
                    font-weight: bold; transition: background 0.2s;
                }
                .logout-button:hover { background: #c0392b; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="error-title">Akses Ditolak</div>
                <div class="error-message">
                    Maaf, Anda tidak memiliki izin untuk mengakses halaman ini.
                </div>
                <div class="role-info">
                    <div><strong>Role Anda saat ini:</strong> <span class="role-current"><?= htmlspecialchars($u['role']) ?></span></div>
                    <div style="margin-top:8px;"><strong>Role yang diizinkan:</strong> <span class="role-required"><?= implode(', ', $roles) ?></span></div>
                </div>
                <div style="margin-top:30px;">
                    <a href="dashboard.php" class="back-button">
                        <i class="fas fa-home"></i> Kembali ke Dashboard
                    </a>
                    <a href="logout.php" class="logout-button">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    return $u;
}

/**
 * Get user full data from database
 */
function auth_refresh_user() {
    $current_user = auth_user();
    if (!$current_user) {
        return false;
    }
    
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$current_user['id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user'] = [
                'id'        => $user['id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'branch_id' => $user['branch_id']
            ];
            return $_SESSION['user'];
        }
    } catch (Exception $e) {
        error_log("Auth refresh user error: " . $e->getMessage());
    }
    
    return false;
}
?>