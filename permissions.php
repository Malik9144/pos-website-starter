<?php
// src/lib/permissions.php

require_once __DIR__ . '/auth.php';

/**
 * Batasi akses kasir hanya ke halaman tertentu
 */
function restrict_for_kasir() {
    $u = auth_user();
    if ($u && $u['role'] === 'kasir') {
        // daftar halaman yang boleh diakses kasir
        $allowed = ['pos.php', 'logout.php'];
        $page = basename($_SERVER['PHP_SELF']);
        if (!in_array($page, $allowed)) {
            header("Location: pos.php");
            exit;
        }
    }
}
