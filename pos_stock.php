<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';

auth_required(['admin','superadmin','spv','kasir']);
$u = auth_user();
$branch = $u['branch_id'];

// Ambil stok produk per cabang
$stmt = db()->prepare("
    SELECT p.id, p.name, p.image, IFNULL(sb.quantity,0) AS stock
    FROM products p
    LEFT JOIN stock_branch sb 
      ON sb.product_id = p.id AND sb.branch_id = ?
    WHERE p.active=1 AND p.branch_id = ?
    ORDER BY p.name ASC
");
$stmt->execute([$branch, $branch]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kembalikan JSON
header('Content-Type: application/json');
echo json_encode(['products' => $products]);
