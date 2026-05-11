<?php
session_start();

if (!isset($_SESSION['passenger_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$id   = $_SESSION['passenger_id'];
$name = $_SESSION['passenger_name'];

$nameMsg  = ['type' => '', 'text' => ''];
$passMsg  = ['type' => '', 'text' => ''];

// ── 修改姓名 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'name') {
    $newName     = trim($_POST['new_name'] ?? '');
    $currentPass = $_POST['current_pass_name'] ?? '';

    if ($newName === '' || $currentPass === '') {
        $nameMsg = ['type' => 'error', 'text' => '所有欄位為必填'];
    } elseif (mb_strlen($newName) > 50) {
        $nameMsg = ['type' => 'error', 'text' => '姓名不可超過 50 字'];
    } else {
        $row = $pdo->prepare('SELECT 密碼 FROM Passengers WHERE PassengerID = ?');
        $row->execute([$id]);
        $user = $row->fetch();

        if (!password_verify($currentPass, $user['密碼'])) {
            $nameMsg = ['type' => 'error', 'text' => '目前密碼不正確'];
        } elseif ($newName === $name) {
            $nameMsg = ['type' => 'error', 'text' => '新姓名與目前姓名相同'];
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM Passengers WHERE 乘客姓名 = ? AND PassengerID != ?');
            $dup->execute([$newName, $id]);
            if ($dup->fetchColumn() > 0) {
                $nameMsg = ['type' => 'error', 'text' => '此姓名已被其他人使用'];
            } else {
                $pdo->prepare('UPDATE Passengers SET 乘客姓名 = ? WHERE PassengerID = ?')
                    ->execute([$newName, $id]);
                $_SESSION['passenger_name'] = $newName;
                $name = $newName;
                $nameMsg = ['type' => 'success', 'text' => '姓名已更新'];
            }
        }
    }
}

// ── 修改密碼 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'password') {
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
        $row = $pdo->prepare('SELECT 密碼 FROM Passengers WHERE PassengerID = ?');
        $row->execute([$id]);
        $user = $row->fetch();

        if (!password_verify($currentPass, $user['密碼'])) {
            $passMsg = ['type' => 'error', 'text' => '目前密碼不正確'];
        } elseif (password_verify($newPass, $user['密碼'])) {
            $passMsg = ['type' => 'error', 'text' => '新密碼不可與目前密碼相同'];
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE Passengers SET 密碼 = ? WHERE PassengerID = ?')
                ->execute([$hash, $id]);
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

    /* 側欄（桌面） */
    .sidebar {
      position: fixed; top: 0; left: 0; width: 200px; height: 100vh;
      background: #1e3a5f; color: #fff;
      display: flex; flex-direction: column; padding: 32px 0; z-index: 100;
    }
    .sidebar .brand { font-size: 1rem; font-weight: bold; padding: 0 24px 28px; border-bottom: 1px solid #2d527a; letter-spacing: .05em; }
    .sidebar nav { margin-top: 16px; flex: 1; }
    .sidebar nav a {
      display: block; padding: 12px 24px; color: #cbd5e1;
      text-decoration: none; font-size: .95rem;
      border-left: 3px solid transparent; transition: background .15s, color .15s;
    }
    .sidebar nav a:hover,
    .sidebar nav a.active { background: #2d527a; color: #fff; border-left-color: #60a5fa; }
    .sidebar .logout { padding: 16px 24px; border-top: 1px solid #2d527a; }
    .sidebar .logout a { color: #94a3b8; font-size: .85rem; text-decoration: none; }
    .sidebar .logout a:hover { color: #fff; }

    /* 主內容 */
    .main { margin-left: 200px; padding: 40px 36px; min-height: 100vh; }
    .main h1 { font-size: 1.4rem; margin: 0 0 4px; }
    .main .subtitle { color: #6b7280; margin: 0 0 28px; font-size: .95rem; }

    /* 卡片 */
    .card {
      background: #fff; border-radius: 8px; padding: 24px 28px;
      max-width: 480px; margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .card h2 { font-size: 1rem; margin: 0 0 18px; color: #374151; padding-bottom: 12px; border-bottom: 1px solid #f3f4f6; }

    label { display: block; margin-bottom: 4px; font-size: .88rem; color: #374151; }
    input[type="text"],
    input[type="password"] {
      width: 100%; padding: 9px 12px; margin-bottom: 14px;
      border: 1px solid #d1d5db; border-radius: 6px;
      font-size: .95rem; outline: none; transition: border-color .15s;
    }
    input:focus { border-color: #2563eb; }

    .divider { border: none; border-top: 1px dashed #e5e7eb; margin: 14px 0; }

    button[type="submit"] {
      width: 100%; padding: 10px; background: #2563eb; color: #fff;
      border: none; border-radius: 6px; font-size: .95rem; cursor: pointer;
    }
    button[type="submit"]:hover { background: #1d4ed8; }

    .msg-error   { color: #dc2626; font-size: .88rem; margin-bottom: 12px; padding: 8px 12px; background: #fef2f2; border-radius: 6px; }
    .msg-success { color: #16a34a; font-size: .88rem; margin-bottom: 12px; padding: 8px 12px; background: #f0fdf4; border-radius: 6px; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }
      .card { max-width: 100%; }

      .bottom-nav {
        display: flex; position: fixed; bottom: 0; left: 0; right: 0;
        background: #1e3a5f; z-index: 100; border-top: 1px solid #2d527a;
      }
      .bottom-nav a {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 8px 2px; color: #94a3b8;
        text-decoration: none; font-size: .65rem; gap: 3px;
      }
      .bottom-nav a svg { width: 22px; height: 22px; fill: currentColor; }
      .bottom-nav a.active, .bottom-nav a:hover { color: #60a5fa; }
    }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統</div>
  <nav>
    <a href="dashboard.php">主頁</a>
    <a href="book.php">行程預約</a>
    <a href="record.php">里程紀錄</a>
    <a href="history.php">歷史查詢</a>
    <a href="profile.php" class="active">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="book.php">
    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 18H5V8h14v13zM7 10h5v5H7z"/></svg>預約
  </a>
  <a href="record.php">
    <svg viewBox="0 0 24 24"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>紀錄
  </a>
  <a href="history.php">
    <svg viewBox="0 0 24 24"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6a7 7 0 1 1 2.05 4.95L6.64 18.36A9 9 0 1 0 13 3zm-1 5v5l4.25 2.53.77-1.28-3.52-2.09V8H12z"/></svg>歷史
  </a>
  <a href="profile.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號
  </a>
</nav>

<div class="main">
  <h1>帳號設定</h1>
  <p class="subtitle">目前登入：<?= htmlspecialchars($name) ?></p>

  <!-- 修改姓名 -->
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

  <!-- 修改密碼 -->
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
