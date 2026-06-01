<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';

try {
    auth_required(['admin','superadmin','spv','kasir']);
    $u = auth_user();
    $branch = $u['branch_id'];
    
    $order_id = (int)($_GET['id'] ?? 0);
    if($order_id <= 0) {
        throw new Exception('Order ID tidak valid');
    }
    
    // Ambil data order
    $stmt = db()->prepare("SELECT o.*, u.name as kasir_name 
                          FROM orders o 
                          LEFT JOIN users u ON o.user_id = u.id 
                          WHERE o.id = ? AND o.branch_id = ?");
    $stmt->execute([$order_id, $branch]);
    $order = $stmt->fetch();
    
    if(!$order) {
        throw new Exception('Order tidak ditemukan');
    }
    
    // Ambil items order
    $stmt = db()->prepare("SELECT oi.*, 
                          (oi.price * oi.quantity) as subtotal_before_discount,
                          (oi.price * oi.quantity) - ((oi.price * oi.quantity) * (oi.discount/100)) as subtotal
                          FROM order_items oi 
                          WHERE oi.order_id = ? 
                          ORDER BY oi.id");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
    
} catch(Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
