<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/lib/db.php';
require_once __DIR__ . '/../src/lib/utils.php';

try {
  $data = json_decode(file_get_contents('php://input'), true);
  if (!is_array($data)) {
    throw new Exception('Payload tidak valid');
  }

  if (empty($data['items']) || !is_array($data['items'])) {
    throw new Exception('Keranjang kosong');
  }

  $branch_id = isset($data['branch_id']) ? (int)$data['branch_id'] : 0;
  if ($branch_id <= 0) {
    throw new Exception('branch_id tidak valid');
  }

  // Validasi branch
  $st = db()->prepare('SELECT id FROM branches WHERE id = ? LIMIT 1');
  $st->execute([$branch_id]);
  if (!$st->fetch()) {
    throw new Exception('Cabang tidak ditemukan');
  }

  $items = $data['items'];
  $customer_name = trim((string)($data['customer_name'] ?? ''));
  $order_type = $data['order_type'] ?? 'dinein';
  if (!in_array($order_type, ['dinein', 'takeaway'], true)) {
    $order_type = 'dinein';
  }
  $table_no = trim((string)($data['table_no'] ?? ''));

  // Customer: pajak otomatis 10% (sesuai permintaan)
  $tax_percent = 10;
  $service_percent = floatval($data['service'] ?? 0);
  if ($service_percent < 0 || $service_percent > 100) $service_percent = 0;


  $method = $data['method'] ?? 'cash';
  if (!in_array($method, ['cash', 'qris'], true)) {
    // Customer menu hanya mendukung cash/qris
    throw new Exception('Metode pembayaran tidak didukung untuk customer');
  }

  $transaction_date = $data['transaction_date'] ?? date('Y-m-d');
  if (strtotime($transaction_date) === false) {
    throw new Exception('Tanggal transaksi tidak valid');
  }

  $created_at = $transaction_date . ' ' . date('H:i:s');

  // Hitung subtotal
  $subtotal = 0;
  foreach ($items as $it) {
    $pid = (int)($it['id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    $price = (int)($it['price'] ?? 0);
    $discPct = (floatval($it['disc'] ?? 0));

    if ($pid <= 0 || $qty <= 0 || $price < 0) {
      throw new Exception('Item tidak valid');
    }
    if ($discPct < 0) $discPct = 0;
    if ($discPct > 100) $discPct = 0;

    $item_subtotal = $price * $qty;
    $discount = $item_subtotal * ($discPct / 100);
    $subtotal += ($item_subtotal - $discount);
  }

  $tax_amount = round($subtotal * $tax_percent / 100);
  $service_amount = round($subtotal * $service_percent / 100);
  $total = $subtotal + $tax_amount + $service_amount;


  if ($total <= 0) {
    throw new Exception('Total transaksi tidak valid');
  }

  $db = db();
  $db->beginTransaction();

  try {
    // Cek stok (opsional tapi disarankan)
    // Kita cek dulu untuk menghindari stok minus.
    $checkStmt = $db->prepare('SELECT quantity FROM stock_branch WHERE product_id = ? AND branch_id = ?');
    foreach ($items as $it) {
      $pid = (int)$it['id'];
      $qty = (int)$it['qty'];
      $checkStmt->execute([$pid, $branch_id]);
      $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
      $stockQty = $row ? (int)$row['quantity'] : 0;
      if ($stockQty < $qty) {
        throw new Exception('Stok tidak cukup untuk produk ID ' . $pid);
      }
    }

    $status = 'paid';
    $payment_method = ($method === 'qris') ? 'qris' : 'cash';

    // Insert orders
    $stmtOrder = $db->prepare("INSERT INTO orders
      (user_id, branch_id, customer_name, order_type, table_no,
      subtotal, tax_percent, tax_amount, service_percent, service_amount,
      total, payment_method, status, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // user_id untuk customer kiosk belum ada, pakai NULL tidak bisa karena schema user_id NOT NULL
    // Maka kita ambil user default: ambil role 'kasir' pertama pada branch.
    $stUser = $db->prepare("SELECT id FROM users WHERE branch_id = ? AND role IN ('kasir','spv','admin','superadmin') ORDER BY id ASC LIMIT 1");
    $stUser->execute([$branch_id]);
    $defaultUserId = (int)($stUser->fetch()['id'] ?? 0);
    if ($defaultUserId <= 0) {
      throw new Exception('Tidak ada user internal untuk pencatatan transaksi');
    }

    $stmtOrder->execute([
      $defaultUserId,
      $branch_id,
      $customer_name,
      $order_type,
      $table_no,
      $subtotal,
      $tax_percent,
      $tax_amount,
      $service_percent,
      $service_amount,
      $total,
      $payment_method,
      $status,
      $created_at
    ]);

    $order_id = (int)$db->lastInsertId();

    // Insert order_items
    $stmtItem = $db->prepare("INSERT INTO order_items
      (order_id, product_id, product_name, qty, price, quantity, discount)
      VALUES (?, ?, ?, ?, ?, ?, ?)" );

    $stmtProd = $db->prepare('SELECT name FROM products WHERE id = ? LIMIT 1');

    foreach ($items as $it) {
      $pid = (int)$it['id'];
      $qty = (int)$it['qty'];
      $price = (int)$it['price'];
      $discPct = (int)($it['disc'] ?? 0);

      $stmtProd->execute([$pid]);
      $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);
      if (!$prod) {
        throw new Exception('Produk tidak ditemukan: ' . $pid);
      }

      $stmtItem->execute([
        $order_id,
        $pid,
        $prod['name'],
        $qty,
        $price,
        $qty,
        $discPct
      ]);
    }

    // Update stok
    $stmtStock = $db->prepare('UPDATE stock_branch SET quantity = quantity - ? WHERE product_id = ? AND branch_id = ?');
    foreach ($items as $it) {
      $pid = (int)$it['id'];
      $qty = (int)$it['qty'];
      $stmtStock->execute([$qty, $pid, $branch_id]);
    }

    // cash_transactions only for cash
    if ($method === 'cash') {
      // Untuk customer, cash_given tidak tersedia, anggap cash_given = total
      $cash_given = (float)$total;
      $change_amount = 0;

      $stmtCash = $db->prepare('INSERT INTO cash_transactions (order_id, cash_given, change_amount, created_at) VALUES (?, ?, ?, ?)');
      $stmtCash->execute([$order_id, $cash_given, $change_amount, $created_at]);
    }

    $db->commit();

    echo json_encode([
      'success' => true,
      'order_id' => $order_id,
      'total' => (int)$total,
      'transaction_date' => $transaction_date,
      'message' => 'Transaksi customer berhasil'
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

