<?php
session_start();

if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name  = $_SESSION['driver_name'];
$id    = $_SESSION['driver_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rideId  = (int)($_POST['ride_id'] ?? 0);
    $mileage = $_POST['mileage'] ?? '';
    $price   = $_POST['price'] ?? '';

    if ($rideId <= 0 || $mileage === '' || $price === '') {
        $error = '所有欄位為必填';
    } elseif (!is_numeric($mileage) || $mileage <= 0) {
        $error = '里程須為正數';
    } elseif ((float)$mileage > 9999.99) {
        $error = '里程不可超過 9999.99 公里';
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = '金額須為正數';
    } elseif ((float)$price > 999999.99) {
        $error = '金額不可超過 999,999 元';
    } else {
        $check = $pdo->prepare(
            'SELECT r.RideID FROM Rides r
             LEFT JOIN Settlements s ON s.RideID = r.RideID
             WHERE r.RideID = ? AND r.DriverID = ? AND s.RideID IS NULL'
        );
        $check->execute([$rideId, $id]);
        if (!$check->fetch()) {
            $error = '行程不存在或已回報';
        } else {
            $pdo->prepare('INSERT INTO Settlements (RideID, 實際里程, 最終價格, 確認狀態) VALUES (?, ?, ?, ?)')
                ->execute([$rideId, $mileage, $price, '待確認']);
            header('Location: report.php?done=1');
            exit;
        }
    }
}

$done = isset($_GET['done']);

$stmt = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間, p.乘客姓名
     FROM Rides r
     LEFT JOIN Settlements s ON s.RideID = r.RideID
     LEFT JOIN Passengers p ON p.PassengerID = r.PassengerID
     WHERE r.DriverID = ? AND s.RideID IS NULL
     ORDER BY r.預計乘車時間 ASC'
);
$stmt->execute([$id]);
$pending = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>回報里程</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: sans-serif; background: #f3f4f6; }

    /* 側欄（桌面） */
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
    .main h1 { font-size: 1.4rem; margin: 0 0 24px; }

    .msg-success { color: #16a34a; margin-bottom: 16px; font-size: .9rem; }
    .msg-error   { color: #dc2626; margin-bottom: 16px; font-size: .9rem; }
    .empty { color: #9ca3af; font-size: .95rem; }

    .ride-card {
      background: #fff; border-radius: 8px; padding: 20px 24px;
      margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .ride-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .ride-info .route { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
    .ride-info .meta  { font-size: .85rem; color: #6b7280; }

    .report-form { margin-top: 16px; border-top: 1px solid #f3f4f6; padding-top: 16px; display: none; }
    .report-form.open { display: block; }
    .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
    .form-group { flex: 1; min-width: 120px; }
    .form-group label { display: block; font-size: .85rem; color: #374151; margin-bottom: 4px; }
    .form-group input {
      width: 100%; padding: 9px 10px;
      border: 1px solid #d1d5db; border-radius: 6px; font-size: .9rem; outline: none;
    }
    .form-group input:focus { border-color: #0f766e; }
    .btn-open   { padding: 8px 16px; background: #0f766e; color: #fff; border: none; border-radius: 6px; font-size: .85rem; cursor: pointer; white-space: nowrap; }
    .btn-open:hover { background: #0d6460; }
    .btn-submit { padding: 9px 20px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: .9rem; cursor: pointer; }
    .btn-submit:hover { background: #15803d; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }

      /* 回報按鈕放卡片下方全寬 */
      .ride-header { flex-direction: column; gap: 10px; }
      .btn-open { width: 100%; text-align: center; padding: 10px; }

      /* 表單欄位垂直堆疊 */
      .form-row { flex-direction: column; }
      .form-group { min-width: unset; }
      .btn-submit { width: 100%; padding: 12px; font-size: 1rem; }

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
  <script>
    function toggleForm(rideId) {
      document.getElementById('form-' + rideId).classList.toggle('open');
    }
  </script>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統（駕駛）</div>
  <nav>
    <a href="dashboard.php">主頁</a>
    <a href="report.php" class="active">回報里程</a>
    <a href="revenue.php">營收彙整</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="report.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1a3 3 0 0 0-6 0c0 .12.11.56.18 1H10a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zm-7-1a1 1 0 0 1 2 0c0 .5-.25 1-.68 1.41L14 6.83l-.32-.42C13.25 6 13 5.5 13 5zm7 15H10V8h2v1a1 1 0 0 0 2 0V8h2v1a1 1 0 0 0 2 0V8h2v12z"/></svg>回報里程
  </a>
  <a href="revenue.php">
    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1H6.1c.12 2.19 1.76 3.42 3.7 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>營收彙整
  </a>
  <a href="logout.php">
    <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8v-2H4V5z"/></svg>登出
  </a>
</nav>

<div class="main">
  <h1>回報里程與價格</h1>

  <?php if ($done): ?><p class="msg-success">回報成功，等待乘客確認結案。</p><?php endif; ?>
  <?php if ($error): ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <?php if (empty($pending)): ?>
    <p class="empty">目前沒有待回報的行程。</p>
  <?php else: ?>
    <?php foreach ($pending as $r): ?>
    <div class="ride-card">
      <div class="ride-header">
        <div class="ride-info">
          <div class="route">
            <?= htmlspecialchars($r['起點']) ?>
            <span style="color:#9ca3af"> → </span>
            <?= htmlspecialchars($r['終點']) ?>
          </div>
          <div class="meta">
            乘客：<?= htmlspecialchars($r['乘客姓名'] ?? '—') ?>　預計乘車：<?= htmlspecialchars($r['預計乘車時間']) ?>
          </div>
        </div>
        <button class="btn-open" onclick="toggleForm(<?= (int)$r['RideID'] ?>)">填寫回報</button>
      </div>

      <div class="report-form" id="form-<?= (int)$r['RideID'] ?>">
        <form method="post">
          <input type="hidden" name="ride_id" value="<?= (int)$r['RideID'] ?>">
          <div class="form-row">
            <div class="form-group">
              <label>實際里程（公里）</label>
              <input type="number" name="mileage" step="0.01" min="0.01" max="9999.99" placeholder="例：12.5" required>
            </div>
            <div class="form-group">
              <label>最終價格（元）</label>
              <input type="number" name="price" step="1" min="1" max="999999" placeholder="例：350" required>
            </div>
            <button class="btn-submit" type="submit" onclick="return confirm('確認送出此行程的回報？')">送出</button>
          </div>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</body>
</html>
