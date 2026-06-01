<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';

try {
    auth_required(['admin', 'superadmin', 'spv', 'kasir']);
    $u = auth_user();
    $branch_id = $u['branch_id'];
    
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = $data['order_id'] ?? 0;
    
    if (!$order_id) {
        throw new Exception('Order ID tidak valid');
    }
    
    $db = db();
    $db->beginTransaction();
    
    try {
        // Get order items untuk kembalikan stok
        $items_stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $items_stmt->execute([$order_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Kembalikan stok
        foreach ($items as $item) {
            $stmt_stock = $db->prepare("
                UPDATE stock_branch 
                SET quantity = quantity + ? 
                WHERE product_id = ? AND branch_id = ?
            ");
            $stmt_stock->execute([$item['quantity'], $item['product_id'], $branch_id]);
        }
        
        // Hapus order items
        $db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
        
        // Update status order jadi cancelled (atau hapus)
        $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND branch_id = ?")
           ->execute([$order_id, $branch_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order berhasil dibatalkan dan stok dikembalikan'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
