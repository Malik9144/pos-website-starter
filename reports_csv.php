<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/utils.php';

auth_required(['admin','superadmin','spv','kasir']);
$u = me();

$from   = $_GET['from'] ?? date('Y-m-01');
$to     = $_GET['to'] ?? date('Y-m-d');
$branch = (int)($_GET['branch'] ?? 0);

$params = [$from, $to];
$where  = ' WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status != "cancelled"';
if ($branch > 0) {
    $where .= ' AND o.branch_id = ?';
    $params[] = $branch;
}

// Ambil data order + user + branch + cash transactions + credits
$sql = "SELECT 
            o.id,
            o.status,
            o.total,
            o.tax_percent,
            o.tax_value,
            o.service_percent,
            o.service_value,
            o.payment_method,
            o.customer_name,
            o.employee_name,
            o.order_type,
            o.table_no,
            o.created_at,
            u.name AS cashier,
            b.name AS branch,
            ct.cash_given,
            ct.change_amount,
            c.id AS credit_id,
            c.total_amount AS credit_total,
            c.paid_amount AS credit_paid,
            c.status AS credit_status
        FROM orders o
        JOIN users u ON u.id = o.user_id
        JOIN branches b ON b.id = o.branch_id
        LEFT JOIN cash_transactions ct ON ct.order_id = o.id
        LEFT JOIN credits c ON c.order_id = o.id
        $where
        ORDER BY o.created_at DESC";
$st = db()->prepare($sql);
$st->execute($params);
$orders = $st->fetchAll();

$sql_items = "SELECT oi.*, p.sku, p.name 
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?";
$st_items = db()->prepare($sql_items);

// Query untuk credit payments
$sql_credit_payments = "SELECT 
    cp.created_at,
    cp.amount,
    cp.payment_method,
    cp.note,
    u.name as user_name
FROM credit_payments cp
LEFT JOIN users u ON cp.user_id = u.id
WHERE cp.credit_id = ?
ORDER BY cp.created_at ASC";
$st_credit_payments = db()->prepare($sql_credit_payments);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="orders_complete_report.csv"');

$out = fopen('php://output', 'w');

// BOM untuk UTF-8 agar Excel bisa baca karakter Indonesia dengan benar
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// --- HEADER CSV dengan kolom kredit ---
fputcsv($out, [
    'Order ID',
    'Status Order',
    'Tanggal Order',
    'Cabang',
    'Kasir',
    'Tipe Order',
    'No Meja',
    'Nama Customer',
    'Nama Karyawan',
    'Metode Pembayaran',
    'Nama Produk',
    'SKU',
    'Qty Terjual',
    'Harga Satuan',
    'Diskon (%)',
    'Subtotal Item',
    'Subtotal Order',
    'Pajak (%)',
    'Nilai Pajak',
    'Service (%)',
    'Nilai Service',
    'Total Order',
    'Stok Keluar',
    // Kolom Cash Transaction
    'Uang Diterima (Cash)',
    'Kembalian',
    'Cash Flow Bersih',
    // Kolom Credit
    'Status Kredit',
    'Total Hutang',
    'Terbayar',
    'Sisa Hutang',
    'Progress Bayar (%)',
    // Kolom Credit Payment Details
    'Riwayat Pembayaran - Tanggal',
    'Riwayat Pembayaran - Jumlah',
    'Riwayat Pembayaran - Metode',
    'Riwayat Pembayaran - Kasir',
    'Riwayat Pembayaran - Keterangan'
]);

foreach ($orders as $o) {
    $st_items->execute([$o['id']]);
    $items = $st_items->fetchAll();

    $subtotal_order = 0;
    foreach ($items as $it) {
        $sub_brg = $it['price'] * $it['qty'];
        $diskon_persen = floatval($it['discount']);
        $diskon_brg = round($sub_brg * $diskon_persen / 100);
        $subtotal_item = $sub_brg - $diskon_brg;
        $subtotal_order += $subtotal_item;
    }

    // Data cash transaction
    $cash_given = floatval($o['cash_given'] ?? 0);
    $change_amount = floatval($o['change_amount'] ?? 0);
    $cash_flow = ($cash_given > 0) ? ($cash_given - $change_amount) : 0;

    // Data kredit
    $is_credit = ($o['payment_method'] === 'credit');
    $credit_total = floatval($o['credit_total'] ?? 0);
    $credit_paid = floatval($o['credit_paid'] ?? 0);
    $credit_remaining = $credit_total - $credit_paid;
    $credit_progress = ($credit_total > 0) ? round(($credit_paid / $credit_total) * 100, 1) : 0;
    
    // Status kredit label
    $credit_status_label = '';
    if ($is_credit && $o['credit_status']) {
        $status_map = [
            'unpaid' => 'Belum Bayar',
            'partial' => 'Cicilan',
            'paid' => 'Lunas',
            'cancelled' => 'Dibatalkan'
        ];
        $credit_status_label = $status_map[$o['credit_status']] ?? $o['credit_status'];
    }

    // Ambil riwayat pembayaran kredit
    $credit_payments = [];
    if ($is_credit && $o['credit_id']) {
        $st_credit_payments->execute([$o['credit_id']]);
        $credit_payments = $st_credit_payments->fetchAll();
    }

    // Jika tidak ada riwayat pembayaran, buat 1 baris saja
    if (empty($credit_payments)) {
        $credit_payments = [null]; // Dummy untuk loop
    }

    // Loop untuk setiap item produk dan payment history
    foreach ($items as $item_idx => $it) {
        $sub_brg = $it['price'] * $it['qty'];
        $diskon_persen = floatval($it['discount']);
        $diskon_brg = round($sub_brg * $diskon_persen / 100);
        $subtotal_item = $sub_brg - $diskon_brg;

        // Tampilkan rekap hanya di item terakhir
        $show_rekap = ($item_idx === count($items) - 1);

        foreach ($credit_payments as $payment_idx => $payment) {
            // Tampilkan data order hanya di baris pertama kombinasi item+payment
            $show_order_data = ($item_idx === 0 && $payment_idx === 0);
            $show_item_data = ($payment_idx === 0);
            $show_payment_data = ($item_idx === 0);

            fputcsv($out, [
                // Data Order
                $show_order_data ? $o['id'] : '',
                $show_order_data ? strtoupper($o['status']) : '',
                $show_order_data ? $o['created_at'] : '',
                $show_order_data ? $o['branch'] : '',
                $show_order_data ? $o['cashier'] : '',
                $show_order_data ? ($o['order_type']=='dinein'?'Dine In':($o['order_type']=='takeaway'?'Take Away':'')) : '',
                $show_order_data ? $o['table_no'] : '',
                $show_order_data ? $o['customer_name'] : '',
                $show_order_data ? $o['employee_name'] : '',
                $show_order_data ? strtoupper($o['payment_method']) : '',
                
                // Data Item
                $show_item_data ? $it['name'] : '',
                $show_item_data ? $it['sku'] : '',
                $show_item_data ? $it['qty'] : '',
                $show_item_data ? $it['price'] : '',
                $show_item_data ? (($diskon_persen > 0) ? $diskon_persen : '') : '',
                $show_item_data ? $subtotal_item : '',
                
                // Rekap Order
                ($show_rekap && $show_item_data) ? $subtotal_order : '',
                ($show_rekap && $show_item_data) ? $o['tax_percent'] : '',
                ($show_rekap && $show_item_data) ? $o['tax_value'] : '',
                ($show_rekap && $show_item_data) ? $o['service_percent'] : '',
                ($show_rekap && $show_item_data) ? $o['service_value'] : '',
                ($show_rekap && $show_item_data) ? $o['total'] : '',
                $show_item_data ? $it['qty'] : '',  // Stok Keluar
                
                // Cash Transaction
                ($show_rekap && $show_item_data && $cash_given > 0) ? $cash_given : '',
                ($show_rekap && $show_item_data && $change_amount > 0) ? $change_amount : '',
                ($show_rekap && $show_item_data && $cash_flow > 0) ? $cash_flow : '',
                
                // Credit Data
                ($show_rekap && $show_item_data && $is_credit) ? $credit_status_label : '',
                ($show_rekap && $show_item_data && $is_credit) ? $credit_total : '',
                ($show_rekap && $show_item_data && $is_credit) ? $credit_paid : '',
                ($show_rekap && $show_item_data && $is_credit) ? $credit_remaining : '',
                ($show_rekap && $show_item_data && $is_credit) ? $credit_progress : '',
                
                // Credit Payment History
                ($show_payment_data && $payment) ? $payment['created_at'] : '',
                ($show_payment_data && $payment) ? $payment['amount'] : '',
                ($show_payment_data && $payment) ? strtoupper($payment['payment_method']) : '',
                ($show_payment_data && $payment) ? $payment['user_name'] : '',
                ($show_payment_data && $payment) ? $payment['note'] : ''
            ]);
        }
    }
}
// === Summary Ringkasan Laporan (format mirip ticket_reports_csv.php) ===
fputcsv($out, []); // baris kosong
fputcsv($out, ['RINGKASAN LAPORAN']);
fputcsv($out, []);

$total_orders = count($orders);
$total_revenue = 0;
$total_cash_given = 0;
$total_change = 0;
$total_qris = 0;
$total_credit = 0;
$cash_orders = 0;
$qris_orders = 0;
$credit_orders = 0;
$pending_orders = 0;
$paid_orders = 0;
$total_tax = 0;

foreach ($orders as $order) {
    $total_revenue += floatval($order['total']);
    $total_tax += floatval($order['tax_value']);
    if ($order['status'] === 'pending') {
        $pending_orders++;
    } else {
        $paid_orders++;
    }
    switch ($order['payment_method']) {
        case 'cash':
            $cash_orders++;
            $cash_given = floatval($order['cash_given'] ?? $order['total']);
            $change = floatval($order['change_amount'] ?? 0);
            $total_cash_given += $cash_given;
            $total_change += $change;
            break;
        case 'qris': $qris_orders++; $total_qris += floatval($order['total']); break;
        case 'credit': $credit_orders++; $total_credit += floatval($order['total']); break;
    }
}
$net_cash_flow = $total_cash_given - $total_change;

fputcsv($out, ['Total Orders', $total_orders]);
fputcsv($out, ['Total Revenue', 'Rp ' . number_format($total_revenue, 0, ',', '.')]);
fputcsv($out, ['Total Pajak', 'Rp ' . number_format($total_tax, 0, ',', '.')]);
fputcsv($out, []);
fputcsv($out, ['Status Pembayaran']);
fputcsv($out, ['Lunas (Paid)', $paid_orders]);
fputcsv($out, ['Pending (Unpaid)', $pending_orders]);
fputcsv($out, []);
fputcsv($out, ['Metode Pembayaran']);
fputcsv($out, ['Tunai', $cash_orders, 'Rp ' . number_format($total_cash_given, 0, ',', '.')]);
fputcsv($out, ['QRIS', $qris_orders, 'Rp ' . number_format($total_qris, 0, ',', '.')]);
fputcsv($out, ['Kredit', $credit_orders, 'Rp ' . number_format($total_credit, 0, ',', '.')]);
fputcsv($out, []);
fputcsv($out, ['Cash Flow']);
fputcsv($out, ['Total Uang Diterima (Tunai)', 'Rp ' . number_format($total_cash_given, 0, ',', '.')]);
fputcsv($out, ['Total Kembalian', 'Rp ' . number_format($total_change, 0, ',', '.')]);
fputcsv($out, ['Net Cash Flow', 'Rp ' . number_format($net_cash_flow, 0, ',', '.')]);
fputcsv($out, []);
fputcsv($out, ['Total QRIS', 'Rp ' . number_format($total_qris, 0, ',', '.')]);
fputcsv($out, ['Total Kredit', 'Rp ' . number_format($total_credit, 0, ',', '.')]);
fputcsv($out, []);
fputcsv($out, ['Periode', "$from s/d $to"]);
fputcsv($out, ['Exported at', date('Y-m-d H:i:s')]);
if (isset($u) && !empty($u['name'])) {
    fputcsv($out, ['Exported by', $u['name']]);
}
fclose($out);
exit;

