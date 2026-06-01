<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../src/lib/db.php';
require_once __DIR__.'/../src/lib/auth.php';

$u = auth_user();

$message = '';
$message_type = '';

if (isset($_POST['submit'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $handle = fopen($file, 'r');
        $rowNum = 0;
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            $rowNum++;
            if ($rowNum < 3 || count($data) < 2) continue;

            $nama = trim($data[1]);
            if ($nama) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE name=?');
                $stmt->execute([$nama]);
                if ($stmt->fetchColumn() == 0) {
                    $pdo->prepare('INSERT INTO employees(name) VALUES (?)')->execute([$nama]);
                }
            }
        }
        fclose($handle);
        $pdo->commit();
        $message = "Import selesai!";
        $message_type = "success";
    } catch(Exception $e) {
        $pdo->rollBack();
        $message = "Gagal import: " . $e->getMessage();
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8" />
<title>Import Karyawan CSV</title>
<link rel="stylesheet" href="/pos-web-starter/assets/css/all.min.css">
<style>
body {
    margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #064420; color: #fff;
}
.container {
    margin-left: 240px; /* Padding untuk sidebar */
    padding: 30px;
    max-width: 550px;
}
.card {
    background: #0b6e4f;
    border-radius: 12px;
    padding: 25px 30px;
    box-shadow: 0 6px 15px rgba(0,0,0,0.4);
}
h2 {
    color: #ffd700;
    margin-top: 0; margin-bottom: 22px;
}
input[type="file"] {
    width: 100%;
    padding: 12px 10px;
    background: #064420;
    border: 1.5px solid #27ae60;
    border-radius: 8px;
    outline: none;
    cursor: pointer;
    color: #fff;
    margin-bottom: 20px;
    transition: border-color 0.3s;
}
input[type="file"]:focus {
    border-color: #ffd700;
}
button {
    background: #ffd700;
    color: #064420;
    font-weight: bold;
    font-size: 15px;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.3s ease;
    width: 100%;
}
button:hover {
    background: #e6c200;
}
.alert {
    margin-bottom: 20px;
    padding: 14px 16px;
    border-radius: 10px;
    font-weight: 600;
}
.alert.success {
    background: #27ae60;
    color: #fff;
}
.alert.error {
    background: #e74c3c;
    color: #fff;
}
@media (max-width: 800px) {
    .container {
        margin-left: 70px;
        padding: 20px;
    }
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../src/nav/sidebar.php'; ?>

<div class="container">
    <div class="card">
        <h2><i class="fa fa-file-import"></i> Import Karyawan dari CSV</h2>
        <?php if ($message): ?>
            <div class="alert <?= $message_type === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" accept=".csv,text/csv">
            <input type="file" name="csv_file" accept=".csv,text/csv" required />
            <button type="submit" name="submit"><i class="fa fa-upload"></i> Mulai Import</button>
        </form>
    </div>
</div>
</body>
</html>
