<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$account = $_SESSION['admin_account'];

$acctMsg = ['type' => '', 'text' => ''];
$passMsg = ['type' => '', 'text' => ''];

// ── 修改帳號 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'account') {
    $newAccount  = trim($_POST['new_account'] ?? '');
    $currentPass = $_POST['current_pass_acct'] ?? '';

    if ($newAccount === '' || $currentPass === '') {
        $acctMsg = ['type' => 'error', 'text' => '所有欄位為必填'];
    } elseif (mb_strlen($newAccount) > 50) {
        $acctMsg = ['type' => 'error', 'text' => '帳號不可超過 50 字'];
    } else {
        $row = $pdo->prepare('SELECT AdminID, 密碼 FROM Admins WHERE 帳號 = ?');
        $row->execute([$account]);
        $user = $row->fetch();

        if (!password_verify($currentPass, $user['密碼'])) {
            $acctMsg = ['type' => 'error', 'text' => '目前密碼不正確'];
        } elseif ($newAccount === $account) {
            $acctMsg = ['type' => 'error', 'text' => '新帳號與目前帳號相同'];
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM Admins WHERE 帳號 = ? AND AdminID != ?');
            $dup->execute([$newAccount, $user['AdminID']]);
            if ($dup->fetchColumn() > 0) {
                $acctMsg = ['type' => 'error', 'text' => '此帳號已被使用'];
            } else {
                $pdo->prepare('UPDATE Admins SET 帳號 = ? WHERE AdminID = ?')
                    ->execute([$newAccount, $user['AdminID']]);
                $_SESSION['admin_account'] = $newAccount;
                $account = $newAccount;
                $acctMsg = ['type' => 'success', 'text' => '帳號已更新'];
            }
        }
    }
}

// ── 修改密碼 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $newPass     = $_POST['new_pass'] ?? '';
    $confirmPass = $_POST['confirm_pass'] ?? '';
    $currentPass = $_POST['current_pass_pw'] ?? '';

    if ($newPass === '' || $confirmPass === '' || $currentPass === '') {
        $passMsg = ['type' => 'error', 'text' => '所有欄位為必填'];
    } elseif (strlen($newPass) < 6) {
        $passMsg = ['type' => 'error', 'text' => '新密碼至少需 6 個字元'];
    } elseif ($newPass !== $confirmPass) {
        $passMsg = ['type' => 'error', 'text' => '兩次輸入的新密碼不一致'];
    } else {
        $row = $pdo->prepare('SELECT AdminID, 密碼 FROM Admins WHERE 帳號 = ?');
        $row->execute([$account]);
        $user = $row->fetch();

        if (!password_verify($currentPass, $user['密碼'])) {
            $passMsg = ['type' => 'error', 'text' => '目前密碼不正確'];
        } elseif (password_verify($newPass, $user['密碼'])) {
            $passMsg = ['type' => 'error', 'text' => '新密碼不可與目前密碼相同'];
        } else {
            $pdo->prepare('UPDATE Admins SET 密碼 = ? WHERE AdminID = ?')
                ->execute([password_hash($newPass, PASSWORD_BCRYPT), $user['AdminID']]);
            $passMsg = ['type' => 'success', 'text' => '密碼已更新'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>帳號設定</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: sans-serif; background: #f3f4f6; }

    .sidebar {
      position: fixed; top: 0; left: 0; width: 200px; height: 100vh;
      background: #312e81; color: #fff;
      display: flex; flex-direction: column; padding: 32px 0; z-index: 100;
    }
    .sidebar .brand { font-size: 1rem; font-weight: bold; padding: 0 24px 28px; border-bottom: 1px solid #3730a3; letter-spacing: .05em; }
    .sidebar nav { margin-top: 16px; flex: 1; }
    .sidebar nav a {
      display: block; padding: 12px 24px; color: #c7d2fe;
      text-decoration: none; font-size: .95rem;
      border-left: 3px solid transparent; transition: background .15s, color .15s;
    }
    .sidebar nav a:hover,
    .sidebar nav a.active { background: #3730a3; color: #fff; border-left-color: #818cf8; }
    .sidebar .logout { padding: 16px 24px; border-top: 1px solid #3730a3; }
    .sidebar .logout a { color: #a5b4fc; font-size: .85rem; text-decoration: none; }
    .sidebar .logout a:hover { color: #fff; }

    .main { margin-left: 200px; padding: 40px 36px; min-height: 100vh; }
    .main h1 { font-size: 1.4rem; margin: 0 0 4px; }
    .main .subtitle { color: #6b7280; margin: 0 0 28px; font-size: .95rem; }

    .card {
      background: #fff; border-radius: 8px; padding: 24px 28px;
      max-width: 480px; margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .card h2 { font-size: 1rem; margin: 0 0 18px; color: #374151; padding-bottom: 12px; border-bottom: 1px solid #f3f4f6; }

    label { display: block; margin-bottom: 4px; font-size: .88rem; color: #374151; }
    input[type="text"], input[type="password"] {
      width: 100%; padding: 9px 12px; margin-bottom: 14px;
      border: 1px solid #d1d5db; border-radius: 6px;
      font-size: .95rem; outline: none; transition: border-color .15s;
    }
    input:focus { border-color: #4f46e5; }
    .divider { border: none; border-top: 1px dashed #e5e7eb; margin: 14px 0; }
    button[type="submit"] {
      width: 100%; padding: 10px; background: #4f46e5; color: #fff;
      border: none; border-radius: 6px; font-size: .95rem; cursor: pointer;
    }
    button[type="submit"]:hover { background: #4338ca; }
    .msg-error   { color: #dc2626; font-size: .88rem; margin-bottom: 12px; padding: 8px 12px; background: #fef2f2; border-radius: 6px; }
    .msg-success { color: #16a34a; font-size: .88rem; margin-bottom: 12px; padding: 8px 12px; background: #f0fdf4; border-radius: 6px; }

    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }
      .card { max-width: 100%; }

      .bottom-nav {
        display: flex; position: fixed; bottom: 0; left: 0; right: 0;
        background: #312e81; z-index: 100; border-top: 1px solid #3730a3;
      }
      .bottom-nav a {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 10px 4px; color: #a5b4fc;
        text-decoration: none; font-size: .7rem; gap: 3px;
      }
      .bottom-nav a svg { width: 22px; height: 22px; fill: currentColor; }
      .bottom-nav a.active, .bottom-nav a:hover { color: #818cf8; }
    }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統（管理）</div>
  <nav>
    <a href="dashboard.php">行程總覽</a>
    <a href="assign.php">指派駕駛</a>
    <a href="report.php">帳務報表</a>
    <a href="profile.php" class="active">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>行程總覽
  </a>
  <a href="assign.php">
    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>指派駕駛
  </a>
  <a href="report.php">
    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>帳務報表
  </a>
  <a href="profile.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號設定
  </a>
</nav>

<div class="main">
  <h1>帳號設定</h1>
  <p class="subtitle">目前登入：<?= htmlspecialchars($account) ?></p>

  <div class="card">
    <h2>修改帳號</h2>
    <?php if ($acctMsg['text']): ?>
      <p class="msg-<?= $acctMsg['type'] ?>"><?= htmlspecialchars($acctMsg['text']) ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="account">
      <label for="new_account">新帳號</label>
      <input id="new_account" name="new_account" type="text" required maxlength="50"
             placeholder="輸入新帳號"
             value="<?= htmlspecialchars($_POST['new_account'] ?? '') ?>">
      <hr class="divider">
      <label for="current_pass_acct">目前密碼（確認身分）</label>
      <input id="current_pass_acct" name="current_pass_acct" type="password" required placeholder="輸入目前密碼">
      <button type="submit">更新帳號</button>
    </form>
  </div>

  <div class="card">
    <h2>修改密碼</h2>
    <?php if ($passMsg['text']): ?>
      <p class="msg-<?= $passMsg['type'] ?>"><?= htmlspecialchars($passMsg['text']) ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="password">
      <label for="new_pass">新密碼（至少 6 字元）</label>
      <input id="new_pass" name="new_pass" type="password" required placeholder="輸入新密碼">
      <label for="confirm_pass">確認新密碼</label>
      <input id="confirm_pass" name="confirm_pass" type="password" required placeholder="再次輸入新密碼">
      <hr class="divider">
      <label for="current_pass_pw">目前密碼（確認身分）</label>
      <input id="current_pass_pw" name="current_pass_pw" type="password" required placeholder="輸入目前密碼">
      <button type="submit">更新密碼</button>
    </form>
  </div>
</div>

</body>
</html>
