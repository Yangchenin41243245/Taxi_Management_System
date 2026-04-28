<?php
require_once __DIR__ . '/../db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account  = trim($_POST['account'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($account === '' || $password === '') {
        $error = '帳號與密碼為必填';
    } elseif (mb_strlen($account) > 50) {
        $error = '帳號不可超過 50 字';
    } elseif (strlen($password) < 6) {
        $error = '密碼至少需 6 個字元';
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Admins WHERE 帳號 = ?');
        $stmt->execute([$account]);
        if ($stmt->fetchColumn() > 0) {
            $error = '此帳號已被使用';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO Admins (帳號, 密碼) VALUES (?, ?)');
            $stmt->execute([$account, $hash]);
            $success = '帳號建立成功！';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>管理員註冊</title>
  <style>
    body { font-family: sans-serif; max-width: 400px; margin: 60px auto; padding: 0 16px; }
    h1   { font-size: 1.4rem; margin-bottom: 24px; }
    label { display: block; margin-bottom: 4px; font-size: .9rem; }
    input { width: 100%; box-sizing: border-box; padding: 8px; margin-bottom: 16px; border: 1px solid #ccc; border-radius: 4px; }
    button { width: 100%; padding: 10px; background: #4f46e5; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
    button:hover { background: #4338ca; }
    .msg-error   { color: #dc2626; margin-bottom: 12px; }
    .msg-success { color: #16a34a; margin-bottom: 12px; }
    .login-link  { text-align: center; margin-top: 16px; font-size: .9rem; }
  </style>
</head>
<body>
  <h1>建立管理員帳號</h1>

  <?php if ($error):   ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="msg-success"><?= htmlspecialchars($success) ?> <a href="login.php">前往登入</a></p><?php endif; ?>

  <?php if (!$success): ?>
  <form method="post">
    <label for="account">帳號</label>
    <input id="account" name="account" type="text" required maxlength="50"
           value="<?= htmlspecialchars($_POST['account'] ?? '') ?>">

    <label for="password">密碼（至少 6 字元）</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">註冊</button>
  </form>
  <?php endif; ?>

  <p class="login-link">已有帳號？<a href="login.php">登入</a></p>
</body>
</html>
