<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';

auth_required(['admin','superadmin','spv']);
$u = auth_user();

// Get filter parameters (sama seperti di manage_credits.php)
$status_filter = $_GET['status'] ?? '';
$customer_filter = $_GET['customer'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

// FILTER 1: Status
if ($status_filter) {
    $where_conditions[] = 'c.status = ?';
    $params[] = $status_filter;
}

// FILTER 2: Customer name
if ($customer_filter) {
    $where_conditions[] = 'c.customer_name LIKE ?';
    $params[] = "%$customer_filter%";
}

// FILTER 3: Date from
if ($date_from) {
    $where_conditions[] = 'DATE(c.created_at) >= ?';
    $params[] = $date_from;
}

// FILTER 4: Date to
if ($date_to) {
    $where_conditions[] = 'DATE(c.created_at) <= ?';
    $params[] = $date_to;
}

// FILTER 5: Branch (untuk SPV)
if ($u['role'] !== 'superadmin') {
    $where_conditions[] = 'c.branch_id = ?';
    $params[] = $u['branch_id'];
}

$where_clause = implode(' AND ', $where_conditions);

// Get credits data dengan join lengkap
$sql = "SELECT c.*, 
               b.name as branch_name, 
               u.name as user_name,
               o.id as order_id, 
               o.created_at as order_date,
               o.total as order_total
        FROM credits c
        LEFT JOIN branches b ON c.branch_id = b.id
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN orders o ON c.order_id = o.id
        WHERE $where_clause
        ORDER BY c.created_at DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk ambil payment history per credit
$payment_sql = "SELECT 
                    cp.created_at,
                    cp.amount,
                    cp.payment_method,
                    cp.note,
                    u.name as user_name
                FROM credit_payments cp
                LEFT JOIN users u ON cp.user_id = u.id
                WHERE cp.credit_id = ?
                ORDER BY cp.created_at ASC";
$payment_stmt = db()->prepare($payment_sql);

// Set header untuk download CSV
$filename = 'credits_export_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM untuk UTF-8 (agar Excel bisa baca karakter Indonesia)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// HEADER CSV
fputcsv($output, [
    'Credit ID',
    'Tanggal Kredit',
    'Order ID',
    'Tanggal Order',
    'Customer Name',
    'Employee Name',
    'Cabang',
    'Kasir Input',
    'Total Kredit',
    'Terbayar',
    'Sisa Hutang',
    'Progress (%)',
    'Status Kredit',
    // Payment history columns
    'Tanggal Bayar',
    'Jumlah Bayar',
    'Metode Bayar',
    'Kasir Bayar',
    'Keterangan Bayar'
]);

// Loop setiap credit
foreach ($credits as $credit) {
    $credit_id = $credit['id'];
    $total_amount = (float)$credit['total_amount'];
    $paid_amount = (float)$credit['paid_amount'];
    $remaining = $total_amount - $paid_amount;
    $progress = $total_amount > 0 ? round(($paid_amount / $total_amount) * 100, 1) : 0;
    
    // Status label
    $status_labels = [
        'unpaid' => 'Belum Bayar',
        'partial' => 'Cicilan',
        'paid' => 'Lunas',
        'cancelled' => 'Dibatalkan'
    ];
    $status_label = $status_labels[$credit['status']] ?? $credit['status'];
    
    // Get payment history untuk credit ini
    $payment_stmt->execute([$credit_id]);
    $payments = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Jika tidak ada payment, tetap tampilkan 1 baris credit info
    if (empty($payments)) {
        fputcsv($output, [
            $credit_id,
            $credit['created_at'],
            $credit['order_id'] ?: '-',
            $credit['order_date'] ?: '-',
            $credit['customer_name'],
            $credit['employee_name'] ?: '-',
            $credit['branch_name'],
            $credit['user_name'],
            $total_amount,
            $paid_amount,
            $remaining,
            $progress,
            $status_label,
            // Empty payment columns
            '', '', '', '', ''
        ]);
    } else {
        // Ada payment history - buat baris per payment
        foreach ($payments as $index => $payment) {
            fputcsv($output, [
                // Credit info (hanya di baris pertama)
                $index === 0 ? $credit_id : '',
                $index === 0 ? $credit['created_at'] : '',
                $index === 0 ? ($credit['order_id'] ?: '-') : '',
                $index === 0 ? ($credit['order_date'] ?: '-') : '',
                $index === 0 ? $credit['customer_name'] : '',
                $index === 0 ? ($credit['employee_name'] ?: '-') : '',
                $index === 0 ? $credit['branch_name'] : '',
                $index === 0 ? $credit['user_name'] : '',
                $index === 0 ? $total_amount : '',
                $index === 0 ? $paid_amount : '',
                $index === 0 ? $remaining : '',
                $index === 0 ? $progress : '',
                $index === 0 ? $status_label : '',
                // Payment info (setiap baris)
                $payment['created_at'],
                (float)$payment['amount'],
                strtoupper($payment['payment_method']),
                $payment['user_name'] ?: '-',
                $payment['note'] ?: '-'
            ]);
        }
    }
}

fclose($output);
exit;
