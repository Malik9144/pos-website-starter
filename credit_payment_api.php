<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Autentikasi
    auth_required(['admin', 'superadmin', 'spv']);
    $user = auth_user();
    
    if (!$user) {
        throw new Exception('Authentication required');
    }

    // Get credit_id from query parameter
    if (!isset($_GET['credit_id'])) {
        throw new Exception('Parameter credit_id tidak ditemukan');
    }
    
    $credit_id = (int)$_GET['credit_id'];
    
    if ($credit_id <= 0) {
        throw new Exception('credit_id tidak valid');
    }
    
    // Verify credit belongs to user's branch (if not superadmin)
    if ($user['role'] !== 'superadmin') {
        $verify_sql = "SELECT id FROM credits WHERE id = ? AND branch_id = ?";
        $verify_stmt = db()->prepare($verify_sql);
        $verify_stmt->execute([$credit_id, $user['branch_id']]);
        
        if (!$verify_stmt->fetch()) {
            throw new Exception('Akses ditolak: Credit tidak ditemukan atau bukan dari cabang Anda');
        }
    }
    
    // Get payment history
    $sql = "SELECT 
                cp.id,
                cp.credit_id,
                cp.amount,
                cp.payment_method,
                cp.note,
                cp.created_at,
                u.name as user_name
            FROM credit_payments cp
            LEFT JOIN users u ON cp.user_id = u.id
            WHERE cp.credit_id = ?
            ORDER BY cp.created_at DESC";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([$credit_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_paid = 0;
    foreach ($payments as $payment) {
        $total_paid += floatval($payment['amount']);
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'credit_id' => $credit_id,
        'total_payments' => count($payments),
        'total_paid' => $total_paid,
        'payments' => $payments
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
