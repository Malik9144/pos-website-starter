<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__.'/../src/lib/permissions.php';
auth_required(['admin','superadmin','spv','kasir']);

$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare('SELECT o.*, u.name cashier, b.name branch 
                     FROM orders o 
                     JOIN users u ON u.id=o.user_id 
                     JOIN branches b ON b.id=o.branch_id 
                     WHERE o.id=?');
$st->execute([$id]);
$o = $st->fetch();

if(!$o){ die("Order not found"); }

$is_credit = ($o['status'] === 'credit');
$credit_info = null;
if ($is_credit) {
    $credit_st = db()->prepare('SELECT * FROM credits WHERE order_id = ?');
    $credit_st->execute([$id]);
    $credit_info = $credit_st->fetch();
}

$is_cash = ($o['payment_method'] === 'cash');
$cash_given = 0;
$change = 0;
if ($is_cash) {
    $cash_st = db()->prepare('SELECT cash_given, change_amount FROM cash_transactions WHERE order_id = ? LIMIT 1');
    $cash_st->execute([$id]);
    $cash_details = $cash_st->fetch();
    
    if ($cash_details) {
        $cash_given = $cash_details['cash_given'];
        $change = $cash_details['change_amount'];
    }
}

// Query SEMUA items dengan kategori DAN NOTES
$it = db()->prepare('SELECT oi.*, p.name, p.category 
                     FROM order_items oi 
                     JOIN products p ON p.id=oi.product_id 
                     WHERE order_id=? 
                     ORDER BY oi.id');
$it->execute([$id]);
$all_items = $it->fetchAll();

// Pisahkan items berdasarkan kategori
$drink_items = [];
$food_items = [];
foreach($all_items as $item) {
    if ($item['category'] === 'drink') {
        $drink_items[] = $item;
    } elseif ($item['category'] === 'food') {
        $food_items[] = $item;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Print - Order #<?= $id ?></title>
<style>
/* OPTIMIZED FOR POS-80 THERMAL PRINTER */
@page { 
    margin: 0;
    size: 80mm auto;
}

* { 
    margin: 0; 
    padding: 0; 
    box-sizing: border-box; 
}

html, body {
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    background: white;
}

body { 
    font-family: Arial, sans-serif;
    font-size: 12px;
    line-height: 1.25;
    color: black;
    width: 74mm;
    max-width: 74mm;
    margin: 0 auto;
    padding: 0;
}

.center { text-align: center; }
.bold { font-weight: bold; }
.line { border-bottom: 1px solid black; margin: 2px 0; }
.dline { border-bottom: 2px solid black; margin: 2px 0; }

table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 12px; 
}

td.item-desc { 
    width: 50%;
    padding: 1px 0;
    text-align: left;
}

td.item-price { 
    width: 50%;
    padding: 1px 0;
    text-align: right;
    padding-right: 35px;
}

.right { text-align: right; }
.item-row { padding: 2px 0; }
.big { font-size: 11px; }

/* PPN Highlight */
.ppn-row {
    background-color: #fffacd;
    font-weight: bold;
}

.dpp-info {
    font-size: 10px;
    font-style: italic;
    color: #555;
}

/* THERMAL FRIENDLY NOTES - TANPA BORDER/BOX */
.item-notes {
    margin: 2px 0 2px 10px;
    font-size: 10px;
    font-style: italic;
}

/* CO NOTES - SIMPLE & BOLD */
.co-notes {
    margin: 3px 0;
    font-size: 11px;
    font-weight: bold;
}

/* Section separator untuk CO */
.section-separator {
    margin: 5px 0;
    padding: 2px 0;
    text-align: center;
    border-top: 2px dashed #000;
    border-bottom: 2px dashed #000;
}

.scissors {
    font-size: 16px;
}

/* Section styling */
.co-section {
    margin-top: 15px;
    page-break-before: auto;
}

.co-header {
    padding: 3px;
    margin: 5px 0;
    text-align: center;
    font-weight: bold;
    font-size: 13px;
}

@media print {
    body { 
        width: 74mm !important;
        max-width: 74mm !important;
        margin: 0 auto !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .no-print { 
        display: none !important;
    }
    
    .section-separator {
        page-break-before: auto;
        page-break-after: avoid;
    }
    
    .ppn-row {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>
<body>
<div class="no-print" style="text-align:center; padding:10px; background:#f0f0f0; margin-bottom:10px;">
    <button onclick="window.print()" style="padding:8px 15px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">🖨️ PRINT</button>
    <button onclick="window.close()" style="padding:8px 15px; background:#dc3545; color:white; border:none; border-radius:5px; cursor:pointer; margin-left:5px; font-weight:bold;">❌ TUTUP</button>
</div>

<!-- ================================================ -->
<!-- SECTION 1: STRUK CUSTOMER -->
<!-- ================================================ -->
<div class="center bold big"><?= strtoupper(APP_NAME) ?></div>
<div class="center"><?= e($o['branch']) ?></div>
<div class="center bold">STRUK KASIR</div>
<div class="dline"></div>

<?php if ($is_credit): ?>
<div class="center bold">*** KREDIT ***</div>
<?php if ($credit_info): ?>
<div>Cust: <?= e($credit_info['customer_name']) ?></div>
<?php if ($credit_info['due_date']): ?>
<div>Tempo: <?= date('d/m/Y', strtotime($credit_info['due_date'])) ?></div>
<?php endif; ?>
<?php endif; ?>
<div class="line"></div>
<?php endif; ?>

<div>Order #<?= $o['id'] ?></div>
<?php if (!empty($o['order_type'])): ?>
<div>Tipe: <?= $o['order_type']=='dinein' ? 'Dine In' : 'Take Away' ?></div>
<?php endif; ?>
<?php if (!empty($o['table_no']) && $o['order_type']=='dinein'): ?>
<div class="bold">Meja: <?= e($o['table_no']) ?></div>
<?php endif; ?>
<div>Kasir: <?= e($o['cashier']) ?></div>
<div>Bayar: <?= e($o['payment_method']) ?><?= $is_credit ? ' [KREDIT]' : '' ?></div>
<?php if (!empty($o['customer_name'])): ?>
<div>Cust: <?= e($o['customer_name']) ?></div>
<?php endif; ?>
<?php if (!empty($o['employee_name'])): ?>
<div>Kary: <?= e($o['employee_name']) ?></div>
<?php endif; ?>
<div><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
<div class="line"></div>

<?php if (empty($all_items)): ?>
<div class="center">Tidak ada item</div>
<?php else: ?>
    <?php $subtotal = 0; $num = 1; ?>
    <?php foreach($all_items as $r): ?>
        <?php
        $qty = (int)$r['qty'];
        $disc = floatval($r['discount']);
        $harga = $r['price'];
        $sub_brg = $harga * $qty;
        $diskon_brg = round($sub_brg * $disc / 100);
        $sub_hasil = $sub_brg - $diskon_brg;
        $subtotal += $sub_hasil;
        $has_notes = !empty($r['notes']);
        ?>
        
        <div class="item-row">
            <div><?= $num ?>. <?= e($r['name']) ?></div>
            <table>
                <tr>
                    <td class="item-desc"><?= $qty ?> x Rp <?= number_format($harga,0,',','.') ?></td>
                    <td class="item-price bold">Rp <?= number_format($sub_hasil,0,',','.') ?></td>
                </tr>
            </table>
            <?php if ($disc > 0): ?>
            <div style="margin-left:10px;">Disc <?= $disc ?>%</div>
            <?php endif; ?>
            <?php if ($has_notes): ?>
            <div class="item-notes">* <?= e($r['notes']) ?></div>
            <?php endif; ?>
        </div>
        
        <?php $num++; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="line"></div>

<?php if (!empty($all_items)): ?>
    <?php
    // Ambil service dari database jika ada
    $svc_val = isset($o['service_value']) ? (int)$o['service_value'] : 0;
    $svc_pct = isset($o['service_percent']) ? (float)$o['service_percent'] : 0;
    
    // Hitung DPP (Dasar Pengenaan Pajak) = Subtotal + Service
    $dpp = $subtotal + $svc_val;
    
    // PPN 10% - Hitung dari DPP
    $ppn_percent = 10;
    $ppn_value = round($dpp * $ppn_percent / 100);
    
    // Gunakan nilai PPN dari database jika ada, atau gunakan perhitungan otomatis
    $tax_val = isset($o['tax_value']) && $o['tax_value'] > 0 ? (int)$o['tax_value'] : $ppn_value;
    $tax_pct = isset($o['tax_percent']) && $o['tax_percent'] > 0 ? (float)$o['tax_percent'] : $ppn_percent;
    
    // Total akhir
    $grand_total = $subtotal + $svc_val + $tax_val;
    
    // Gunakan total dari database jika ada
    if (isset($o['total']) && $o['total'] > 0) {
        $grand_total = $o['total'];
    }
    ?>
    
    <table>
        <tr>
            <td class="item-desc">Subtotal:</td>
            <td class="item-price">Rp <?= number_format($subtotal,0,',','.') ?></td>
        </tr>
        <?php if ($svc_val > 0): ?>
        <tr>
            <td class="item-desc">Service (<?= $svc_pct ?>%):</td>
            <td class="item-price">Rp <?= number_format($svc_val,0,',','.') ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <!-- DPP (Dasar Pengenaan Pajak) -->
    <div class="line"></div>
    <table>
        <tr style="background-color:#f5f5f5;">
            <td class="item-desc bold">DPP:</td>
            <td class="item-price bold">Rp <?= number_format($dpp,0,',','.') ?></td>
        </tr>
    </table>
    <div class="dpp-info" style="margin:2px 0 2px 5px;">
        (Dasar Pengenaan Pajak)
    </div>
    
    <!-- PPN 10% -->
    <table>
        <tr class="ppn-row">
            <td class="item-desc bold">PPN (<?= $tax_pct ?>%):</td>
            <td class="item-price bold">Rp <?= number_format($tax_val,0,',','.') ?></td>
        </tr>
    </table>
    
    <div class="dline"></div>
    
    <table>
        <tr class="bold big">
            <td class="item-desc">TOTAL:</td>
            <td class="item-price">Rp <?= number_format($grand_total,0,',','.') ?></td>
        </tr>
    </table>
    
    <?php if ($is_cash && $cash_given > 0): ?>
    <div class="dline"></div>
    <table>
        <tr>
            <td class="item-desc">Uang Diberikan:</td>
            <td class="item-price">Rp <?= number_format($cash_given,0,',','.') ?></td>
        </tr>
        <tr class="bold">
            <td class="item-desc">KEMBALIAN:</td>
            <td class="item-price">Rp <?= number_format($change,0,',','.') ?></td>
        </tr>
    </table>
    <?php elseif ($is_credit): ?>
    <div class="dline"></div>
    <div class="center bold">STATUS: KREDIT - BELUM LUNAS</div>
    <?php endif; ?>
<?php endif; ?>

<div class="line"></div>

<div class="center">
    <?php if ($is_credit): ?>
    <div class="bold">*** PEMBAYARAN KREDIT ***</div>
    <div>Harap segera dilunasi</div>
    <?php else: ?>
    <div class="bold">*** TERIMA KASIH ***</div>
    <div>Atas kunjungan Anda</div>
    <?php endif; ?>
</div>
<?php if (!empty($all_items) && $tax_val > 0): ?>
<div class="center" style="font-size:10px; margin-top:3px;">
    * Harga sudah termasuk PPN <?= $tax_pct ?>%
</div>

<?php endif; ?>

<!-- ================================================ -->
<!-- SECTION 2: CO BAR (jika ada minuman) -->
<!-- ================================================ -->
<?php if (!empty($drink_items)): ?>
<div class="section-separator">
    <div class="scissors">- - - - - - - - - - - - - -</div>
</div>

<div class="co-section">
    <div class="center bold big"><?= strtoupper(APP_NAME) ?></div>
    <div class="center"><?= e($o['branch']) ?></div>
    <div class="co-header">ORDER BAR</div>
    <div class="dline"></div>
    
    <div class="bold">Order #<?= $o['id'] ?> [URGENT]</div>
    <?php if (!empty($o['order_type'])): ?>
    <div>Tipe: <?= $o['order_type']=='dinein' ? 'Dine In' : 'Take Away' ?></div>
    <?php endif; ?>
    <?php if (!empty($o['table_no']) && $o['order_type']=='dinein'): ?>
    <div class="bold">Meja: <?= e($o['table_no']) ?></div>
    <?php endif; ?>
    <?php if (!empty($o['customer_name'])): ?>
    <div>Cust: <?= e($o['customer_name']) ?></div>
    <?php endif; ?>
    <div><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
    <div class="line"></div>
    
    <?php $num = 1; ?>
    <?php foreach($drink_items as $r): ?>
        <?php $has_notes = !empty($r['notes']); ?>
        <div class="bold big"><?= $num ?>. <?= e($r['name']) ?></div>
        <div class="bold" style="font-size:14px;">QTY: <?= (int)$r['qty'] ?></div>
        
        <?php if ($has_notes): ?>
        <div class="co-notes">>> CATATAN: <?= strtoupper(e($r['notes'])) ?></div>
        <?php endif; ?>
        
        <?php if ($num < count($drink_items)): ?>
        <div style="margin:5px 0;">................</div>
        <?php endif; ?>
        <?php $num++; ?>
    <?php endforeach; ?>
    
    <div class="line"></div>
    <div class="center bold">TOTAL: <?= count($drink_items) ?> ITEM</div>
    <div class="line"></div>
    
    <div class="center">
        <div class="bold">*** SEGERA SIAPKAN MINUMAN ***</div>
        <div>Print: <?= date('H:i:s') ?></div>
    </div>
</div>
<?php endif; ?>

<!-- ================================================ -->
<!-- SECTION 3: CO KITCHEN (jika ada makanan) -->
<!-- ================================================ -->
<?php if (!empty($food_items)): ?>
<div class="section-separator">
    <div class="scissors">- - - - - - - - - - - - - -</div>
</div>

<div class="co-section">
    <div class="center bold big"><?= strtoupper(APP_NAME) ?></div>
    <div class="center"><?= e($o['branch']) ?></div>
    <div class="co-header">ORDER KITCHEN</div>
    <div class="dline"></div>
    
    <div class="bold">Order #<?= $o['id'] ?> [URGENT]</div>
    <?php if (!empty($o['order_type'])): ?>
    <div>Tipe: <?= $o['order_type']=='dinein' ? 'Dine In' : 'Take Away' ?></div>
    <?php endif; ?>
    <?php if (!empty($o['table_no']) && $o['order_type']=='dinein'): ?>
    <div class="bold">Meja: <?= e($o['table_no']) ?></div>
    <?php endif; ?>
    <?php if (!empty($o['customer_name'])): ?>
    <div>Cust: <?= e($o['customer_name']) ?></div>
    <?php endif; ?>
    <div><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
    <div class="line"></div>
    
    <?php $num = 1; ?>
    <?php foreach($food_items as $r): ?>
        <?php $has_notes = !empty($r['notes']); ?>
        <div class="bold big"><?= $num ?>. <?= e($r['name']) ?></div>
        <div class="bold" style="font-size:14px;">QTY: <?= (int)$r['qty'] ?></div>
        
        <?php if ($has_notes): ?>
        <div class="co-notes">>> CATATAN: <?= strtoupper(e($r['notes'])) ?></div>
        <?php endif; ?>
        
        <?php if ($num < count($food_items)): ?>
        <div style="margin:5px 0;">................</div>
        <?php endif; ?>
        <?php $num++; ?>
    <?php endforeach; ?>
    
    <div class="line"></div>
    <div class="center bold">TOTAL: <?= count($food_items) ?> ITEM</div>
    <div class="line"></div>
    
    <div class="center">
        <div class="bold">*** SEGERA SIAPKAN MAKANAN ***</div>
        <div>Print: <?= date('H:i:s') ?></div>
    </div>
</div>
<?php endif; ?>

<script>
window.onload = function() {
    setTimeout(function() {
        window.print();
    }, 500);
};

window.onafterprint = function() {
    setTimeout(function() {
        window.close();
    }, 1000);
};
</script>
</body>
</html>


</style>
</head>
<body>
<div class="no-print" style="text-align:center; padding:10px; background:#f0f0f0; margin-bottom:10px;">
    <button onclick="window.print()" style="padding:8px 15px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">🖨️ PRINT</button>
    <button onclick="window.close()" style="padding:8px 15px; background:#dc3545; color:white; border:none; border-radius:5px; cursor:pointer; margin-left:5px; font-weight:bold;">❌ TUTUP</button>
</div>
