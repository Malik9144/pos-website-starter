<?php
ob_start();

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
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_filter = $_GET['branch'] ?? ($u['role'] === 'superadmin' ? 'all' : $u['branch_id']);
$report_type = $_GET['type'] ?? 'summary';

// REPORT TYPE FUNCTIONALITY
$show_detailed_products = false;
$show_hourly_analysis = false;
$show_comparison_analysis = false;
$product_limit = 10;

switch($report_type) {
    case 'summary':
        // Executive Summary - tampilkan ringkasan saja
        $product_limit = 5;
        $show_detailed_products = false;
        $show_hourly_analysis = false;
        $show_comparison_analysis = false;
        break;
        
    case 'detailed':
        // Detailed Analysis - tampilkan semua detail
        $product_limit = 50;
        $show_detailed_products = true;
        $show_hourly_analysis = true;
        $show_comparison_analysis = false;
        break;
        
    case 'comparison':
        // Period Comparison - tampilkan perbandingan periode
        $product_limit = 20;
        $show_detailed_products = true;
        $show_hourly_analysis = false;
        $show_comparison_analysis = true;
        break;
}

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

// Get branches for filter
$branches = [];
if ($u['role'] === 'superadmin') {
    $branches = db()->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
}

try {
    // 1. BASIC SALES SUMMARY - Only using existing columns
    $sales_summary_query = "
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN o.status = 'paid' THEN 1 END) as paid_orders,
            COUNT(CASE WHEN o.status = 'credit' THEN 1 END) as credit_orders,
            COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
            COALESCE(SUM(CASE WHEN o.payment_method = 'cash' AND o.status = 'paid' THEN o.total END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN o.payment_method = 'qris' AND o.status = 'paid' THEN o.total END), 0) as qris_sales,
            COALESCE(SUM(CASE WHEN o.payment_method = 'credit' OR o.status = 'credit' THEN o.total END), 0) as credit_sales,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as total_sales,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN IFNULL(o.tax_value, 0) END), 0) as total_tax,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN IFNULL(o.service_value, 0) END), 0) as total_service,
            COALESCE(AVG(CASE WHEN o.status = 'paid' THEN o.total END), 0) as avg_transaction,
            COALESCE(MIN(CASE WHEN o.status = 'paid' THEN o.total END), 0) as min_transaction,
            COALESCE(MAX(CASE WHEN o.status = 'paid' THEN o.total END), 0) as max_transaction
        FROM orders o 
        WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
    ";
    
    $stmt = db()->prepare($sales_summary_query);
    $stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $sales_summary = $stmt->fetch();

    // 2. DAILY BREAKDOWN
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

    // 3. PAYMENT METHOD ANALYSIS
    $payment_analysis_query = "
        SELECT 
            o.payment_method,
            COUNT(*) as transaction_count,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as total_amount,
            COALESCE(AVG(CASE WHEN o.status = 'paid' THEN o.total END), 0) as avg_amount,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN IFNULL(o.tax_value, 0) END), 0) as tax_collected,
            COALESCE(SUM(CASE WHEN o.status = 'paid' THEN IFNULL(o.service_value, 0) END), 0) as service_collected
        FROM orders o 
        WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
        AND o.status = 'paid'
        GROUP BY o.payment_method
        ORDER BY total_amount DESC
    ";
    
    $stmt = db()->prepare($payment_analysis_query);
    $stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $payment_analysis = $stmt->fetchAll();

    // 4. SIMPLIFIED PRODUCT ANALYSIS - FIXED: Remove LIMIT ? from query
    $product_analysis_query = "
        SELECT 
            p.name,
            p.sku,
            p.category,
            SUM(oi.qty) as qty_sold,
            COALESCE(SUM(oi.qty * oi.price), 0) as gross_revenue,
            COALESCE(SUM(oi.qty * IFNULL(p.hpp, 0)), 0) as total_cost,
            COALESCE(SUM(oi.qty * oi.price) - SUM(oi.qty * IFNULL(p.hpp, 0)), 0) as gross_profit,
            ROUND(
                CASE WHEN SUM(oi.qty * oi.price) > 0 
                THEN ((SUM(oi.qty * oi.price) - SUM(oi.qty * IFNULL(p.hpp, 0))) / SUM(oi.qty * oi.price)) * 100 
                ELSE 0 END, 2
            ) as profit_margin
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
        AND o.status = 'paid'
        GROUP BY p.id, p.name, p.sku, p.category
        ORDER BY gross_profit DESC
        LIMIT 100
    ";
    
    $stmt = db()->prepare($product_analysis_query);
    $stmt->execute(array_merge([$date_from, $date_to], $branch_params));
    $product_analysis = $stmt->fetchAll();

    // Calculate key metrics
    $total_revenue = $sales_summary['total_sales'] ?? 0;
    $total_cost = array_sum(array_column($product_analysis, 'total_cost'));
    $gross_profit = $total_revenue - $total_cost;
    $profit_margin = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;
    
    // Growth calculations (compared to previous period)
    $period_days = (strtotime($date_to) - strtotime($date_from)) / (60*60*24) + 1;
    $prev_date_from = date('Y-m-d', strtotime($date_from . " -{$period_days} days"));
    $prev_date_to = date('Y-m-d', strtotime($date_to . " -{$period_days} days"));
    
    $prev_sales_query = "
        SELECT COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.total END), 0) as total_sales
        FROM orders o 
        WHERE DATE(o.created_at) BETWEEN ? AND ? {$branch_condition}
    ";
    
    $stmt = db()->prepare($prev_sales_query);
    $stmt->execute(array_merge([$prev_date_from, $prev_date_to], $branch_params));
    $prev_sales = $stmt->fetchColumn();
    
    $growth_rate = $prev_sales > 0 ? (($total_revenue - $prev_sales) / $prev_sales) * 100 : 0;

} catch (Exception $e) {
    error_log("Error in financial report: " . $e->getMessage());
    $error_message = "Terjadi kesalahan saat memuat laporan: " . $e->getMessage();
}

// Get current branch name for display
$current_branch_name = 'Semua Cabang';
if ($u['role'] !== 'superadmin') {
    try {
        $branch_stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
        $branch_stmt->execute([$u['branch_id']]);
        $branch_data = $branch_stmt->fetch();
        $current_branch_name = $branch_data['name'] ?? 'Cabang ' . $u['branch_id'];
    } catch (Exception $e) {
        $current_branch_name = 'Cabang ' . $u['branch_id'];
    }
} elseif ($branch_filter !== 'all') {
    try {
        $branch_stmt = db()->prepare("SELECT name FROM branches WHERE id = ?");
        $branch_stmt->execute([$branch_filter]);
        $branch_data = $branch_stmt->fetch();
        $current_branch_name = $branch_data['name'] ?? 'Cabang ' . $branch_filter;
    } catch (Exception $e) {
        $current_branch_name = 'Cabang ' . $branch_filter;
    }
}

// Build query string for export links
$export_params = [
    'date_from' => $date_from,
    'date_to' => $date_to,
    'branch' => $branch_filter,
    'type' => $report_type
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financial Dashboard - Laporan Keuangan</title>
    <link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
    <style>
        :root {
            --primary-color: #064420;
            --secondary-color: #0b6e4f;
            --accent-color: #ffd700;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --dark-bg: #085c3a;
            --light-text: #ecf0f1;
            --shadow: 0 8px 32px rgba(0,0,0,0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--light-text);
            line-height: 1.6;
        }
        
        .container {
            margin-left: 240px;
            padding: 30px;
        }
        
        /* Enhanced Header */
        .financial-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--dark-bg));
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .financial-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), #ffed4e, var(--accent-color));
        }
        
        .header-content {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 30px;
            align-items: center;
        }
        
        .header-info h1 {
            color: var(--accent-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .meta-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid var(--accent-color);
        }
        
        .meta-label {
            font-size: 12px;
            color: #ccc;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-color);
        }
        
        .header-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        /* Enhanced Cards */
        .card {
            background: linear-gradient(135deg, var(--secondary-color), var(--dark-bg));
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 215, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 215, 0, 0.2);
        }
        
        .card-title {
            color: var(--accent-color);
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-subtitle {
            color: #ccc;
            font-size: 13px;
            margin-top: 5px;
        }
        
        /* Enhanced Filter System */
        .advanced-filters {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            color: var(--accent-color);
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-input {
            padding: 12px 15px;
            border: 2px solid transparent;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        /* Enhanced Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color), #ffed4e);
            color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ffed4e, var(--accent-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #2ecc71);
            color: white;
        }
        
        .btn-info {
            background: linear-gradient(135deg, var(--info-color), #5dade2);
            color: white;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #f7dc6f);
            color: white;
        }
        
        /* KPI Dashboard */
        .kpi-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: linear-gradient(135deg, var(--dark-bg), #064420);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(255, 215, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--kpi-color, var(--accent-color));
        }
        
        .kpi-icon {
            font-size: 36px;
            color: var(--kpi-color, var(--accent-color));
            margin-bottom: 15px;
        }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--kpi-color, var(--accent-color));
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .kpi-label {
            font-size: 13px;
            color: #ccc;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .kpi-change {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .kpi-up {
            background: rgba(39, 174, 96, 0.2);
            color: var(--success-color);
        }
        
        .kpi-down {
            background: rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
        }
        
        /* Alert Styles */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin: 25px 0;
        }
        
        .summary-card {
            background: linear-gradient(135deg, var(--card-color, var(--info-color)), var(--card-color-dark, #2980b9));
            padding: 25px;
            border-radius: 15px;
            color: white;
        }
        
        .summary-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
        }
        
        .summary-metric {
            text-align: center;
        }
        
        .summary-value {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-size: 11px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Data table */
        .data-table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            color: #333;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #34495e, #2c3e50);
        }
        
        .data-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        
        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .report-type-indicator {
            display: inline-block;
            background: var(--accent-color);
            color: var(--primary-color);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        @media (max-width: 800px) {
            .container {
                margin-left: 70px;
                padding: 20px;
            }
            
            .header-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .kpi-dashboard {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Enhanced Financial Header -->
    <div class="financial-header">
        <div class="header-content">
            <div class="header-info">
                <h1>
                    <i class="fa fa-chart-line"></i>
                    Financial Dashboard
                    <span class="report-type-indicator"><?= strtoupper(str_replace('_', ' ', $report_type)) ?></span>
                </h1>
                <p style="color: #ccc; font-size: 16px; margin-bottom: 0;">
                    Comprehensive Financial Analysis & Reporting System
                </p>
                
                <div class="header-meta">
                    <div class="meta-item">
                        <div class="meta-label">Report Period</div>
                        <div class="meta-value"><?= date('d M Y', strtotime($date_from)) ?> - <?= date('d M Y', strtotime($date_to)) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Branch Scope</div>
                        <div class="meta-value"><?= e($current_branch_name) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">User Access Level</div>
                        <div class="meta-value"><?= strtoupper(e($u['role'])) ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Generated</div>
                        <div class="meta-value"><?= date('d M Y, H:i') ?> WIB</div>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fa fa-print"></i> Print Report
                </button>
                <a href="export_financial.php?<?= http_build_query($export_params) ?>&export=csv" class="btn btn-success">
                    <i class="fa fa-file-csv"></i> Export CSV
                </a>
                <a href="export_financial.php?<?= http_build_query($export_params) ?>&export=excel" class="btn btn-info">
                    <i class="fa fa-file-excel"></i> Export Excel
                </a>
                <button onclick="location.reload()" class="btn btn-warning">
                    <i class="fa fa-refresh"></i> Refresh Data
                </button>
            </div>
        </div>
    </div>

    <!-- Error Handling -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i>
            <?= e($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Advanced Filters -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">
                    <i class="fa fa-filter"></i>
                    Advanced Report Filters
                </div>
                <div class="card-subtitle">Configure your financial report parameters</div>
            </div>
        </div>
        
        <form method="get" class="advanced-filters">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">Start Date</label>
                    <input type="date" name="date_from" value="<?= e($date_from) ?>" class="filter-input" required>
                </div>
                <div class="filter-group">
                    <label class="filter-label">End Date</label>
                    <input type="date" name="date_to" value="<?= e($date_to) ?>" class="filter-input" required>
                </div>
                <?php if ($u['role'] === 'superadmin'): ?>
                <div class="filter-group">
                    <label class="filter-label">Branch Selection</label>
                    <select name="branch" class="filter-input">
                        <option value="all" <?= $branch_filter === 'all' ? 'selected' : '' ?>>All Branches</option>
                        <?php foreach($branches as $branch): ?>
                            <option value="<?= (int)$branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>>
                                <?= e($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label class="filter-label">Report Type</label>
                    <select name="type" class="filter-input">
                        <option value="summary" <?= $report_type === 'summary' ? 'selected' : '' ?>>Executive Summary</option>
                        <option value="detailed" <?= $report_type === 'detailed' ? 'selected' : '' ?>>Detailed Analysis</option>
                        <option value="comparison" <?= $report_type === 'comparison' ? 'selected' : '' ?>>Period Comparison</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-chart-line"></i> Generate Report
                </button>
                <button type="button" onclick="setQuickDate('today')" class="btn btn-info">
                    <i class="fa fa-calendar-day"></i> Today
                </button>
                <button type="button" onclick="setQuickDate('week')" class="btn btn-info">
                    <i class="fa fa-calendar-week"></i> This Week
                </button>
                <button type="button" onclick="setQuickDate('month')" class="btn btn-info">
                    <i class="fa fa-calendar-alt"></i> This Month
                </button>
            </div>
        </form>
    </div>

    <!-- Key Performance Indicators -->
    <div class="kpi-dashboard">
        <div class="kpi-card" style="--kpi-color: var(--success-color);">
            <div class="kpi-icon">
                <i class="fa fa-chart-line"></i>
            </div>
            <div class="kpi-value">Rp <?= number_format($total_revenue, 0, ',', '.') ?></div>
            <div class="kpi-label">Total Revenue</div>
            <div class="kpi-change <?= $growth_rate >= 0 ? 'kpi-up' : 'kpi-down' ?>">
                <i class="fa fa-arrow-<?= $growth_rate >= 0 ? 'up' : 'down' ?>"></i>
                <?= number_format(abs($growth_rate), 1) ?>% vs Previous Period
            </div>
        </div>
        
        <div class="kpi-card" style="--kpi-color: var(--info-color);">
            <div class="kpi-icon">
                <i class="fa fa-coins"></i>
            </div>
            <div class="kpi-value">Rp <?= number_format($gross_profit, 0, ',', '.') ?></div>
            <div class="kpi-label">Gross Profit</div>
            <div class="kpi-change kpi-up">
                <i class="fa fa-percentage"></i>
                <?= number_format($profit_margin, 1) ?>% Margin
            </div>
        </div>
        
        <div class="kpi-card" style="--kpi-color: var(--warning-color);">
            <div class="kpi-icon">
                <i class="fa fa-receipt"></i>
            </div>
            <div class="kpi-value"><?= number_format($sales_summary['total_orders'] ?? 0) ?></div>
            <div class="kpi-label">Total Transactions</div>
            <div class="kpi-change kpi-up">
                <i class="fa fa-calculator"></i>
                Avg: Rp <?= number_format($sales_summary['avg_transaction'] ?? 0, 0, ',', '.') ?>
            </div>
        </div>
        
        <div class="kpi-card" style="--kpi-color: var(--danger-color);">
            <div class="kpi-icon">
                <i class="fa fa-credit-card"></i>
            </div>
            <div class="kpi-value"><?= ($sales_summary['credit_orders'] ?? 0) ?></div>
            <div class="kpi-label">Credit Orders</div>
            <div class="kpi-change kpi-up">
                <i class="fa fa-money-bill"></i>
                Rp <?= number_format($sales_summary['credit_sales'] ?? 0, 0, ',', '.') ?>
            </div>
        </div>
    </div>

    <!-- Revenue Analysis -->
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">
                    <i class="fa fa-chart-pie"></i>
                    Revenue Analysis & Breakdown
                </div>
                <div class="card-subtitle">Comprehensive revenue analysis across payment methods</div>
            </div>
        </div>
        
        <!-- Payment Method Analysis -->
        <div class="summary-grid">
            <div class="summary-card" style="--card-color: var(--success-color); --card-color-dark: #229954;">
                <div class="summary-title">
                    <i class="fa fa-money-bill-wave"></i>
                    Cash Payments
                </div>
                <div class="summary-metrics">
                    <div class="summary-metric">
                        <div class="summary-value">Rp <?= number_format($sales_summary['cash_sales'] ?? 0, 0, ',', '.') ?></div>
                        <div class="summary-label">Total Amount</div>
                    </div>
                    <div class="summary-metric">
                        <div class="summary-value"><?= count(array_filter($payment_analysis, fn($p) => $p['payment_method'] === 'cash')) > 0 ? array_values(array_filter($payment_analysis, fn($p) => $p['payment_method'] === 'cash'))[0]['transaction_count'] : 0 ?></div>
                        <div class="summary-label">Transactions</div>
                    </div>
                </div>
            </div>
            
            <div class="summary-card" style="--card-color: var(--info-color); --card-color-dark: #2980b9;">
                <div class="summary-title">
                    <i class="fa fa-qrcode"></i>
                    QRIS Payments
                </div>
                <div class="summary-metrics">
                    <div class="summary-metric">
                        <div class="summary-value">Rp <?= number_format($sales_summary['qris_sales'] ?? 0, 0, ',', '.') ?></div>
                        <div class="summary-label">Total Amount</div>
                    </div>
                    <div class="summary-metric">
                        <div class="summary-value"><?= count(array_filter($payment_analysis, fn($p) => $p['payment_method'] === 'qris')) > 0 ? array_values(array_filter($payment_analysis, fn($p) => $p['payment_method'] === 'qris'))[0]['transaction_count'] : 0 ?></div>
                        <div class="summary-label">Transactions</div>
                    </div>
                </div>
            </div>
            
            <div class="summary-card" style="--card-color: var(--warning-color); --card-color-dark: #e67e22;">
                <div class="summary-title">
                    <i class="fa fa-credit-card"></i>
                    Credit Sales
                </div>
                <div class="summary-metrics">
                    <div class="summary-metric">
                        <div class="summary-value">Rp <?= number_format($sales_summary['credit_sales'] ?? 0, 0, ',', '.') ?></div>
                        <div class="summary-label">Total Amount</div>
                    </div>
                    <div class="summary-metric">
                        <div class="summary-value"><?= count(array_filter($payment_analysis, fn($p) => $p['payment_method'] === 'credit')) > 0 ? array_values(array_filter($payment_analysis, fn($p) => $p['payment_method'] === 'credit'))[0]['transaction_count'] : 0 ?></div>
                        <div class="summary-label">Transactions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REPORT TYPE SPECIFIC CONTENT -->
    <?php if ($report_type === 'summary'): ?>
        <!-- EXECUTIVE SUMMARY - Hanya tampilkan KPI utama -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa fa-chart-bar"></i>
                        Executive Summary
                    </div>
                    <div class="card-subtitle">High-level overview of key performance metrics</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; text-align: center; margin-bottom: 30px;">
                <div style="padding: 20px; background: rgba(39, 174, 96, 0.1); border-radius: 10px; border: 2px solid #27ae60;">
                    <div style="font-size: 24px; font-weight: bold; color: #27ae60; margin-bottom: 10px;">
                        Rp <?= number_format($sales_summary['total_sales'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div style="font-size: 12px; color: #ccc;">Total Sales</div>
                </div>
                
                <div style="padding: 20px; background: rgba(52, 152, 219, 0.1); border-radius: 10px; border: 2px solid #3498db;">
                    <div style="font-size: 24px; font-weight: bold; color: #3498db; margin-bottom: 10px;">
                        <?= ($sales_summary['total_orders'] ?? 0) ?>
                    </div>
                    <div style="font-size: 12px; color: #ccc;">Total Transactions</div>
                </div>
                
                <div style="padding: 20px; background: rgba(243, 156, 18, 0.1); border-radius: 10px; border: 2px solid #f39c12;">
                    <div style="font-size: 24px; font-weight: bold; color: #f39c12; margin-bottom: 10px;">
                        Rp <?= number_format($sales_summary['avg_transaction'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div style="font-size: 12px; color: #ccc;">Average Transaction</div>
                </div>
                
                <div style="padding: 20px; background: rgba(155, 89, 182, 0.1); border-radius: 10px; border: 2px solid #9b59b6;">
                    <div style="font-size: 24px; font-weight: bold; color: #9b59b6; margin-bottom: 10px;">
                        <?= number_format($profit_margin, 1) ?>%
                    </div>
                    <div style="font-size: 12px; color: #ccc;">Profit Margin</div>
                </div>
            </div>
            
            <!-- Top 5 Products only for summary -->
            <?php if (!empty($product_analysis)): ?>
            <h3 style="margin-top: 30px; color: var(--accent-color); margin-bottom: 15px;">
                <i class="fa fa-star"></i> Top 5 Products
            </h3>
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Profit Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($product_analysis, 0, $product_limit) as $product): ?>
                        <tr>
                            <td><strong><?= e($product['name']) ?></strong></td>
                            <td><?= (int)$product['qty_sold'] ?>x</td>
                            <td>Rp <?= number_format($product['gross_revenue'], 0, ',', '.') ?></td>
                            <td style="color: <?= $product['profit_margin'] > 50 ? 'var(--success-color)' : ($product['profit_margin'] > 20 ? 'var(--warning-color)' : 'var(--danger-color)') ?>; font-weight: 600;">
                                <?= number_format($product['profit_margin'], 1) ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <?php elseif ($report_type === 'detailed'): ?>
        <!-- DETAILED ANALYSIS - Tampilkan semua detail -->
        
        <!-- Daily Performance Analysis -->
        <?php if (!empty($daily_breakdown)): ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa fa-calendar-alt"></i>
                        Daily Performance Analysis (Detailed)
                    </div>
                    <div class="card-subtitle">Comprehensive day-by-day breakdown with trends</div>
                </div>
            </div>
            
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Total Sales</th>
                            <th>Cash</th>
                            <th>QRIS</th>
                            <th>Credit</th>
                            <th>Avg/Order</th>
                            <th>Growth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $prev_sales = 0;
                        foreach ($daily_breakdown as $i => $day): 
                            $daily_growth = $prev_sales > 0 ? (($day['daily_sales'] - $prev_sales) / $prev_sales) * 100 : 0;
                            $prev_sales = $day['daily_sales'];
                        ?>
                        <tr>
                            <td><strong><?= date('D, d M Y', strtotime($day['sale_date'])) ?></strong></td>
                            <td><span style="font-weight: 600; color: var(--info-color);"><?= (int)$day['total_orders'] ?></span></td>
                            <td><strong style="color: var(--success-color);">Rp <?= number_format($day['daily_sales'], 0, ',', '.') ?></strong></td>
                            <td>Rp <?= number_format($day['cash_sales'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($day['qris_sales'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($day['credit_sales'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($day['avg_transaction'], 0, ',', '.') ?></td>
                            <td>
                                <span style="color: <?= $daily_growth >= 0 ? 'var(--success-color)' : 'var(--danger-color)' ?>; font-weight: 600;">
                                    <?= $daily_growth >= 0 ? '+' : '' ?><?= number_format($daily_growth, 1) ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Extended Product Analysis -->
        <?php if (!empty($product_analysis)): ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa fa-trophy"></i>
                        Complete Product Performance Analysis
                    </div>
                    <div class="card-subtitle">All products with detailed metrics and profitability</div>
                </div>
            </div>
            
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Cost</th>
                            <th>Profit</th>
                            <th>Margin</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($product_analysis, 0, $product_limit) as $index => $product): 
                            $margin = $product['profit_margin'];
                            $margin_color = $margin > 50 ? 'var(--success-color)' : ($margin > 20 ? 'var(--warning-color)' : 'var(--danger-color)');
                            $performance = $margin > 50 ? 'Excellent' : ($margin > 20 ? 'Good' : 'Poor');
                        ?>
                        <tr>
                            <td><strong><?= $index + 1 ?></strong></td>
                            <td>
                                <div>
                                    <strong><?= e($product['name']) ?></strong>
                                    <br><small style="color: #666;"><?= e($product['sku'] ?? '-') ?></small>
                                </div>
                            </td>
                            <td><?= ucfirst($product['category'] ?? 'other') ?></td>
                            <td><strong><?= (int)$product['qty_sold'] ?>x</strong></td>
                            <td>Rp <?= number_format($product['gross_revenue'], 0, ',', '.') ?></td>
                            <td>Rp <?= number_format($product['total_cost'], 0, ',', '.') ?></td>
                            <td><strong style="color: var(--success-color);">Rp <?= number_format($product['gross_profit'], 0, ',', '.') ?></strong></td>
                            <td>
                                <span style="color: <?= $margin_color ?>; font-weight: 600;">
                                    <?= number_format($margin, 1) ?>%
                                </span>
                            </td>
                            <td>
                                <span style="color: <?= $margin_color ?>; font-weight: 600;">
                                    <?= $performance ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($report_type === 'comparison'): ?>
        <!-- PERIOD COMPARISON - Tampilkan perbandingan dengan periode sebelumnya -->
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa fa-chart-line"></i>
                        Period Comparison Analysis
                    </div>
                    <div class="card-subtitle">Current period vs previous period performance comparison</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div style="padding: 20px; background: rgba(39, 174, 96, 0.1); border-radius: 10px; border: 2px solid #27ae60;">
                    <h4 style="margin-top: 0; color: #27ae60;">Current Period</h4>
                    <div style="font-size: 24px; font-weight: bold; color: #27ae60; margin: 10px 0;">
                        Rp <?= number_format($sales_summary['total_sales'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <?= date('d M Y', strtotime($date_from)) ?> - <?= date('d M Y', strtotime($date_to)) ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <strong><?= ($sales_summary['total_orders'] ?? 0) ?></strong> transactions<br>
                        <strong>Rp <?= number_format($sales_summary['avg_transaction'] ?? 0, 0, ',', '.') ?></strong> average
                    </div>
                </div>
                
                <div style="padding: 20px; background: rgba(52, 152, 219, 0.1); border-radius: 10px; border: 2px solid #3498db;">
                    <h4 style="margin-top: 0; color: #3498db;">Previous Period</h4>
                    <div style="font-size: 24px; font-weight: bold; color: #3498db; margin: 10px 0;">
                        Rp <?= number_format($prev_sales, 0, ',', '.') ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <?= date('d M Y', strtotime($prev_date_from)) ?> - <?= date('d M Y', strtotime($prev_date_to)) ?>
                    </div>
                    <div style="margin-top: 10px;">
                        <small>Previous period comparison base</small>
                    </div>
                </div>
                
                <div style="padding: 20px; background: rgba(243, 156, 18, 0.1); border-radius: 10px; border: 2px solid #f39c12;">
                    <h4 style="margin-top: 0; color: #f39c12;">Growth Analysis</h4>
                    <div style="font-size: 24px; font-weight: bold; color: <?= $growth_rate >= 0 ? '#27ae60' : '#e74c3c' ?>; margin: 10px 0;">
                        <?= $growth_rate >= 0 ? '+' : '' ?><?= number_format($growth_rate, 1) ?>%
                    </div>
                    <div style="font-size: 12px; color: #666;">Growth Rate</div>
                    <div style="margin-top: 10px;">
                        <strong style="color: <?= $growth_rate >= 0 ? '#27ae60' : '#e74c3c' ?>">
                            <?= $growth_rate >= 0 ? 'GROWING' : 'DECLINING' ?>
                        </strong><br>
                        <small>
                            Rp <?= number_format(abs($total_revenue - $prev_sales), 0, ',', '.') ?> 
                            <?= $growth_rate >= 0 ? 'increase' : 'decrease' ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Comparison Insights -->
            <div style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 10px;">
                <h4 style="color: var(--accent-color); margin-top: 0;">
                    <i class="fa fa-lightbulb"></i> Performance Insights
                </h4>
                <ul style="color: #ccc; line-height: 1.8;">
                    <?php if ($growth_rate > 10): ?>
                        <li style="color: var(--success-color);">🚀 <strong>Excellent Growth:</strong> Sales increased by <?= number_format($growth_rate, 1) ?>% compared to previous period</li>
                    <?php elseif ($growth_rate > 0): ?>
                        <li style="color: var(--warning-color);">📈 <strong>Positive Growth:</strong> Sales grew by <?= number_format($growth_rate, 1) ?>%, showing steady improvement</li>
                    <?php elseif ($growth_rate > -10): ?>
                        <li style="color: var(--warning-color);">⚠️ <strong>Minor Decline:</strong> Sales dropped by <?= number_format(abs($growth_rate), 1) ?>%, requires attention</li>
                    <?php else: ?>
                        <li style="color: var(--danger-color);">🔻 <strong>Significant Decline:</strong> Sales fell by <?= number_format(abs($growth_rate), 1) ?>%, immediate action needed</li>
                    <?php endif; ?>
                    
                    <li>📊 Average transaction: Rp <?= number_format($sales_summary['avg_transaction'] ?? 0, 0, ',', '.') ?></li>
                    <li>🎯 Total transactions: <?= ($sales_summary['total_orders'] ?? 0) ?> orders</li>
                    <li>💰 Estimated profit margin: <?= number_format($profit_margin, 1) ?>%</li>
                </ul>
            </div>
        </div>

        <!-- Product Comparison for comparison type -->
        <?php if (!empty($product_analysis)): ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">
                        <i class="fa fa-trophy"></i>
                        Product Performance Ranking
                    </div>
                    <div class="card-subtitle">Top performing products for comparison period</div>
                </div>
            </div>
            
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Product</th>
                            <th>Qty Sold</th>
                            <th>Revenue</th>
                            <th>Profit</th>
                            <th>Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($product_analysis, 0, $product_limit) as $index => $product): ?>
                        <tr>
                            <td><strong><?= $index + 1 ?></strong></td>
                            <td>
                                <div>
                                    <strong><?= e($product['name']) ?></strong>
                                    <br><small style="color: #666;"><?= e($product['sku'] ?? '-') ?></small>
                                </div>
                            </td>
                            <td><strong><?= (int)$product['qty_sold'] ?>x</strong></td>
                            <td>Rp <?= number_format($product['gross_revenue'], 0, ',', '.') ?></td>
                            <td><strong style="color: var(--success-color);">Rp <?= number_format($product['gross_profit'], 0, ',', '.') ?></strong></td>
                            <td>
                                <span style="color: <?= $product['profit_margin'] > 50 ? 'var(--success-color)' : ($product['profit_margin'] > 20 ? 'var(--warning-color)' : 'var(--danger-color)') ?>; font-weight: 600;">
                                    <?= number_format($product['profit_margin'], 1) ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick date setters
    window.setQuickDate = function(period) {
        const today = new Date();
        let startDate, endDate;
        
        switch(period) {
            case 'today':
                startDate = endDate = today.toISOString().split('T')[0];
                break;
            case 'week':
                const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
                startDate = weekStart.toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case 'month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
        }
        
        document.querySelector('input[name="date_from"]').value = startDate;
        document.querySelector('input[name="date_to"]').value = endDate;
    };
    
    // Auto-submit form when report type changes
    const reportTypeSelect = document.querySelector('select[name="type"]');
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            this.form.submit();
        });
    }
});
</script>
</body>
</html>
