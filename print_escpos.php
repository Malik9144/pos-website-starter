<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__.'/../src/lib/permissions.php';
auth_required(['admin','superadmin','spv','kasir']);

$id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'cashier';

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

if ($type === 'bar') {
    $it = db()->prepare('SELECT oi.*, p.name FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE order_id=? AND p.category="drink" ORDER BY oi.id');
} elseif ($type === 'kitchen') {
    $it = db()->prepare('SELECT oi.*, p.name FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE order_id=? AND p.category="food" ORDER BY oi.id');
} else {
    $it = db()->prepare('SELECT oi.*, p.name FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE order_id=? ORDER BY oi.id');
}
$it->execute([$id]);
$items = $it->fetchAll();

$print_titles = ['cashier' => 'STRUK KASIR', 'bar' => 'ORDER BAR', 'kitchen' => 'ORDER KITCHEN'];
$print_title = $print_titles[$type] ?? 'STRUK';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Print - Order #<?= $id ?></title>
<style>
/* OPTIMIZED FOR POS-80 WITH BALANCED LAYOUT */
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

/* FIX: Balanced column width - tidak terlalu banyak space kosong */
td.item-desc { 
    width: 50%; /* Kolom kiri untuk deskripsi */
    padding: 1px 0;
    text-align: left;
}

td.item-price { 
    width: 50%; /* Kolom kanan untuk harga */
    padding: 1px 0;
    text-align: right;
    padding-right: 35px; /* Sedikit padding agar tidak terlalu mepet */
}

.right { text-align: right; }
.item-row { padding: 2px 0; }
.big { font-size: 11px; }

@media print {
    body { 
        width: 74mm !important;
        max-width: 74mm !important;
        margin: 0 auto !important;
        padding: 0 !important;
    }
    
    .no-print { 
        display: none !important;
    }
}
</style>
</head>
<body>
<div class="no-print" style="text-align:center; padding:10px; background:#f0f0f0; margin-bottom:10px;">
    <button onclick="window.print()" style="padding:8px 15px; background:#28a745; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">🖨️ PRINT</button>
    <button onclick="window.close()" style="padding:8px 15px; background:#dc3545; color:white; border:none; border-radius:5px; cursor:pointer; margin-left:5px; font-weight:bold;">❌ TUTUP</button>
</div>

<div class="center bold big"><?= strtoupper(APP_NAME) ?></div>
<div class="center"><?= e($o['branch']) ?></div>
<div class="center bold"><?= $print_title ?></div>
<div class="dline"></div>

<?php if ($is_credit && $type === 'cashier'): ?>
<div class="center bold">*** KREDIT ***</div>
<?php if ($credit_info): ?>
<div>Cust: <?= e($credit_info['customer_name']) ?></div>
<?php if ($credit_info['due_date']): ?>
<div>Tempo: <?= date('d/m/Y', strtotime($credit_info['due_date'])) ?></div>
<?php endif; ?>
<?php endif; ?>
<div class="line"></div>
<?php endif; ?>

<div>Order #<?= $o['id'] ?><?= $type !== 'cashier' ? ' [URGENT]' : '' ?></div>
<?php if (!empty($o['order_type'])): ?>
<div>Tipe: <?= $o['order_type']=='dinein' ? 'Dine In' : 'Take Away' ?></div>
<?php endif; ?>
<?php if (!empty($o['table_no']) && $o['order_type']=='dinein'): ?>
<div class="bold">Meja: <?= e($o['table_no']) ?></div>
<?php endif; ?>
<?php if ($type === 'cashier'): ?>
<div>Kasir: <?= e($o['cashier']) ?></div>
<div>Bayar: <?= e($o['payment_method']) ?><?= $is_credit ? ' [KREDIT]' : '' ?></div>
<?php endif; ?>
<?php if (!empty($o['customer_name'])): ?>
<div>Cust: <?= e($o['customer_name']) ?></div>
<?php endif; ?>
<?php if (!empty($o['employee_name'])): ?>
<div>Kary: <?= e($o['employee_name']) ?></div>
<?php endif; ?>
<div><?= date('d/m/Y H:i', strtotime($o['created_at'])) ?></div>
<div class="line"></div>

<?php if (empty($items)): ?>
<div class="center">Tidak ada item</div>
<?php else: ?>
    <?php $subtotal = 0; $num = 1; ?>
    <?php foreach($items as $r): ?>
        <?php
        $qty = (int)$r['qty'];
        $disc = floatval($r['discount']);
        $harga = $r['price'];
        $sub_brg = $harga * $qty;
        $diskon_brg = round($sub_brg * $disc / 100);
        $sub_hasil = $sub_brg - $diskon_brg;
        $subtotal += $sub_hasil;
        ?>
        
        <?php if ($type !== 'cashier'): ?>
        <div class="bold big"><?= $num ?>. <?= e($r['name']) ?></div>
        <div class="bold">QTY: <?= $qty ?></div>
        <?php if ($num < count($items)): ?>
        <div style="margin:5px 0;">................</div>
        <?php endif; ?>
        <?php else: ?>
        <div class="item-row">
            <div><?= e($r['name']) ?></div>
            <table>
                <tr>
                    <td class="item-desc"><?= $qty ?> x Rp <?= number_format($harga,0,',','.') ?></td>
                    <td class="item-price bold">Rp <?= number_format($sub_hasil,0,',','.') ?></td>
                </tr>
            </table>
            <?php if ($disc > 0): ?>
            <div style="margin-left:10px;">Disc <?= $disc ?>%</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php $num++; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="line"></div>

<?php if ($type === 'cashier' && !empty($items)): ?>
    <?php
    // Kode ASLI - tidak diubah
    $tax_val = isset($o['tax_value']) ? (int)$o['tax_value'] : 0;
    $svc_val = isset($o['service_value']) ? (int)$o['service_value'] : 0;
    $tax_pct = isset($o['tax_percent']) ? (float)$o['tax_percent'] : 0;
    $svc_pct = isset($o['service_percent']) ? (float)$o['service_percent'] : 0;
    
    // TAMBAHAN: Hitung DPP dan PPN 10% jika tidak ada di database
    $dpp = $subtotal + $svc_val; // DPP = Subtotal + Service
    $ppn_percent = 10;
    if ($tax_val == 0 && $tax_pct == 0) {
        // Jika tidak ada pajak di database, hitung PPN 10%
        $tax_val = round($dpp * $ppn_percent / 100);
        $tax_pct = $ppn_percent;
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
    
    <!-- TAMBAHAN: Section DPP dan PPN 10% -->
    <?php if ($tax_val > 0): ?>
    <div class="line"></div>
    <table>
        <tr>
            <td class="item-desc bold">DPP:</td>
            <td class="item-price bold">Rp <?= number_format($dpp,0,',','.') ?></td>
        </tr>
    </table>
    <div style="font-size:10px; font-style:italic; margin:1px 0 2px 5px;">
        (Dasar Pengenaan Pajak)
    </div>
    <table>
        <tr>
            <td class="item-desc bold">PPN (<?= $tax_pct ?>%):</td>
            <td class="item-price bold">Rp <?= number_format($tax_val,0,',','.') ?></td>
        </tr>
    </table>
    <?php endif; ?>
    <!-- AKHIR TAMBAHAN -->
    
    <div class="dline"></div>
    
    <table>
        <tr class="bold big">
            <td class="item-desc">TOTAL:</td>
            <td class="item-price">Rp <?= number_format($o['total'],0,',','.') ?></td>
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
    
<?php elseif ($type !== 'cashier' && !empty($items)): ?>
    <div class="center bold">TOTAL: <?= count($items) ?> ITEM</div>
<?php endif; ?>

<div class="line"></div>

<div class="center">
<?php if ($type === 'cashier'): ?>
    <?php if ($is_credit): ?>
    <div class="bold">*** PEMBAYARAN KREDIT ***</div>
    <div>Harap segera dilunasi</div>
    <?php else: ?>
    <div class="bold">*** TERIMA KASIH ***</div>
    <div>Atas kunjungan Anda</div>
    <?php endif; ?>
    <!-- TAMBAHAN: Notice PPN -->
    <?php if ($tax_val > 0): ?>
    <div style="font-size:10px; margin-top:3px;">
        * Harga sudah termasuk PPN <?= $tax_pct ?>%
    </div>
    <?php endif; ?>
    <!-- AKHIR TAMBAHAN -->
<?php elseif ($type === 'bar'): ?>
    <div class="bold">*** SEGERA SIAPKAN MINUMAN ***</div>
    <div>Print: <?= date('H:i:s') ?></div>
<?php elseif ($type === 'kitchen'): ?>
    <div class="bold">*** SEGERA SIAPKAN MAKANAN ***</div>
    <div>Print: <?= date('H:i:s') ?></div>
<?php endif; ?>
</div>

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
