<?php
session_start();

if (!isset($_SESSION['passenger_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$id   = $_SESSION['passenger_id'];
$name = $_SESSION['passenger_name'];

$sumStmt = $pdo->prepare(
    'SELECT COUNT(*)          AS 總行程數,
            SUM(s.實際里程)   AS 累積里程,
            SUM(s.最終價格)   AS 累積金額
     FROM Rides r
     JOIN Settlements s ON s.RideID = r.RideID
     WHERE r.PassengerID = ? AND s.確認狀態 = \'已確認\''
);
$sumStmt->execute([$id]);
$sum = $sumStmt->fetch();

$detailStmt = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間,
            d.駕駛姓名, s.實際里程, s.最終價格, s.完成確認時間
     FROM Rides r
     JOIN Settlements s  ON s.RideID   = r.RideID
     LEFT JOIN Drivers d ON d.DriverID = r.DriverID
     WHERE r.PassengerID = ? AND s.確認狀態 = \'已確認\'
     ORDER BY s.完成確認時間 DESC'
);
$detailStmt->execute([$id]);
$rides = $detailStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>里程紀錄</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: sans-serif; background: #f3f4f6; }

    .sidebar {
      position: fixed; top: 0; left: 0;
      width: 200px; height: 100vh;
      background: #1e3a5f; color: #fff;
      display: flex; flex-direction: column;
      padding: 32px 0; z-index: 100;
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

    .main { margin-left: 200px; padding: 40px 36px; min-height: 100vh; }
    .main h1 { font-size: 1.4rem; margin: 0 0 24px; }

    .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .stat-card {
      background: #fff; border-radius: 8px; padding: 20px 28px;
      flex: 1; min-width: 150px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .stat-card .num { font-size: 2rem; font-weight: 700; color: #1e3a5f; }
    .stat-card .lbl { font-size: .85rem; color: #6b7280; margin-top: 4px; }

    .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
    .card-title { padding: 16px 20px; font-size: .95rem; font-weight: 600; border-bottom: 1px solid #e5e7eb; }

    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th { text-align: left; padding: 10px 14px; color: #6b7280; font-weight: 600; font-size: .82rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    td { padding: 11px 14px; border-bottom: 1px solid #f3f4f6; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .text-muted { color: #9ca3af; }

    .ride-cards { display: none; }
    .ride-item { padding: 16px; border-bottom: 1px solid #f3f4f6; }
    .ride-item:last-child { border-bottom: none; }
    .ride-item .route { font-size: .95rem; font-weight: 600; margin-bottom: 4px; }
    .ride-item .arrow { color: #9ca3af; margin: 0 4px; }
    .ride-item .meta { font-size: .82rem; color: #6b7280; margin-bottom: 8px; line-height: 1.6; }
    .ride-item .foot { display: flex; justify-content: space-between; align-items: center; }
    .ride-item .price { font-size: 1rem; font-weight: 700; color: #1e3a5f; }

    .empty { padding: 32px; text-align: center; color: #9ca3af; font-size: .95rem; }

    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }
      .stats { gap: 12px; }
      .stat-card { min-width: calc(50% - 6px); padding: 14px 16px; }
      .stat-card .num { font-size: 1.6rem; }

      .desktop-table { display: none; }
      .ride-cards { display: block; }

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
    <a href="record.php" class="active">里程紀錄</a>
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
  <a href="record.php" class="active">
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
  <h1>里程紀錄</h1>

  <div class="stats">
    <div class="stat-card">
      <div class="num"><?= (int)($sum['總行程數'] ?? 0) ?></div>
      <div class="lbl">已完成行程</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= number_format((float)($sum['累積里程'] ?? 0), 1) ?></div>
      <div class="lbl">累積里程（km）</div>
    </div>
    <div class="stat-card">
      <div class="num">$<?= number_format((float)($sum['累積金額'] ?? 0), 0) ?></div>
      <div class="lbl">累積消費（元）</div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">已完成行程明細</div>
    <?php if (empty($rides)): ?>
      <div class="empty">尚無已完成行程。</div>
    <?php else: ?>

      <!-- 桌面表格 -->
      <div class="desktop-table">
        <table>
          <thead>
            <tr>
              <th>#</th><th>起點 → 終點</th><th>駕駛</th>
              <th>預計乘車時間</th><th>里程</th><th>金額</th><th>結案時間</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rides as $r): ?>
            <tr>
              <td class="text-muted"><?= (int)$r['RideID'] ?></td>
              <td><?= htmlspecialchars($r['起點']) ?> <span class="text-muted">→</span> <?= htmlspecialchars($r['終點']) ?></td>
              <td><?= htmlspecialchars($r['駕駛姓名'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['預計乘車時間']) ?></td>
              <td><?= number_format((float)$r['實際里程'], 1) ?> km</td>
              <td>$<?= number_format((float)$r['最終價格'], 0) ?></td>
              <td><?= htmlspecialchars($r['完成確認時間']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 手機卡片 -->
      <div class="ride-cards">
        <?php foreach ($rides as $r): ?>
        <div class="ride-item">
          <div class="route">
            <?= htmlspecialchars($r['起點']) ?><span class="arrow">→</span><?= htmlspecialchars($r['終點']) ?>
          </div>
          <div class="meta">
            駕駛：<?= htmlspecialchars($r['駕駛姓名'] ?? '—') ?><br>
            <?= htmlspecialchars($r['預計乘車時間']) ?>　里程：<?= number_format((float)$r['實際里程'], 1) ?> km<br>
            結案：<?= htmlspecialchars($r['完成確認時間']) ?>
          </div>
          <div class="foot">
            <span class="text-muted" style="font-size:.82rem">#<?= (int)$r['RideID'] ?></span>
            <span class="price">$<?= number_format((float)$r['最終價格'], 0) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>
</div>

</body>
</html>
