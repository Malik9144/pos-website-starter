<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../src/lib/auth.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/nav/sidebar.php';
auth_required(); 
$u = auth_user();

// Helper function for escaping HTML
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_filter = $_GET['branch'] ?? ($u['role'] === 'superadmin' ? 'all' : $u['branch_id']);

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

// Ambil data cabang untuk filter (superadmin only)
$branches = [];
if ($u['role'] === 'superadmin') {
    try {
        $branches = db()->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching branches: " . $e->getMessage());
        $branches = [];
    }
}

// ==================== QUERY UNTUK BRANCH (KESELURUHAN) ====================
$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN o.payment_method = 'cash' AND o.status = 'paid' THEN o.total END), 0) as cash_sales,
        COALESCE(SUM(CASE WHEN o.payment_method = 'qris' AND o.status = 'paid' THEN o.total END), 0) as qris_sales,
        COALESCE(SUM(CASE WHEN o.payment_method = 'credit' OR o.status = 'credit' THEN o.total END), 0) as credit_sales,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as total_sales,
        COALESCE(SUM(o.tax_value), 0) as total_tax,
        COALESCE(SUM(o.service_value), 0) as total_service,
        COALESCE(AVG(CASE WHEN o.status = 'paid' THEN o.total END), 0) as avg_transaction
    FROM orders o
    WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
";

try {
    $daily_stats_stmt = db()->prepare($stats_query);
    $daily_stats_stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $daily_stats = $daily_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$daily_stats) {
        $daily_stats = [
            'cash_sales' => 0,
            'qris_sales' => 0,
            'credit_sales' => 0,
            'total_sales' => 0,
            'total_orders' => 0,
            'total_tax' => 0,
            'total_service' => 0,
            'avg_transaction' => 0
        ];
    }
} catch (Exception $e) {
    error_log("Error fetching daily stats: " . $e->getMessage());
    $daily_stats = [
        'cash_sales' => 0,
        'qris_sales' => 0,
        'credit_sales' => 0,
        'total_sales' => 0,
        'total_orders' => 0,
        'total_tax' => 0,
        'total_service' => 0,
        'avg_transaction' => 0
    ];
}

// Items sold - BRANCH
$items_query = "
    SELECT COALESCE(SUM(oi.qty), 0) as items_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
    AND o.status = 'paid'
";

try {
    $items_stmt = db()->prepare($items_query);
    $items_stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $items_sold = (int)$items_stmt->fetchColumn();
} catch (Exception $e) {
    $items_sold = 0;
}

// ==================== QUERY UNTUK KASIR YANG LOGIN ====================
$cashier_stats = null;
$cashier_items_sold = 0;
$cashier_top_products = [];

// Tampilkan untuk semua role kecuali superadmin
if ($u['role'] !== 'superadmin') {
    $cashier_stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN o.payment_method = 'cash' AND o.status = 'paid' THEN o.total END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN o.payment_method = 'qris' AND o.status = 'paid' THEN o.total END), 0) as qris_sales,
            COALESCE(SUM(CASE WHEN o.payment_method = 'credit' OR o.status = 'credit' THEN o.total END), 0) as credit_sales,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as total_sales,
            COALESCE(AVG(CASE WHEN o.status = 'paid' THEN o.total END), 0) as avg_transaction
        FROM orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.user_id = ?
    ";
    
    try {
        $cashier_stats_stmt = db()->prepare($cashier_stats_query);
        $cashier_stats_stmt->execute([$date_from, $date_to, $u['id']]);
        $cashier_stats = $cashier_stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cashier_stats) {
            $cashier_stats = [
                'cash_sales' => 0,
                'qris_sales' => 0,
                'credit_sales' => 0,
                'total_sales' => 0,
                'total_orders' => 0,
                'avg_transaction' => 0
            ];
        }
    } catch (Exception $e) {
        error_log("Error fetching cashier stats: " . $e->getMessage());
        $cashier_stats = [
            'cash_sales' => 0,
            'qris_sales' => 0,
            'credit_sales' => 0,
            'total_sales' => 0,
            'total_orders' => 0,
            'avg_transaction' => 0
        ];
    }
    
    // Items sold untuk kasir yang login
    $cashier_items_query = "
        SELECT COALESCE(SUM(oi.qty), 0) as items_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.user_id = ?
        AND o.status = 'paid'
    ";
    
    try {
        $cashier_items_stmt = db()->prepare($cashier_items_query);
        $cashier_items_stmt->execute([$date_from, $date_to, $u['id']]);
        $cashier_items_sold = (int)$cashier_items_stmt->fetchColumn();
    } catch (Exception $e) {
        $cashier_items_sold = 0;
    }
    
    // Top products untuk kasir yang login
    $cashier_top_products_query = "
        SELECT 
            p.name,
            p.sku,
            SUM(oi.qty) as qty_sold,
            SUM(oi.qty * oi.price * (1 - IFNULL(oi.discount, 0)/100)) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.user_id = ?
        AND o.status = 'paid'
        GROUP BY p.id, p.name, p.sku
        ORDER BY qty_sold DESC
        LIMIT 5
    ";
    
    try {
        $cashier_top_products_stmt = db()->prepare($cashier_top_products_query);
        $cashier_top_products_stmt->execute([$date_from, $date_to, $u['id']]);
        $cashier_top_products = $cashier_top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching cashier top products: " . $e->getMessage());
        $cashier_top_products = [];
    }
}

// Outstanding credits
$credit_query = "
    SELECT 
        COUNT(*) as count,
        COALESCE(SUM(total_amount - paid_amount), 0) as outstanding
    FROM credits c
    WHERE c.status IN ('unpaid', 'partial')
";

if ($u['role'] !== 'superadmin') {
    $credit_query .= " AND c.branch_id = ?";
    $credit_params = [$u['branch_id']];
} elseif ($branch_filter !== 'all') {
    $credit_query .= " AND c.branch_id = ?";
    $credit_params = [(int)$branch_filter];
} else {
    $credit_params = [];
}

try {
    $credit_stats_stmt = db()->prepare($credit_query);
    $credit_stats_stmt->execute($credit_params);
    $credit_stats = $credit_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$credit_stats) {
        $credit_stats = ['count' => 0, 'outstanding' => 0];
    }
} catch (Exception $e) {
    error_log("Error fetching credit stats: " . $e->getMessage());
    $credit_stats = ['count' => 0, 'outstanding' => 0];
}

// Product & Stock stats
$product_query = "
    SELECT 
        COUNT(DISTINCT p.id) as active_products,
        COUNT(CASE WHEN IFNULL(sb.quantity, 0) <= 5 THEN 1 END) as low_stock_count
    FROM products p
    LEFT JOIN stock_branch sb ON p.id = sb.product_id AND p.branch_id = sb.branch_id
    WHERE p.active = 1
";

if ($u['role'] !== 'superadmin') {
    $product_query .= " AND p.branch_id = ?";
    $product_params = [$u['branch_id']];
} elseif ($branch_filter !== 'all') {
    $product_query .= " AND p.branch_id = ?";
    $product_params = [(int)$branch_filter];
} else {
    $product_params = [];
}

try {
    $product_stats_stmt = db()->prepare($product_query);
    $product_stats_stmt->execute($product_params);
    $product_stats = $product_stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product_stats) {
        $product_stats = ['active_products' => 0, 'low_stock_count' => 0];
    }
} catch (Exception $e) {
    error_log("Error fetching product stats: " . $e->getMessage());
    $product_stats = ['active_products' => 0, 'low_stock_count' => 0];
}

// Top products - BRANCH
$top_products_query = "
    SELECT 
        p.name,
        p.sku,
        SUM(oi.qty) as qty_sold,
        SUM(oi.qty * oi.price * (1 - IFNULL(oi.discount, 0)/100)) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
    AND o.status = 'paid'
    GROUP BY p.id, p.name, p.sku
    ORDER BY qty_sold DESC
    LIMIT 5
";

try {
    $top_products_stmt = db()->prepare($top_products_query);
    $top_products_stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching top products: " . $e->getMessage());
    $top_products = [];
}

// Recent orders - BRANCH
$recent_orders_query = "
    SELECT 
        o.id,
        o.total,
        o.status,
        o.payment_method,
        o.customer_name,
        o.employee_id,
        o.employee_name,
        o.created_at,
        b.name as branch_name,
        u.name as cashier_name,
        u.role as cashier_role
    FROM orders o
    LEFT JOIN branches b ON o.branch_id = b.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
    ORDER BY o.created_at DESC
    LIMIT 10
";

try {
    $recent_orders_stmt = db()->prepare($recent_orders_query);
    $recent_orders_stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching recent orders: " . $e->getMessage());
    $recent_orders = [];
}

// Quick alerts
$alerts = [];

if ($product_stats['low_stock_count'] > 0) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-exclamation-triangle',
        'message' => $product_stats['low_stock_count'] . ' produk stok menipis',
        'action' => 'manage_stockbranch.php'
    ];
}

if ($credit_stats['count'] > 0) {
    $alerts[] = [
        'type' => 'info',
        'icon' => 'fa-credit-card',
        'message' => $credit_stats['count'] . ' kredit belum lunas (Rp ' . number_format($credit_stats['outstanding'], 0, ',', '.') . ')',
        'action' => 'manage_credits.php'
    ];
}

// Get branch name for display
$current_branch_name = 'Semua Cabang';
if ($u['role'] !== 'superadmin') {
    try {
        $branch_name_stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
        $branch_name_stmt->execute([$u['branch_id']]);
        $branch_data = $branch_name_stmt->fetch(PDO::FETCH_ASSOC);
        $current_branch_name = $branch_data['name'] ?? 'Cabang ' . $u['branch_id'];
    } catch (Exception $e) {
        error_log("Error fetching branch name: " . $e->getMessage());
        $current_branch_name = 'Cabang ' . $u['branch_id'];
    }
} elseif ($branch_filter !== 'all') {
    try {
        $branch_name_stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
        $branch_name_stmt->execute([$branch_filter]);
        $branch_data = $branch_name_stmt->fetch(PDO::FETCH_ASSOC);
        $current_branch_name = $branch_data['name'] ?? 'Cabang ' . $branch_filter;
    } catch (Exception $e) {
        error_log("Error fetching filtered branch name: " . $e->getMessage());
        $current_branch_name = 'Cabang ' . $branch_filter;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard POS - <?= e(defined('APP_NAME') ? APP_NAME : 'POS System') ?></title>
  <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
  <style>
    body { 
      margin:0; 
      font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; 
      background:#064420; 
      color:#fff; 
      line-height: 1.6;
    }
    .container { margin-left:240px; padding:30px; }
    
    /* Enhanced Header */
    .header { 
      background: linear-gradient(135deg, #0b6e4f, #085c3a);
      border-radius: 16px;
      padding: 25px;
      margin-bottom: 30px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    }
    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }
    .header h1 { 
      margin:0; 
      color:#ffd700; 
      font-size: 28px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    /* User Section */
    .user-section {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    .user-welcome {
      background: rgba(255, 215, 0, 0.1);
      padding: 15px 20px;
      border-radius: 12px;
      border-left: 4px solid #ffd700;
      min-width: 200px;
    }
    .user-name {
      font-size: 18px;
      font-weight: 600;
      color: #ffd700;
      margin-bottom: 4px;
    }
    .user-role {
      font-size: 12px;
      color: #ccc;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .user-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .user-actions .btn {
      font-size: 11px;
      padding: 6px 12px;
    }
    
    /* Branch Info */
    .branch-info {
      background: rgba(39, 174, 96, 0.2);
      padding: 10px 16px;
      border-radius: 20px;
      border: 2px solid #27ae60;
      font-size: 13px;
      font-weight: 600;
      margin-top: 10px;
    }
    
    /* Section Divider */
    .section-divider {
      background: linear-gradient(135deg, #27ae60, #229954);
      padding: 15px 25px;
      border-radius: 12px;
      margin: 30px 0 20px 0;
      border-left: 5px solid #1e8449;
      box-shadow: 0 4px 20px rgba(39, 174, 96, 0.3);
    }
    .section-divider.cashier {
      background: linear-gradient(135deg, #8e44ad, #7d3c98);
      border-left: 5px solid #6c3483;
      box-shadow: 0 4px 20px rgba(142, 68, 173, 0.3);
    }
    .section-divider h2 {
      margin: 0;
      color: #fff;
      font-size: 20px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .section-divider p {
      margin: 5px 0 0 0;
      color: rgba(255,255,255,0.8);
      font-size: 13px;
    }
    
    /* Date Filter */
    .date-filter { 
      display:flex; 
      gap:12px; 
      align-items:center;
      background: rgba(255,255,255,0.1);
      padding: 15px;
      border-radius: 12px;
    }
    .date-filter input, .date-filter select { 
      padding:10px 12px; 
      border-radius:8px; 
      border:none; 
      color:#333;
      font-size: 14px;
      background: #fff;
    }
    .date-filter button {
      padding: 10px 20px;
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      color: #064420;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .date-filter button:hover {
      background: linear-gradient(135deg, #ffed4e, #ffd700);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
    }
    
    .grid { 
      display:grid; 
      grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); 
      gap:24px; 
      margin-bottom:30px; 
    }
    .grid-2 { 
      display:grid; 
      grid-template-columns: 2fr 1fr; 
      gap:24px; 
      margin-bottom:30px; 
    }
    .grid-3 { 
      display:grid; 
      grid-template-columns: repeat(auto-fit, minmax(350px,1fr)); 
      gap:24px; 
    }
    
    /* Cards */
    .card { 
      background: linear-gradient(135deg, #0b6e4f, #085c3a);
      border-radius:16px; 
      padding:24px; 
      box-shadow:0 8px 32px rgba(0,0,0,0.15); 
      transition: all 0.3s ease;
      border: 1px solid rgba(255, 215, 0, 0.1);
    }
    .card:hover { 
      transform: translateY(-4px); 
      box-shadow:0 12px 40px rgba(0,0,0,0.25);
    }
    .card h2 { 
      color:#ffd700; 
      margin-top:0; 
      margin-bottom:18px; 
      font-size:18px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .card h3 { 
      color:#ffd700; 
      margin:20px 0 12px 0; 
      font-size:16px;
      font-weight: 500;
    }
    
    /* Cashier Card - UNGU/PURPLE */
    .card-cashier {
      background: linear-gradient(135deg, #8e44ad, #7d3c98);
      border: 1px solid rgba(142, 68, 173, 0.3);
    }
    .card-cashier h2 {
      color: #f8e5ff;
    }
    .card-cashier h3 {
      color: #f8e5ff;
    }
    
    /* Stat Cards */
    .stat-card { 
      text-align:center;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--accent-color, #ffd700);
    }
    .stat-number { 
      font-size:32px; 
      font-weight:800; 
      margin:15px 0;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    .stat-label { 
      font-size:13px; 
      color:#bbb; 
      text-transform:uppercase;
      letter-spacing: 1px;
      font-weight: 500;
    }
    
    .stat-cash { color:#27ae60; --accent-color: #27ae60; }
    .stat-qris { color:#3498db; --accent-color: #3498db; }
    .stat-credit { color:#f39c12; --accent-color: #f39c12; }
    .stat-orders { color:#9b59b6; --accent-color: #9b59b6; }
    .stat-products { color:#e74c3c; --accent-color: #e74c3c; }
    
    /* Alerts */
    .alert-card { 
      border-left:6px solid #f39c12;
      background: linear-gradient(135deg, rgba(241, 196, 15, 0.1), rgba(230, 126, 34, 0.1));
    }
    .alert-item { 
      display:flex; 
      align-items:center; 
      padding:15px; 
      margin:8px 0; 
      background:rgba(255,255,255,0.05); 
      border-radius:10px;
      backdrop-filter: blur(10px);
    }
    .alert-item i { 
      margin-right:15px; 
      font-size:20px; 
    }
    
    /* Tables */
    table { 
      width:100%; 
      border-collapse:collapse; 
      margin-top:15px; 
      background:#fff; 
      color:#333; 
      border-radius:12px; 
      overflow:hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    th, td { 
      padding:14px 12px; 
      border-bottom:1px solid #ecf0f1; 
    }
    th { 
      background: linear-gradient(135deg, #34495e, #2c3e50);
      color:#fff; 
      text-align:left; 
      font-weight:600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    tr:hover { 
      background:#f8f9fa; 
    }
    
    /* Buttons */
    .btn { 
      display:inline-block; 
      padding:8px 16px; 
      border:none; 
      border-radius:8px; 
      cursor:pointer; 
      font-weight:600; 
      text-decoration:none; 
      transition:all 0.3s ease;
      font-size: 14px;
      text-align: center;
    }
    .btn.primary { 
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      color:#064420; 
    }
    .btn.primary:hover { 
      background: linear-gradient(135deg, #ffed4e, #ffd700);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 215, 0, 0.3);
    }
    .btn.secondary { 
      background: linear-gradient(135deg, #6c757d, #5a6268);
      color:#fff; 
    }
    .btn.secondary:hover { 
      background: linear-gradient(135deg, #5a6268, #495057);
      transform: translateY(-2px);
    }
    .btn.success { 
      background: linear-gradient(135deg, #27ae60, #2ecc71);
      color:#fff; 
    }
    .btn.warning { 
      background: linear-gradient(135deg, #f39c12, #e67e22);
      color:#fff; 
    }
    .btn.warning:hover { 
      background: linear-gradient(135deg, #e67e22, #d35400);
      transform: translateY(-2px);
    }
    
    .quick-actions { 
      display:grid; 
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap:12px; 
      margin-top:20px; 
    }
    
    /* Status badges */
    .status-paid { background: linear-gradient(135deg, #27ae60, #2ecc71); color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight: 600; }
    .status-pending { background: linear-gradient(135deg, #f39c12, #e67e22); color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight: 600; }
    .status-credit { background: linear-gradient(135deg, #e74c3c, #c0392b); color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight: 600; }
    .status-cancelled { background: linear-gradient(135deg, #95a5a6, #7f8c8d); color:#fff; padding:4px 10px; border-radius:6px; font-size:11px; font-weight: 600; }
    
    /* Animation */
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .card {
      animation: fadeInUp 0.6s ease-out;
    }
    
    @media (max-width: 800px) {
      .container { margin-left:70px; padding:20px; }
      .header-top { flex-direction:column; gap:15px; align-items:stretch; }
      .grid-2 { grid-template-columns:1fr; }
      .grid-3 { grid-template-columns:1fr; }
      .quick-actions { grid-template-columns: 1fr; }
      .date-filter { flex-wrap: wrap; }
      .user-actions { justify-content: center; }
      
      table td, table th {
        font-size: 12px;
        padding: 8px 6px;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <!-- Header -->
  <div class="header">
    <div class="header-top">
      <div>
        <h1>
          <i class="fa fa-chart-line"></i>
          Dashboard POS
        </h1>
        <div class="branch-info">
          <i class="fa fa-store"></i> <?= e($current_branch_name) ?>
        </div>
      </div>
      
      <div class="user-section">
        <div class="user-welcome">
          <div class="user-name">Selamat datang, <?= e($u['name']) ?></div>
          <div class="user-role"><?= ucfirst(e($u['role'])) ?></div>
        </div>
        <div class="user-actions">
          <a href="ganti_password.php" class="btn warning">
            <i class="fa fa-key"></i> Ganti Password
          </a>
          <a href="logout.php" class="btn secondary">
            <i class="fa fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </div>
    </div>
    
    <form method="get" class="date-filter">
      <i class="fa fa-calendar" style="color: #ffd700; font-size: 18px;"></i>
      <input type="date" name="date_from" value="<?= e($date_from) ?>" required>
      <span style="color: #ccc;">sampai</span>
      <input type="date" name="date_to" value="<?= e($date_to) ?>" required>
      
      <?php if ($u['role'] === 'superadmin'): ?>
      <select name="branch">
        <option value="all" <?= $branch_filter === 'all' ? 'selected' : '' ?>>Semua Cabang</option>
        <?php foreach($branches as $branch): ?>
          <option value="<?= (int)$branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>>
            <?= e($branch['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      
      <button type="submit">
        <i class="fa fa-search"></i> Tampilkan
      </button>
    </form>
  </div>

  <!-- Alerts -->
  <?php if (!empty($alerts)): ?>
  <div class="card alert-card" style="margin-bottom:30px;">
    <h2><i class="fa fa-bell"></i> Peringatan & Notifikasi</h2>
    <?php foreach($alerts as $alert): ?>
      <div class="alert-item alert-<?= e($alert['type']) ?>">
        <i class="fa <?= e($alert['icon']) ?>"></i>
        <div style="flex:1;">
          <?= e($alert['message']) ?>
          <a href="<?= e($alert['action']) ?>" class="btn secondary" style="margin-left:15px; font-size:12px; padding:6px 12px;">
            Lihat Detail
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- SECTION 1: RESUME BRANCH -->
  <div class="section-divider">
    <h2>
      <i class="fa fa-store"></i>
      Resume Branch: <?= e($current_branch_name) ?>
    </h2>
    <p>Data keseluruhan penjualan branch untuk periode terpilih</p>
  </div>

  <!-- Stats Cards - BRANCH -->
  <div class="grid">
    <div class="card stat-card stat-cash">
      <h2><i class="fa fa-money-bill-wave"></i> Penjualan Tunai</h2>
      <div class="stat-number">Rp <?= number_format((int)$daily_stats['cash_sales'], 0, ',', '.') ?></div>
      <div class="stat-label">Total Branch</div>
    </div>
    
    <div class="card stat-card stat-qris">
      <h2><i class="fa fa-qrcode"></i> Penjualan QRIS</h2>
      <div class="stat-number">Rp <?= number_format((int)$daily_stats['qris_sales'], 0, ',', '.') ?></div>
      <div class="stat-label">Total Branch</div>
    </div>
    
    <div class="card stat-card stat-credit">
      <h2><i class="fa fa-credit-card"></i> Penjualan Kredit</h2>
      <div class="stat-number">Rp <?= number_format((int)$daily_stats['credit_sales'], 0, ',', '.') ?></div>
      <div class="stat-label">Total Branch</div>
    </div>
    
    <div class="card stat-card stat-orders">
      <h2><i class="fa fa-receipt"></i> Total Transaksi</h2>
      <div class="stat-number"><?= number_format((int)$daily_stats['total_orders']) ?></div>
      <div class="stat-label">Total Branch</div>
    </div>
    
    <div class="card stat-card stat-products">
      <h2><i class="fa fa-box-open"></i> Item Terjual</h2>
      <div class="stat-number"><?= number_format($items_sold) ?></div>
      <div class="stat-label">Total Branch</div>
    </div>
  </div>

  <!-- Sales Summary - BRANCH -->
  <div class="grid-2">
    <div class="card">
      <h2><i class="fa fa-chart-pie"></i> Ringkasan Penjualan Branch</h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="text-align: center; padding: 15px; background: rgba(39, 174, 96, 0.1); border-radius: 10px; border: 2px solid #27ae60;">
          <div style="font-size: 20px; color: #27ae60; font-weight: bold;">
            Rp <?= number_format((int)$daily_stats['cash_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #ccc; margin-top: 5px;">
            <i class="fa fa-money-bill" style="color: #27ae60;"></i> TUNAI
          </div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: rgba(52, 152, 219, 0.1); border-radius: 10px; border: 2px solid #3498db;">
          <div style="font-size: 20px; color: #3498db; font-weight: bold;">
            Rp <?= number_format((int)$daily_stats['qris_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #ccc; margin-top: 5px;">
            <i class="fa fa-qrcode" style="color: #3498db;"></i> QRIS
          </div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: rgba(243, 156, 18, 0.1); border-radius: 10px; border: 2px solid #f39c12;">
          <div style="font-size: 20px; color: #f39c12; font-weight: bold;">
            Rp <?= number_format((int)$daily_stats['credit_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #ccc; margin-top: 5px;">
            <i class="fa fa-credit-card" style="color: #f39c12;"></i> KREDIT
          </div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: rgba(155, 89, 182, 0.1); border-radius: 10px; border: 2px solid #9b59b6;">
          <div style="font-size: 20px; color: #9b59b6; font-weight: bold;">
            Rp <?= number_format((int)$daily_stats['total_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #ccc; margin-top: 5px;">
            <i class="fa fa-chart-line" style="color: #9b59b6;"></i> TOTAL
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
        <div style="background: rgba(255,255,255,0.05); padding: 18px; border-radius: 12px; text-align: center; border: 1px solid rgba(255,215,0,0.2);">
          <div style="font-size: 22px; font-weight: bold; color: #3498db;">
            <?= number_format((int)$daily_stats['total_orders']) ?>
          </div>
          <div style="font-size: 12px; color: #ccc; margin-top: 8px;">
            <i class="fa fa-receipt"></i> Total Transaksi
          </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.05); padding: 18px; border-radius: 12px; text-align: center; border: 1px solid rgba(255,215,0,0.2);">
          <div style="font-size: 22px; font-weight: bold; color: #9b59b6;">
            Rp <?= number_format((int)$daily_stats['avg_transaction'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 12px; color: #ccc; margin-top: 8px;">
            <i class="fa fa-calculator"></i> Rata-rata Transaksi
          </div>
        </div>
      </div>
    </div>
    
    <div class="card">
      <h2><i class="fa fa-trophy"></i> Produk Terlaris Branch</h2>
      <?php if (empty($top_products)): ?>
        <div style="text-align:center; color:#ccc; padding:40px; background: rgba(255,255,255,0.05); border-radius: 10px; margin-top: 15px;">
          <i class="fa fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
          <p>Belum ada penjualan periode ini</p>
        </div>
      <?php else: ?>
        <table>
          <tr><th>Produk</th><th>Terjual</th><th>Revenue</th></tr>
          <?php foreach($top_products as $product): ?>
            <tr>
              <td>
                <strong><?= e($product['name']) ?></strong><br>
                <small style="color:#666;">SKU: <?= e($product['sku'] ?? '-') ?></small>
              </td>
              <td><strong style="color: #27ae60;"><?= (int)$product['qty_sold'] ?>x</strong></td>
              <td><strong>Rp <?= number_format((int)$product['revenue'], 0, ',', '.') ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECTION 2: RESUME KASIR -->
  <?php if ($cashier_stats !== null): ?>
  <div class="section-divider cashier">
    <h2>
      <i class="fa fa-user-circle"></i>
      Resume Kasir: <?= e($u['name']) ?>
    </h2>
    <p>Data transaksi yang Anda handle untuk periode terpilih</p>
  </div>

  <!-- Stats Cards - KASIR (UNGU) -->
  <div class="grid">
    <div class="card card-cashier stat-card stat-cash">
      <h2><i class="fa fa-money-bill-wave"></i> Penjualan Tunai Anda</h2>
      <div class="stat-number" style="color: #2ecc71;">Rp <?= number_format((int)$cashier_stats['cash_sales'], 0, ',', '.') ?></div>
      <div class="stat-label" style="color: rgba(255,255,255,0.7);">Transaksi Anda</div>
    </div>
    
    <div class="card card-cashier stat-card stat-qris">
      <h2><i class="fa fa-qrcode"></i> Penjualan QRIS Anda</h2>
      <div class="stat-number" style="color: #5dade2;">Rp <?= number_format((int)$cashier_stats['qris_sales'], 0, ',', '.') ?></div>
      <div class="stat-label" style="color: rgba(255,255,255,0.7);">Transaksi Anda</div>
    </div>
    
    <div class="card card-cashier stat-card stat-credit">
      <h2><i class="fa fa-credit-card"></i> Penjualan Kredit Anda</h2>
      <div class="stat-number" style="color: #f5b041;">Rp <?= number_format((int)$cashier_stats['credit_sales'], 0, ',', '.') ?></div>
      <div class="stat-label" style="color: rgba(255,255,255,0.7);">Transaksi Anda</div>
    </div>
    
    <div class="card card-cashier stat-card stat-orders">
      <h2><i class="fa fa-receipt"></i> Total Transaksi Anda</h2>
      <div class="stat-number" style="color: #f8e5ff;"><?= number_format((int)$cashier_stats['total_orders']) ?></div>
      <div class="stat-label" style="color: rgba(255,255,255,0.7);">Transaksi Anda</div>
    </div>
    
    <div class="card card-cashier stat-card stat-products">
      <h2><i class="fa fa-box-open"></i> Item Yang Anda Jual</h2>
      <div class="stat-number" style="color: #f8e5ff;"><?= number_format($cashier_items_sold) ?></div>
      <div class="stat-label" style="color: rgba(255,255,255,0.7);">Transaksi Anda</div>
    </div>
  </div>

  <!-- Cashier Performance -->
  <div class="grid-2">
    <div class="card card-cashier">
      <h2><i class="fa fa-chart-bar"></i> Performa Penjualan Anda</h2>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 10px; border: 2px solid rgba(255,255,255,0.3);">
          <div style="font-size: 20px; color: #2ecc71; font-weight: bold;">
            Rp <?= number_format((int)$cashier_stats['cash_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #fff; margin-top: 5px;">
            <i class="fa fa-money-bill" style="color: #2ecc71;"></i> TUNAI
          </div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 10px; border: 2px solid rgba(255,255,255,0.3);">
          <div style="font-size: 20px; color: #5dade2; font-weight: bold;">
            Rp <?= number_format((int)$cashier_stats['qris_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #fff; margin-top: 5px;">
            <i class="fa fa-qrcode" style="color: #5dade2;"></i> QRIS
          </div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 10px; border: 2px solid rgba(255,255,255,0.3);">
          <div style="font-size: 20px; color: #f5b041; font-weight: bold;">
            Rp <?= number_format((int)$cashier_stats['credit_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #fff; margin-top: 5px;">
            <i class="fa fa-credit-card" style="color: #f5b041;"></i> KREDIT
          </div>
        </div>
        
        <div style="text-align: center; padding: 15px; background: rgba(255,255,255,0.15); border-radius: 10px; border: 2px solid rgba(255,255,255,0.3);">
          <div style="font-size: 20px; color: #fff; font-weight: bold;">
            Rp <?= number_format((int)$cashier_stats['total_sales'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 11px; color: #fff; margin-top: 5px;">
            <i class="fa fa-chart-line"></i> TOTAL
          </div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
        <div style="background: rgba(255,255,255,0.15); padding: 18px; border-radius: 12px; text-align: center; border: 1px solid rgba(255,255,255,0.2);">
          <div style="font-size: 22px; font-weight: bold; color: #fff;">
            <?= number_format((int)$cashier_stats['total_orders']) ?>
          </div>
          <div style="font-size: 12px; color: rgba(255,255,255,0.8); margin-top: 8px;">
            <i class="fa fa-receipt"></i> Total Transaksi Anda
          </div>
        </div>
        
        <div style="background: rgba(255,255,255,0.15); padding: 18px; border-radius: 12px; text-align: center; border: 1px solid rgba(255,255,255,0.2);">
          <div style="font-size: 22px; font-weight: bold; color: #ffd700;">
            Rp <?= number_format((int)$cashier_stats['avg_transaction'], 0, ',', '.') ?>
          </div>
          <div style="font-size: 12px; color: rgba(255,255,255,0.8); margin-top: 8px;">
            <i class="fa fa-calculator"></i> Rata-rata Transaksi Anda
          </div>
        </div>
      </div>

      <?php if ($daily_stats['total_sales'] > 0): ?>
      <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid rgba(255,255,255,0.2);">
        <h3 style="font-size: 16px; margin-bottom: 15px;">
          <i class="fa fa-chart-pie"></i> Kontribusi Anda terhadap Branch
        </h3>
        <?php 
        $contribution_percentage = ($cashier_stats['total_sales'] / $daily_stats['total_sales']) * 100;
        $transaction_percentage = ($cashier_stats['total_orders'] / $daily_stats['total_orders']) * 100;
        ?>
        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px;">
          <div style="margin-bottom: 12px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
              <span style="font-size: 13px; color: rgba(255,255,255,0.9);">
                <i class="fa fa-money-bill-wave"></i> Kontribusi Penjualan
              </span>
              <strong style="color: #ffd700; font-size: 14px;">
                <?= number_format($contribution_percentage, 1) ?>%
              </strong>
            </div>
            <div style="background: rgba(0,0,0,0.3); height: 10px; border-radius: 5px; overflow: hidden;">
              <div style="background: linear-gradient(90deg, #2ecc71, #27ae60); height: 100%; width: <?= min($contribution_percentage, 100) ?>%; border-radius: 5px;"></div>
            </div>
          </div>
          
          <div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
              <span style="font-size: 13px; color: rgba(255,255,255,0.9);">
                <i class="fa fa-receipt"></i> Kontribusi Transaksi
              </span>
              <strong style="color: #ffd700; font-size: 14px;">
                <?= number_format($transaction_percentage, 1) ?>%
              </strong>
            </div>
            <div style="background: rgba(0,0,0,0.3); height: 10px; border-radius: 5px; overflow: hidden;">
              <div style="background: linear-gradient(90deg, #5dade2, #3498db); height: 100%; width: <?= min($transaction_percentage, 100) ?>%; border-radius: 5px;"></div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    
    <div class="card card-cashier">
      <h2><i class="fa fa-star"></i> Produk Terlaris Anda</h2>
      <?php if (empty($cashier_top_products)): ?>
        <div style="text-align:center; color:rgba(255,255,255,0.7); padding:40px; background: rgba(255,255,255,0.05); border-radius: 10px; margin-top: 15px;">
          <i class="fa fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
          <p>Belum ada penjualan Anda di periode ini</p>
        </div>
      <?php else: ?>
        <table>
          <tr><th>Produk</th><th>Terjual</th><th>Revenue</th></tr>
          <?php foreach($cashier_top_products as $product): ?>
            <tr>
              <td>
                <strong><?= e($product['name']) ?></strong><br>
                <small style="color:#666;">SKU: <?= e($product['sku'] ?? '-') ?></small>
              </td>
              <td><strong style="color: #27ae60;"><?= (int)$product['qty_sold'] ?>x</strong></td>
              <td><strong>Rp <?= number_format((int)$product['revenue'], 0, ',', '.') ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Orders -->
  <div class="grid-3">
    <div class="card" style="grid-column: span 2;">
      <h2><i class="fa fa-history"></i> Transaksi Terbaru</h2>
      <?php if (empty($recent_orders)): ?>
        <div style="text-align:center; color:#ccc; padding:40px; background: rgba(255,255,255,0.05); border-radius: 10px; margin-top: 15px;">
          <i class="fa fa-receipt" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
          <p>Belum ada transaksi periode ini</p>
        </div>
      <?php else: ?>
        <div style="overflow-x:auto; margin-top: 15px;">
          <table>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Metode</th>
              <th>Status</th>
              <th>Kasir</th>
              <th>Waktu</th>
              <th>Aksi</th>
            </tr>
            <?php foreach($recent_orders as $order): ?>
              <tr>
                <td><strong style="color: #3498db;">#<?= (int)$order['id'] ?></strong></td>
                <td>
                  <?php if ($order['status'] === 'credit' && !empty($order['employee_name'])): ?>
                    <div style="font-size:12px;">
                      <strong><?= e($order['employee_name']) ?></strong>
                      <br><span style="color:#666; font-size:10px;">Karyawan</span>
                    </div>
                  <?php else: ?>
                    <?= e($order['customer_name'] ?: 'Guest') ?>
                  <?php endif; ?>
                </td>
                <td><strong>Rp <?= number_format((int)$order['total'], 0, ',', '.') ?></strong></td>
                <td>
                  <?php
                  $method_colors = [
                    'cash' => '#27ae60',
                    'qris' => '#3498db',
                    'credit' => '#f39c12'
                  ];
                  $method_color = $method_colors[$order['payment_method']] ?? '#95a5a6';
                  ?>
                  <span style="background:<?= $method_color ?>;color:#fff;padding:4px 8px;font-size:10px;border-radius:6px;text-transform:uppercase;font-weight:600;">
                    <?= e($order['payment_method']) ?>
                  </span>
                </td>
                <td>
                  <?php
                  $status_colors = [
                    'paid' => '#27ae60',
                    'pending' => '#f39c12',
                    'credit' => '#e74c3c',
                    'cancelled' => '#95a5a6'
                  ];
                  $status_labels = [
                    'paid' => 'Lunas',
                    'pending' => 'Pending', 
                    'credit' => 'Kredit',
                    'cancelled' => 'Batal'
                  ];
                  $status_color = $status_colors[$order['status']] ?? '#95a5a6';
                  $status_label = $status_labels[$order['status']] ?? ucfirst($order['status']);
                  ?>
                  <span style="background:<?= $status_color ?>;color:#fff;padding:4px 8px;font-size:10px;border-radius:6px;font-weight:600;">
                    <?= $status_label ?>
                  </span>
                </td>
                <td>
                  <div style="font-size:11px;">
                    <i class="fa fa-user" style="color:#8e44ad;"></i>
                    <strong><?= e($order['cashier_name'] ?? 'N/A') ?></strong>
                    <?php if (!empty($order['cashier_role'])): ?>
                    <br><span style="color:#666; font-size:9px;">
                      <?= ucfirst(e($order['cashier_role'])) ?>
                    </span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div style="font-size:11px;">
                    <strong><?= date('H:i', strtotime($order['created_at'])) ?></strong>
                    <br><span style="color:#666; font-size:9px;">
                      <?= date('d/m/Y', strtotime($order['created_at'])) ?>
                    </span>
                  </div>
                </td>
                <td>
                  <div style="display:flex; gap:4px; flex-direction:column;">
                    <a href="receipt.php?id=<?= (int)$order['id'] ?>" target="_blank" 
                       class="btn secondary" style="font-size:10px; padding:4px 8px;">
                      <i class="fa fa-print"></i> Struk
                    </a>
                    
                    <?php if ($order['status'] === 'credit'): ?>
                      <a href="manage_credits.php?id=<?= (int)$order['id'] ?>" 
                         class="btn warning" style="font-size:10px; padding:4px 8px;">
                        <i class="fa fa-credit-card"></i> Detail
                      </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <h2><i class="fa fa-rocket"></i> Quick Actions</h2>
      
      <div class="quick-actions">
        <a href="pos.php" class="btn primary">
          <i class="fa fa-cash-register"></i> Transaksi Baru
        </a>
        
        <a href="manage_products.php" class="btn secondary">
          <i class="fa fa-box-open"></i> Kelola Produk
        </a>
        
        <a href="reports.php" class="btn secondary">
          <i class="fa fa-chart-line"></i> Laporan
        </a>
        
        <a href="manage_credits.php" class="btn warning">
          <i class="fa fa-credit-card"></i> Kredit
        </a>
        
        <a href="stock_movements.php" class="btn secondary">
          <i class="fa fa-truck"></i> Stok Movement
        </a>
      </div>

      <h3><i class="fa fa-tachometer-alt"></i> Statistik Ringkas</h3>
      <div style="background: rgba(255,255,255,0.05); padding:18px; border-radius:12px; margin:15px 0; border: 1px solid rgba(255,215,0,0.2);">
        <div style="display:grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin:8px 0;">
          <span><i class="fa fa-box" style="color: #3498db; margin-right: 8px;"></i>Produk Aktif:</span>
          <strong style="color: #3498db;"><?= number_format((int)$product_stats['active_products']) ?></strong>
        </div>
        <div style="display:grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin:8px 0;">
          <span><i class="fa fa-exclamation-triangle" style="color: #f39c12; margin-right: 8px;"></i>Stok Menipis:</span>
          <strong style="color:#f39c12;"><?= number_format((int)$product_stats['low_stock_count']) ?></strong>
        </div>
        <div style="display:grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin:8px 0;">
          <span><i class="fa fa-credit-card" style="color: #e74c3c; margin-right: 8px;"></i>Kredit Pending:</span>
          <strong style="color:#e74c3c;"><?= number_format((int)$credit_stats['count']) ?></strong>
        </div>
        <?php if ($credit_stats['outstanding'] > 0): ?>
        <div style="display:grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; margin:8px 0; padding-top: 10px; border-top: 1px solid rgba(255,215,0,0.2);">
          <span><i class="fa fa-money-bill-alt" style="color: #e74c3c; margin-right: 8px;"></i>Total Piutang:</span>
          <strong style="color:#e74c3c;">Rp <?= number_format((int)$credit_stats['outstanding'], 0, ',', '.') ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
let refreshTimer;
function startAutoRefresh() {
  refreshTimer = setTimeout(() => {
    document.body.style.opacity = '0.8';
    setTimeout(() => {
      location.reload();
    }, 500);
  }, 300000);
}

document.addEventListener('click', () => {
  clearTimeout(refreshTimer);
  startAutoRefresh();
});

document.addEventListener('keypress', () => {
  clearTimeout(refreshTimer);
  startAutoRefresh();
});

startAutoRefresh();

window.addEventListener('load', () => {
  document.body.style.opacity = '1';
});
</script>
</body>
</html>
