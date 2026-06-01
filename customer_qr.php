<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';

// Ini halaman penerima hasil scan QR meja.
// Praktik umum: isi QR berupa URL seperti:
//   /pos-web-starter/public/customer_qr.php?branch_id=2&amp;table_no=A1
// atau jika ingin: /customer_menu.php?branch_id=...&amp;table_no=...
//
// Kita akan redirect ke customer_menu.php dengan parameter yang sama.
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$table_no  = isset($_GET['table_no']) ? trim((string)$_GET['table_no']) : '';

if ($branch_id <= 0) {
  // fallback: redirect tanpa branch_id (akan default cabang 1)
  header('Location: customer_menu.php');
  exit;
}

$q = [];
$q[] = 'branch_id=' . urlencode((string)$branch_id);
if ($table_no !== '') {
  $q[] = 'table_no=' . urlencode($table_no);
}

header('Location: customer_menu.php?' . implode('&amp;', $q));
exit;
?>
