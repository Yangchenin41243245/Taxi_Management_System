<?php
session_start();

if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account  = trim($_POST['account'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($account === '' || $password === '') {
        $error = '請輸入帳號與密碼';
    } else {
        $stmt = $pdo->prepare('SELECT AdminID, 密碼 FROM Admins WHERE 帳號 = ?');
        $stmt->execute([$account]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['密碼'])) {
            session_regenerate_id(true);
            $_SESSION['admin']         = true;
            $_SESSION['admin_account'] = $account;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = '帳號或密碼錯誤';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>管理端登入</title>
  <style>
    body { font-family: sans-serif; max-width: 400px; margin: 60px auto; padding: 0 16px; }
    h1   { font-size: 1.4rem; margin-bottom: 24px; }
    label { display: block; margin-bottom: 4px; font-size: .9rem; }
    input { width: 100%; box-sizing: border-box; padding: 8px; margin-bottom: 16px; border: 1px solid #ccc; border-radius: 4px; }
    button { width: 100%; padding: 10px; background: #4f46e5; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
    button:hover { background: #4338ca; }
    .msg-error  { color: #dc2626; margin-bottom: 12px; }
    .register-link { text-align: center; margin-top: 16px; font-size: .9rem; }
  </style>
</head>
<body>
  <h1>管理端登入</h1>

  <?php if ($error): ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <form method="post">
    <label for="account">帳號</label>
    <input id="account" name="account" type="text" required maxlength="50"
           value="<?= htmlspecialchars($_POST['account'] ?? '') ?>">

    <label for="password">密碼</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">登入</button>
  </form>

  <p class="register-link">還沒有帳號？<a href="register.php">註冊</a></p>
</body>
</html>
