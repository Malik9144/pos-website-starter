<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';

try {
    auth_required(['admin','superadmin','spv','kasir']);
    $u = auth_user();
    $branch_id = $u['branch_id'];
    $user_id = $u['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    
    $order_id = (int)($data['order_id'] ?? 0);
    $items = $data['items'] ?? [];
    
    if($order_id <= 0 || empty($items)) {
        throw new Exception('Data tidak valid');
    }
    
    $db = db();
    $db->beginTransaction();
    
    try {
        // Cek apakah order masih pending dan milik branch yang sama
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND branch_id = ? AND status = 'pending'");
        $stmt->execute([$order_id, $branch_id]);
        $order = $stmt->fetch();
        
        if(!$order) {
            throw new Exception('Order tidak ditemukan atau sudah tidak bisa diubah');
        }
        
        // Hitung subtotal item baru
        $additional_subtotal = 0;
        foreach($items as $item) {
            $item_subtotal = $item['price'] * $item['qty'];
            $discount = $item_subtotal * ($item['disc']/100);
            $additional_subtotal += ($item_subtotal - $discount);
            
            // Insert item baru ke order_items
            $stmt = $db->prepare("INSERT INTO order_items 
                                (order_id, product_id, product_name, qty, price, quantity, discount) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $order_id, $item['id'], $item['name'], $item['qty'], 
                $item['price'], $item['qty'], $item['disc']
            ]);
        }
        
        // Update total order
        $new_subtotal = $order['subtotal'] + $additional_subtotal;
        $new_tax_amount = round($new_subtotal * $order['tax_percent'] / 100);
        $new_service_amount = round($new_subtotal * $order['service_percent'] / 100);
        $new_total = $new_subtotal + $new_tax_amount + $new_service_amount;
        
        $stmt = $db->prepare("UPDATE orders SET 
                            subtotal = ?, tax_amount = ?, service_amount = ?, total = ?
                            WHERE id = ?");
        $stmt->execute([$new_subtotal, $new_tax_amount, $new_service_amount, $new_total, $order_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Item berhasil ditambahkan ke order',
            'order_id' => $order_id,
            'new_total' => $new_total
        ]);
        
    } catch(Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch(Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
