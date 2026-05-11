<?php
session_start();

if (!isset($_SESSION['passenger_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name  = $_SESSION['passenger_name'];
$id    = $_SESSION['passenger_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin      = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $scheduled   = $_POST['scheduled'] ?? '';

    if ($origin === '' || $destination === '' || $scheduled === '') {
        $error = '所有欄位為必填';
    } elseif ($origin === $destination) {
        $error = '起點與終點不可相同';
    } elseif (strtotime($scheduled) <= time()) {
        $error = '預計乘車時間須為未來時間';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO Rides (PassengerID, 起點, 終點, 預計乘車時間) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, $origin, $destination, $scheduled]);
        header('Location: dashboard.php');
        exit;
    }
}

$defaultTime = date('Y-m-d\TH:i', strtotime('+1 hour'));
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>行程預約</title>
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
    .main h1 { font-size: 1.4rem; margin: 0 0 24px; }

    .card {
      background: #fff; border-radius: 8px; padding: 28px;
      max-width: 480px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    label { display: block; margin-bottom: 4px; font-size: .9rem; color: #374151; }
    input[type="text"],
    input[type="datetime-local"] {
      width: 100%; padding: 9px 12px; margin-bottom: 18px;
      border: 1px solid #d1d5db; border-radius: 6px;
      font-size: .95rem; outline: none; transition: border-color .15s;
    }
    input:focus { border-color: #2563eb; }
    button[type="submit"] {
      width: 100%; padding: 12px; background: #2563eb; color: #fff;
      border: none; border-radius: 6px; font-size: 1rem; cursor: pointer;
    }
    button[type="submit"]:hover { background: #1d4ed8; }
    .msg-error { color: #dc2626; margin-bottom: 16px; font-size: .9rem; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }
      .card { max-width: 100%; padding: 20px; }

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
    <a href="book.php" class="active">行程預約</a>
    <a href="record.php">里程紀錄</a>
    <a href="history.php">歷史查詢</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="book.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 18H5V8h14v13zM7 10h5v5H7z"/></svg>預約
  </a>
  <a href="record.php">
    <svg viewBox="0 0 24 24"><path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/></svg>紀錄
  </a>
  <a href="history.php">
    <svg viewBox="0 0 24 24"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6a7 7 0 1 1 2.05 4.95L6.64 18.36A9 9 0 1 0 13 3zm-1 5v5l4.25 2.53.77-1.28-3.52-2.09V8H12z"/></svg>歷史
  </a>
  <a href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號
  </a>
</nav>

<div class="main">
  <h1>行程預約</h1>
  <div class="card">
    <?php if ($error): ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post">
      <label for="origin">起點</label>
      <input id="origin" name="origin" type="text" required maxlength="255"
             placeholder="請輸入上車地點"
             value="<?= htmlspecialchars($_POST['origin'] ?? '') ?>">

      <label for="destination">終點</label>
      <input id="destination" name="destination" type="text" required maxlength="255"
             placeholder="請輸入下車地點"
             value="<?= htmlspecialchars($_POST['destination'] ?? '') ?>">

      <label for="scheduled">預計乘車時間</label>
      <input id="scheduled" name="scheduled" type="datetime-local" required
             min="<?= date('Y-m-d\TH:i') ?>"
             value="<?= htmlspecialchars($_POST['scheduled'] ?? $defaultTime) ?>">

      <button type="submit">送出預約</button>
    </form>
  </div>
</div>

</body>
</html>
