<?php
session_start();

if (!isset($_SESSION['passenger_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name = $_SESSION['passenger_name'];
$id   = $_SESSION['passenger_id'];
$msg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rideId = (int)($_POST['ride_id'] ?? 0);
    if ($rideId > 0) {
        $check = $pdo->prepare(
            'SELECT s.RideID FROM Rides r
             JOIN Settlements s ON s.RideID = r.RideID
             WHERE r.RideID = ? AND r.PassengerID = ? AND s.確認狀態 = ?'
        );
        $check->execute([$rideId, $id, '待確認']);
        if ($check->fetch()) {
            $pdo->prepare('UPDATE Settlements SET 確認狀態 = ?, 完成確認時間 = NOW() WHERE RideID = ?')
                ->execute(['已確認', $rideId]);
            $msg = 'confirmed';
        }
    }
    header('Location: confirm.php?msg=' . $msg);
    exit;
}

$msg = $_GET['msg'] ?? '';

$stmt = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間,
            d.駕駛姓名, s.實際里程, s.最終價格
     FROM Rides r
     JOIN Settlements s ON s.RideID = r.RideID
     LEFT JOIN Drivers d ON d.DriverID = r.DriverID
     WHERE r.PassengerID = ? AND s.確認狀態 = ?
     ORDER BY r.預計乘車時間 DESC'
);
$stmt->execute([$id, '待確認']);
$rides = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>結單確認</title>
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
    .main h1 { font-size: 1.4rem; margin: 0 0 8px; }
    .main .subtitle { color: #6b7280; margin: 0 0 24px; font-size: .95rem; }

    .msg-success { color: #16a34a; margin-bottom: 16px; font-size: .9rem; }
    .empty { color: #9ca3af; font-size: .95rem; }

    /* 行程卡片 */
    .ride-card {
      background: #fff; border-radius: 8px; padding: 20px 24px;
      margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
      display: flex; justify-content: space-between; align-items: center; gap: 16px;
    }
    .ride-info { flex: 1; }
    .ride-route { font-size: 1rem; font-weight: 600; margin-bottom: 6px; }
    .ride-route span { color: #9ca3af; margin: 0 6px; }
    .ride-meta { font-size: .85rem; color: #6b7280; line-height: 1.6; }

    .ride-amount { text-align: right; flex-shrink: 0; }
    .ride-amount .mileage { font-size: .85rem; color: #6b7280; margin-bottom: 4px; }
    .ride-amount .price { font-size: 1.4rem; font-weight: 700; color: #111; margin-bottom: 12px; }

    .btn-confirm {
      display: inline-block; padding: 9px 20px;
      background: #16a34a; color: #fff;
      border: none; border-radius: 6px; font-size: .9rem; cursor: pointer; white-space: nowrap;
    }
    .btn-confirm:hover { background: #15803d; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }

      /* 卡片改為垂直堆疊 */
      .ride-card { flex-direction: column; align-items: flex-start; gap: 16px; }
      .ride-amount { text-align: left; width: 100%; }
      .ride-amount .price { font-size: 1.6rem; }
      .btn-confirm { width: 100%; text-align: center; padding: 12px; font-size: 1rem; }

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
    <a href="profile.php">帳號設定</a>
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
  <a href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號
  </a>
</nav>

<div class="main">
  <h1>結單確認</h1>
  <p class="subtitle">以下行程由駕駛回報完畢，請確認里程與金額後結案。</p>

  <?php if ($msg === 'confirmed'): ?>
    <p class="msg-success">已成功確認，行程正式結案。</p>
  <?php endif; ?>

  <?php if (empty($rides)): ?>
    <p class="empty">目前沒有待確認的行程。</p>
  <?php else: ?>
    <?php foreach ($rides as $r): ?>
    <div class="ride-card">
      <div class="ride-info">
        <div class="ride-route">
          <?= htmlspecialchars($r['起點']) ?><span>→</span><?= htmlspecialchars($r['終點']) ?>
        </div>
        <div class="ride-meta">
          預計乘車：<?= htmlspecialchars($r['預計乘車時間']) ?><br>
          駕駛：<?= htmlspecialchars($r['駕駛姓名'] ?? '未指派') ?>
        </div>
      </div>
      <div class="ride-amount">
        <div class="mileage"><?= htmlspecialchars($r['實際里程']) ?> 公里</div>
        <div class="price">$<?= number_format($r['最終價格'], 0) ?></div>
        <form method="post" onsubmit="return confirm('確認結案此行程？')">
          <input type="hidden" name="ride_id" value="<?= (int)$r['RideID'] ?>">
          <button class="btn-confirm" type="submit">確認結案</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</body>
</html>
