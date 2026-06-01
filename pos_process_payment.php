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

    if (!isset($data['order_id'])) {
        throw new Exception('Order ID tidak ditemukan');
    }

    $order_id = (int)$data['order_id'];
    $method = $data['method'] ?? 'cash';

    $db = db();

    // Cek order ada dan status pending
    $order = $db->prepare("SELECT * FROM orders WHERE id = ? AND branch_id = ? AND status = 'pending'");
    $order->execute([$order_id, $branch_id]);
    $order_data = $order->fetch(PDO::FETCH_ASSOC);

    if (!$order_data) {
        throw new Exception('Order tidak ditemukan atau sudah dibayar');
    }

    $db->beginTransaction();

    try {
        // Update status order ke 'paid' atau 'credit' tergantung payment method
        $payment_method = ($method === 'credit') ? 'credit' : $method;
        $stmt = $db->prepare("
            UPDATE orders 
            SET payment_method = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$method, ($method === 'credit' ? 'credit' : 'paid'), $order_id]);

        // Jika metode kredit, insert record baru di credits
        if ($method === 'credit' && isset($data['employee_id'])) {
            // Cari nama employee dulu tanpa filter branch_id
            $stmt_emp = $db->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt_emp->execute([$data['employee_id']]);
            $emp = $stmt_emp->fetch();

            if (!$emp) {
                throw new Exception('Data karyawan tidak ditemukan');
            }

            $employee_name = $emp['name'];
            $customer_name = $data['customer_name'] ?? '';
            $total_amount = $order_data['total'];
            $user_id = $u['id'];

            $stmt_credit = $db->prepare("
                INSERT INTO credits 
                (order_id, employee_id, employee_name, customer_name, total_amount, paid_amount, status, branch_id, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, 'unpaid', ?, ?, NOW())
            ");
            $stmt_credit->execute([
                $order_id,
                $data['employee_id'],
                $employee_name,
                $customer_name,
                $total_amount,
                $branch_id,
                $user_id
            ]);
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'total' => $order_data['total'],
            'message' => 'Pembayaran berhasil diproses'
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
