<?php
require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';

auth_required();
$u = auth_user();

// Get parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_filter = $_GET['branch'] ?? ($u['role'] === 'superadmin' ? 'all' : $u['branch_id']);
$export_format = $_GET['export'] ?? 'csv';

// Build branch condition
$branch_condition = '';
$branch_params = [];
if ($u['role'] !== 'superadmin') {
    $branch_condition = ' AND o.branch_id = ?';
    $branch_params[] = $u['branch_id'];
} elseif ($branch_filter !== 'all') {
    $branch_condition = ' AND o.branch_id = ?';
    $branch_params[] = (int)$branch_filter;
}

// Query data sama seperti di report utama
$sales_summary_query = "
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as total_sales,
        COALESCE(AVG(CASE WHEN o.status = 'paid' THEN o.total END), 0) as avg_transaction
    FROM orders o 
    WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
";

$stmt = db()->prepare($sales_summary_query);
$stmt->execute(array_merge([$date_from, $date_to], $branch_params));
$sales_summary = $stmt->fetch();

// Daily breakdown
$daily_breakdown_query = "
    SELECT 
        DATE(o.created_at) as sale_date,
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as daily_sales,
        COALESCE(SUM(CASE WHEN o.payment_method = 'cash' AND o.status = 'paid' THEN o.total END), 0) as cash_sales,
        COALESCE(SUM(CASE WHEN o.payment_method = 'qris' AND o.status = 'paid' THEN o.total END), 0) as qris_sales,
        COALESCE(SUM(CASE WHEN o.payment_method = 'credit' OR o.status = 'credit' THEN o.total END), 0) as credit_sales,
        COALESCE(AVG(CASE WHEN o.status = 'paid' THEN o.total END), 0) as avg_transaction
    FROM orders o 
    WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
    GROUP BY DATE(o.created_at)
    ORDER BY sale_date DESC
";

$stmt = db()->prepare($daily_breakdown_query);
$stmt->execute(array_merge([$date_from, $date_to], $branch_params));
$daily_breakdown = $stmt->fetchAll();

// Calculate metrics
$total_revenue = $sales_summary['total_sales'] ?? 0;
$gross_profit = $total_revenue * 0.3; // Estimasi 30% profit
$profit_margin = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;

// Get branch name
$current_branch_name = 'All Branches';
if ($u['role'] !== 'superadmin') {
    $branch_stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
    $branch_stmt->execute([$u['branch_id']]);
    $branch_data = $branch_stmt->fetch();
    $current_branch_name = $branch_data['name'] ?? 'Cabang ' . $u['branch_id'];
} elseif ($branch_filter !== 'all') {
    $branch_stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
    $branch_stmt->execute([$branch_filter]);
    $branch_data = $branch_stmt->fetch();
    $current_branch_name = $branch_data['name'] ?? 'Cabang ' . $branch_filter;
}

// Export
if ($export_format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo "Laporan Keuangan - $current_branch_name\n";
    echo "Periode: $date_from - $date_to\n\n";
    
    echo "RINGKASAN PENJUALAN\n";
    echo "Total Penjualan,Rp " . number_format($total_revenue, 0, ',', '.') . "\n";
    echo "Laba Kotor,Rp " . number_format($gross_profit, 0, ',', '.') . "\n";
    echo "Margin Laba," . number_format($profit_margin, 2) . "%\n";
    echo "Total Transaksi," . ($sales_summary['total_orders'] ?? 0) . "\n";
    echo "Rata-rata Transaksi,Rp " . number_format($sales_summary['avg_transaction'] ?? 0, 0, ',', '.') . "\n\n";
    
    echo "ANALISIS HARIAN\n";
    echo "Tanggal,Total Orders,Penjualan,Tunai,QRIS,Kredit,Rata-rata\n";
    foreach ($daily_breakdown as $day) {
        echo $day['sale_date'] . "," . $day['total_orders'] . ",Rp " . number_format($day['daily_sales'], 0, ',', '.') . ",Rp " . number_format($day['cash_sales'], 0, ',', '.') . ",Rp " . number_format($day['qris_sales'], 0, ',', '.') . ",Rp " . number_format($day['credit_sales'], 0, ',', '.') . ",Rp " . number_format($day['avg_transaction'], 0, ',', '.') . "\n";
    }
}

if ($export_format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="financial_report_' . date('Y-m-d') . '.xls"');
    
    echo "\xEF\xBB\xBF";
    echo "<table border='1'>";
    echo "<tr><td colspan='6'><b>Laporan Keuangan - $current_branch_name</b></td></tr>";
    echo "<tr><td colspan='6'>Periode: $date_from - $date_to</td></tr>";
    echo "<tr><td></td></tr>";
    
    echo "<tr><td><b>Metrik</b></td><td><b>Nilai</b></td></tr>";
    echo "<tr><td>Total Penjualan</td><td>Rp " . number_format($total_revenue, 0, ',', '.') . "</td></tr>";
    echo "<tr><td>Laba Kotor</td><td>Rp " . number_format($gross_profit, 0, ',', '.') . "</td></tr>";
    echo "<tr><td>Margin Laba</td><td>" . number_format($profit_margin, 2) . "%</td></tr>";
    echo "<tr><td>Total Transaksi</td><td>" . ($sales_summary['total_orders'] ?? 0) . "</td></tr>";
    echo "</table>";
}
?>
