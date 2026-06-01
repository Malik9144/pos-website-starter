<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    echo '<input type="hidden" name="csrf_token" value="'.$token.'">';
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("CSRF token tidak valid!");
        }
    }
}
