<?php
header('Content-Type: application/json');
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/auth.php';
require_once __DIR__.'/../src/lib/db.php';

try {
  auth_required(['admin','superadmin','spv','kasir']);
  $u = auth_user();
  $branch_id = $u['branch_id'];
  $user_id = $u['id'];

  $data = json_decode(file_get_contents('php://input'), true);

  if(empty($data['items'])) {
    throw new Exception('Keranjang kosong');
  }

  $items = $data['items'];
  $customer_name = $data['customer_name'] ?? '';
  $employee_id = $data['employee_id'] ?? null;
  $transaction_type = $data['transaction_type'] ?? 'direct';
  $method = $data['method'] ?? 'cash';
  
  // TAMBAHAN BARU: Ambil tanggal transaksi dari request
  $transaction_date = $data['transaction_date'] ?? date('Y-m-d');
  
  // Validasi tanggal tidak boleh lebih dari hari ini
  if (strtotime($transaction_date) > strtotime(date('Y-m-d'))) {
    throw new Exception('Tanggal transaksi tidak boleh lebih dari hari ini');
  }
  
  // Buat timestamp lengkap: tanggal yang dipilih + waktu sekarang
  $created_at = $transaction_date . ' ' . date('H:i:s');

  $subtotal = 0;
  foreach($items as $it){
    $item_subtotal = $it['price'] * $it['qty'];
    $discount = $item_subtotal * ($it['disc']/100);
    $subtotal += ($item_subtotal-$discount);
  }
  $tax_percent = floatval($data['tax'] ?? 0);
  $service_percent = floatval($data['service'] ?? 0);
  $tax_amount = round($subtotal * $tax_percent / 100);
  $service_amount = round($subtotal * $service_percent / 100);
  $total = $subtotal+$tax_amount+$service_amount;
  $order_type = $data['order_type'] ?? 'dinein';
  $table_no = $data['table_no'] ?? '';

  $db = db();
  $db->beginTransaction();

  try {
    // Tentukan status dan payment method
    if($transaction_type === 'open_bill'){
      // Open bill untuk customer biasa (bukan kredit)
      $status = 'pending';
      $payment_method = 'unpaid';
    } else {
      // Direct payment (termasuk kredit)
      if($method === 'credit'){
        $status = 'credit';
        $payment_method = 'credit';
      } else {
        $status = 'paid';
        $payment_method = $method;
      }
    }

    // PERBAIKAN: Gunakan timestamp custom dari input tanggal
    $stmt = $db->prepare("INSERT INTO orders
      (user_id, branch_id, customer_name, order_type, table_no,
      subtotal, tax_percent, tax_amount, service_percent, service_amount,
      total, payment_method, status, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      $user_id, $branch_id, $customer_name, $order_type, $table_no,
      $subtotal, $tax_percent, $tax_amount, $service_percent, $service_amount,
      $total, $payment_method, $status, $created_at // PAKAI TANGGAL CUSTOM
    ]);

    $order_id = $db->lastInsertId();

    $stmt_item = $db->prepare("INSERT INTO order_items
      (order_id, product_id, product_name, qty, price, quantity, discount)
      VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach($items as $it){
      $stmt_item->execute([
        $order_id, $it['id'], $it['name'], $it['qty'], $it['price'], $it['qty'], $it['disc']
      ]);
    }

    // Update stok hanya jika bukan open_bill atau jika kredit (kredit langsung bayar)
    if($transaction_type !== 'open_bill'){
      foreach($items as $it){
        $stmt_stock = $db->prepare("UPDATE stock_branch SET quantity = quantity - ? WHERE product_id = ? AND branch_id = ?");
        $stmt_stock->execute([$it['qty'], $it['id'], $branch_id]);
      }
    }

    // Jika kredit (selalu direct payment), buat entry di credits
    if($method === 'credit' && $employee_id){
      $stmt_emp = $db->prepare("SELECT name FROM employees WHERE id = ?");
      $stmt_emp->execute([$employee_id]);
      $emp = $stmt_emp->fetch();
      if(!$emp) throw new Exception('Data karyawan tidak ditemukan');

      $stmt_credit = $db->prepare("INSERT INTO credits
        (order_id, employee_id, employee_name, customer_name, total_amount, paid_amount, status, branch_id, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, 0, 'unpaid', ?, ?, ?)");
      $stmt_credit->execute([
        $order_id, $employee_id, $emp['name'], $customer_name, $total, $branch_id, $user_id, $created_at
      ]);
    }

    // Jika pembayaran tunai (cash), simpan data ke cash_transactions
    if($method === 'cash' && isset($data['cash_given']) && $data['cash_given'] > 0){
      $cash_given = floatval($data['cash_given']);
      $change_amount = $cash_given - $total;
      
      $stmt_cash = $db->prepare("INSERT INTO cash_transactions
        (order_id, cash_given, change_amount, created_at)
        VALUES (?, ?, ?, ?)");
      $stmt_cash->execute([$order_id, $cash_given, $change_amount, $created_at]);
    }

    $db->commit();

    $message = '';
    if($transaction_type === 'open_bill'){
      $message = 'Open bill berhasil dibuat.';
    } else if($method === 'credit'){
      $message = 'Order berhasil dibuat dan kredit dicatat.';
    } else {
      $message = 'Order dan pembayaran berhasil.';
    }

    echo json_encode([
      'success' => true,
      'order_id' => $order_id,
      'total' => $total,
      'transaction_date' => $transaction_date,
      'message' => $message
    ]);

  } catch(Exception $e){
    $db->rollBack();
    throw $e;
  }
} catch(Exception $e){
  http_response_code(400);
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}
