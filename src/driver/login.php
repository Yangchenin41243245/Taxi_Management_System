<?php
session_start();

if (isset($_SESSION['driver_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $password === '') {
        $error = '請輸入姓名與密碼';
    } else {
        $stmt = $pdo->prepare('SELECT DriverID, 密碼 FROM Drivers WHERE 駕駛姓名 = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['密碼'])) {
            session_regenerate_id(true);
            $_SESSION['driver_id']   = $row['DriverID'];
            $_SESSION['driver_name'] = $name;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = '姓名或密碼錯誤';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>駕駛登入</title>
  <style>
    body { font-family: sans-serif; max-width: 400px; margin: 60px auto; padding: 0 16px; }
    h1   { font-size: 1.4rem; margin-bottom: 24px; }
    label { display: block; margin-bottom: 4px; font-size: .9rem; }
    input { width: 100%; box-sizing: border-box; padding: 8px; margin-bottom: 16px; border: 1px solid #ccc; border-radius: 4px; }
    button { width: 100%; padding: 10px; background: #0f766e; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
    button:hover { background: #0d6460; }
    .msg-error { color: #dc2626; margin-bottom: 12px; }
  </style>
</head>
<body>
  <h1>駕駛登入</h1>

  <?php if ($error): ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <form method="post">
    <label for="name">姓名</label>
    <input id="name" name="name" type="text" required maxlength="50"
           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

    <label for="password">密碼</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">登入</button>
  </form>
  <p style="text-align:center;margin-top:16px;font-size:.9rem">還沒有帳號？<a href="register.php">註冊</a></p>
</body>
</html>
