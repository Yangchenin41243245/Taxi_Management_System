<?php
session_start();

if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$id   = $_SESSION['driver_id'];
$name = $_SESSION['driver_name'];

$nameMsg = ['type' => '', 'text' => ''];
$passMsg = ['type' => '', 'text' => ''];

// ── 修改姓名 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'name') {
    $newName     = trim($_POST['new_name'] ?? '');
    $currentPass = $_POST['current_pass_name'] ?? '';

    if ($newName === '' || $currentPass === '') {
        $nameMsg = ['type' => 'error', 'text' => '所有欄位為必填'];
    } elseif (mb_strlen($newName) > 50) {
        $nameMsg = ['type' => 'error', 'text' => '姓名不可超過 50 字'];
    } else {
        $row = $pdo->prepare('SELECT 密碼 FROM Drivers WHERE DriverID = ?');
        $row->execute([$id]);
        $user = $row->fetch();

        if (!password_verify($currentPass, $user['密碼'])) {
            $nameMsg = ['type' => 'error', 'text' => '目前密碼不正確'];
        } elseif ($newName === $name) {
            $nameMsg = ['type' => 'error', 'text' => '新姓名與目前姓名相同'];
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM Drivers WHERE 駕駛姓名 = ? AND DriverID != ?');
            $dup->execute([$newName, $id]);
            if ($dup->fetchColumn() > 0) {
                $nameMsg = ['type' => 'error', 'text' => '此姓名已被其他人使用'];
            } else {
                $pdo->prepare('UPDATE Drivers SET 駕駛姓名 = ? WHERE DriverID = ?')
                    ->execute([$newName, $id]);
                $_SESSION['driver_name'] = $newName;
                $name = $newName;
                $nameMsg = ['type' => 'success', 'text' => '姓名已更新'];
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
        $row = $pdo->prepare('SELECT 密碼 FROM Drivers WHERE DriverID = ?');
        $row->execute([$id]);
        $user = $row->fetch();

        if (!password_verify($currentPass, $user['密碼'])) {
            $passMsg = ['type' => 'error', 'text' => '目前密碼不正確'];
        } elseif (password_verify($newPass, $user['密碼'])) {
            $passMsg = ['type' => 'error', 'text' => '新密碼不可與目前密碼相同'];
        } else {
            $pdo->prepare('UPDATE Drivers SET 密碼 = ? WHERE DriverID = ?')
                ->execute([password_hash($newPass, PASSWORD_BCRYPT), $id]);
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
      background: #134e4a; color: #fff;
      display: flex; flex-direction: column; padding: 32px 0; z-index: 100;
    }
    .sidebar .brand { font-size: 1rem; font-weight: bold; padding: 0 24px 28px; border-bottom: 1px solid #1f6b65; letter-spacing: .05em; }
    .sidebar nav { margin-top: 16px; flex: 1; }
    .sidebar nav a {
      display: block; padding: 12px 24px; color: #99f6e4;
      text-decoration: none; font-size: .95rem;
      border-left: 3px solid transparent; transition: background .15s, color .15s;
    }
    .sidebar nav a:hover,
    .sidebar nav a.active { background: #1f6b65; color: #fff; border-left-color: #2dd4bf; }
    .sidebar .logout { padding: 16px 24px; border-top: 1px solid #1f6b65; }
    .sidebar .logout a { color: #5eead4; font-size: .85rem; text-decoration: none; }
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
    input:focus { border-color: #0f766e; }
    .divider { border: none; border-top: 1px dashed #e5e7eb; margin: 14px 0; }
    button[type="submit"] {
      width: 100%; padding: 10px; background: #0f766e; color: #fff;
      border: none; border-radius: 6px; font-size: .95rem; cursor: pointer;
    }
    button[type="submit"]:hover { background: #0d6460; }
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
        background: #134e4a; z-index: 100; border-top: 1px solid #1f6b65;
      }
      .bottom-nav a {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 10px 4px; color: #5eead4;
        text-decoration: none; font-size: .7rem; gap: 3px;
      }
      .bottom-nav a svg { width: 22px; height: 22px; fill: currentColor; }
      .bottom-nav a.active, .bottom-nav a:hover { color: #2dd4bf; }
    }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統（駕駛）</div>
  <nav>
    <a href="dashboard.php">主頁</a>
    <a href="report.php">回報里程</a>
    <a href="revenue.php">營收彙整</a>
    <a href="profile.php" class="active">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="report.php">
    <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1a3 3 0 0 0-6 0c0 .12.11.56.18 1H10a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zm-7-1a1 1 0 0 1 2 0c0 .5-.25 1-.68 1.41L14 6.83l-.32-.42C13.25 6 13 5.5 13 5zm7 15H10V8h2v1a1 1 0 0 0 2 0V8h2v1a1 1 0 0 0 2 0V8h2v12z"/></svg>回報里程
  </a>
  <a href="revenue.php">
    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1H6.1c.12 2.19 1.76 3.42 3.7 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>營收彙整
  </a>
  <a href="profile.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號設定
  </a>
</nav>

<div class="main">
  <h1>帳號設定</h1>
  <p class="subtitle">目前登入：<?= htmlspecialchars($name) ?> 駕駛</p>

  <div class="card">
    <h2>修改姓名</h2>
    <?php if ($nameMsg['text']): ?>
      <p class="msg-<?= $nameMsg['type'] ?>"><?= htmlspecialchars($nameMsg['text']) ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="name">
      <label for="new_name">新姓名</label>
      <input id="new_name" name="new_name" type="text" required maxlength="50"
             placeholder="輸入新姓名"
             value="<?= htmlspecialchars($_POST['new_name'] ?? '') ?>">
      <hr class="divider">
      <label for="current_pass_name">目前密碼（確認身分）</label>
      <input id="current_pass_name" name="current_pass_name" type="password" required placeholder="輸入目前密碼">
      <button type="submit">更新姓名</button>
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
