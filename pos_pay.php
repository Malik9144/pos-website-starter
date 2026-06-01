<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/permissions.php';
require_once __DIR__.'/../src/lib/utils.php';
require_once __DIR__.'/../src/lib/auth.php';

// JSON response
header('Content-Type: application/json');

try {
    auth_required(['admin','superadmin','spv','kasir']);
    $u = auth_user();
    if (!$u) {
        http_response_code(401);
        echo json_encode(['message'=>'User tidak terautentik']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['message'=>'Invalid payload']);
        exit;
    }

    $items          = $payload['items'] ?? [];
    $method         = $payload['method'] ?? 'cash';
    $customer_name  = trim($payload['customer_name'] ?? '');
    $employee_id    = isset($payload['employee_id']) ? (int)$payload['employee_id'] : null;
    $tax_pct        = isset($payload['tax']) ? floatval($payload['tax']) : 0;
    $svc_pct        = isset($payload['service']) ? floatval($payload['service']) : 0;
    $order_type     = isset($payload['order_type']) ? trim($payload['order_type']) : null;
    $table_no       = isset($payload['table_no']) ? trim($payload['table_no']) : null;
    $cash_given     = isset($payload['cash_given']) ? floatval($payload['cash_given']) : 0;

    error_log("POS Payment Debug - Method: $method, Employee ID: " . ($employee_id ?: 'NULL') . ", Cash Given: $cash_given");

    // Validasi dasar
    if (empty($items)) {
        http_response_code(422);
        echo json_encode(['message'=>'Keranjang kosong']);
        exit;
    }
    if (!in_array($method, ['cash','qris','credit'], true)) {
        http_response_code(422);
        echo json_encode(['message'=>'Metode pembayaran tidak valid']);
        exit;
    }
    if ($method === 'credit' && empty($employee_id)) {
        http_response_code(422);
        echo json_encode(['message' => 'Karyawan wajib dipilih untuk pembayaran kredit']);
        exit;
    }

    $pdo = db();
    $pdo->beginTransaction();

    $employee_name = null;
    if ($employee_id) {
        $st = $pdo->prepare("SELECT name FROM employees WHERE id=? LIMIT 1");
        $st->execute([$employee_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['message' => "Karyawan tidak ditemukan di database (ID: $employee_id)"]);
            exit;
        }
        $employee_name = $row['name'];
        error_log("POS Payment - Found employee: $employee_name (ID: $employee_id)");
    }

    $order_status = ($method === 'credit') ? 'credit' : 'paid';

    // Simpan order header
    $stmt = $pdo->prepare('INSERT INTO orders
        (user_id, branch_id, total, payment_method, customer_name, employee_id, employee_name,
         tax_percent, service_percent, order_type, table_no, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, ?, NOW())');
    $stmt->execute([
        $u['id'], $u['branch_id'], 0, $method,
        $customer_name, $employee_id, $employee_name,
        $tax_pct, $svc_pct,
        $order_type, $table_no,
        $order_status
    ]);
    $order_id = $pdo->lastInsertId();

    $total = 0;

    $qProd = $pdo->prepare("
        SELECT p.name, IFNULL(sb.quantity,0) AS stock
        FROM products p
        LEFT JOIN stock_branch sb ON p.id=sb.product_id AND sb.branch_id=?
        WHERE p.id=? AND p.active=1
        LIMIT 1
    ");

    $insItem = $pdo->prepare('INSERT INTO order_items
        (order_id, product_id, qty, price, discount)
        VALUES (?,?,?,?,?)');

    $ensureStock = $pdo->prepare("
        INSERT INTO stock_branch(product_id, branch_id, quantity)
        VALUES (?,?,0)
        ON DUPLICATE KEY UPDATE quantity=quantity
    ");

    $decStock = $pdo->prepare("
        UPDATE stock_branch
        SET quantity = quantity - ?
        WHERE product_id=? AND branch_id=? AND quantity >= ?
    ");

    $insStockMovement = $pdo->prepare('INSERT INTO stock_movements
        (product_id, branch_id, movement_type, quantity, note, user_id, created_at)
        VALUES (?,?,?,?,?,?,NOW())');

    foreach ($items as $it) {
        $pid   = (int)($it['id'] ?? 0);
        $price = (int)($it['price'] ?? 0);
        $qty   = (int)($it['qty'] ?? 0);
        $disc  = floatval($it['disc'] ?? 0);

        if ($pid <= 0 || $qty <= 0) {
            throw new Exception('Item tidak valid');
        }

        $qProd->execute([$u['branch_id'], $pid]);
        $prod = $qProd->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new Exception("Produk tidak ditemukan (ID $pid)");
        }
        if ((int)$prod['stock'] < $qty) {
            throw new Exception("Stok tidak cukup untuk produk {$prod['name']}");
        }

        $raw = $price * $qty;
        $disc_val = round($raw * $disc / 100);
        $sub = $raw - $disc_val;
        $total += $sub;

        $insItem->execute([$order_id, $pid, $qty, $price, $disc]);

        $ensureStock->execute([$pid, $u['branch_id']]);
        $ok = $decStock->execute([$qty, $pid, $u['branch_id'], $qty]);
        if (!$ok || $decStock->rowCount() === 0) {
            throw new Exception("Gagal mengurangi stok produk {$prod['name']}");
        }

        $note = ($method === 'credit') ? "Penjualan Kredit - Order #$order_id" : "Penjualan POS - Order #$order_id";
        $insStockMovement->execute([$pid, $u['branch_id'], 'out', $qty, $note, $u['id']]);
    }

    $tax_val = round($total * $tax_pct / 100);
    $svc_val = round($total * $svc_pct / 100);
    $grand   = $total + $tax_val + $svc_val;

    $pdo->prepare('UPDATE orders
        SET total=?, tax_value=?, service_value=?
        WHERE id=?')
        ->execute([$grand, $tax_val, $svc_val, $order_id]);

    // Hitung kembalian dan simpan detail cash jika metode tunai
    $change = 0;
    if ($method === 'cash') {
        if ($cash_given < $grand) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['message' => 'Uang yang diberikan kurang dari total']);
            exit;
        }
        $change = $cash_given - $grand;
        
        // Simpan detail transaksi tunai ke tabel khusus (opsional)
        // Buat tabel cash_transactions jika belum ada
        try {
            $pdo->prepare('INSERT INTO cash_transactions 
                (order_id, cash_given, change_amount, created_at)
                VALUES (?,?,?,NOW())')
                ->execute([$order_id, $cash_given, $change]);
        } catch (Exception $e) {
            // Tabel mungkin belum ada, lanjutkan tanpa error
            error_log("Cash transaction table not found or error: " . $e->getMessage());
        }
    }

    $credit_id = null;
    if ($method === 'credit') {
        $due_date = date('Y-m-d', strtotime('+30 days'));
        $credit_customer_name = !empty($customer_name) ? $customer_name : $employee_name;
        $stmt = $pdo->prepare('INSERT INTO credits
            (order_id, employee_id, customer_name, employee_name, total_amount, paid_amount,
             status, branch_id, user_id, due_date, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([
            $order_id,
            $employee_id,
            $credit_customer_name,
            $employee_name,
            $grand,
            0,
            'unpaid',
            $u['branch_id'],
            $u['id'],
            $due_date
        ]);
        $credit_id = $pdo->lastInsertId();
    }

    $pdo->prepare('INSERT INTO audits
        (user_id, branch_id, action, details, created_at)
        VALUES (?,?,?,?,NOW())')
        ->execute([$u['id'], $u['branch_id'], 'order.create', json_encode([
            'order_id'=>$order_id,
            'total'=>$grand,
            'payment_method'=>$method,
            'items_count'=>count($items),
            'cash_given'=>$cash_given,
            'change'=>$change
        ])]);

    $pdo->commit();

    // Response yang lebih lengkap
    $response = [
        'ok' => true,
        'order_id' => $order_id,
        'total' => $grand,
        'payment_method' => $method
    ];

    if ($method === 'cash') {
        $response['cash_given'] = $cash_given;
        $response['change'] = $change;
        $response['message'] = "Pembayaran tunai berhasil. Kembalian: Rp " . number_format($change, 0, ',', '.');
    } elseif ($method === 'credit') {
        $response['credit_id'] = $credit_id;
        $response['message'] = "Order kredit berhasil dibuat untuk $employee_name";
    } else {
        $response['message'] = "Pembayaran $method berhasil";
    }

    echo json_encode($response);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("POS Payment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage()
    ]);
}