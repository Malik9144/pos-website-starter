<?php
// src/lib/utils.php

// escape string biar aman dipakai di HTML
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// format angka jadi Rupiah
if (!function_exists('money')) {
    function money($num) {
        return number_format((int)$num, 0, ',', '.');
    }
}

// fungsi upload image
if (!function_exists('upload_image')) {
    function upload_image($field, $targetDir) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // cek folder, kalau belum ada buat
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // validasi ekstensi (hanya gambar)
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            throw new Exception("Format file tidak diizinkan!");
        }

        // validasi ukuran (max 2MB)
        if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
            throw new Exception("Ukuran file maksimal 2MB!");
        }

        // buat nama file unik
        $fileName = uniqid().".".$ext;
        $targetFile = rtrim($targetDir,'/\\').DIRECTORY_SEPARATOR.$fileName;

        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $targetFile)) {
            throw new Exception("Gagal upload file!");
        }

        return $fileName;
    }
}

// fungsi json_response untuk return API
if (!function_exists('json_response')) {
    function json_response($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
