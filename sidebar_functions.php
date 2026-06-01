<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

// Pastikan user login dan punya role
auth_required(['kasir','spv','admin','superadmin','spv_warehouse']);

// Ambil nama cabang
if (!function_exists('getBranchName')) {
    function getBranchName($branch_id) {
        $stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
        $stmt->execute([$branch_id]);
        $row = $stmt->fetch();
        return $row ? $row['name'] : 'Unknown';
    }
}

// Alias supaya kompatibel
if (!function_exists('get_auth_user')) {
    function get_auth_user() {
        return auth_user(); // dari lib/auth.php
    }
}
