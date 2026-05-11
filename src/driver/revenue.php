<?php
session_start();

if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name = $_SESSION['driver_name'];
$id   = $_SESSION['driver_id'];

$summary = $pdo->prepare(
    'SELECT COUNT(*) AS 總行程數, SUM(s.最終價格) AS 累計金額
     FROM Rides r
     JOIN Settlements s ON s.RideID = r.RideID
     WHERE r.DriverID = ? AND s.確認狀態 = ?'
);
$summary->execute([$id, '已確認']);
$sum = $summary->fetch();

$stmt = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間,
            p.乘客姓名, s.實際里程, s.最終價格, s.完成確認時間
     FROM Rides r
     JOIN Settlements s ON s.RideID = r.RideID
     LEFT JOIN Passengers p ON p.PassengerID = r.PassengerID
     WHERE r.DriverID = ? AND s.確認狀態 = ?
     ORDER BY s.完成確認時間 DESC'
);
$stmt->execute([$id, '已確認']);
$rides = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>營收彙整</title>
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

    .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .stat-card {
      background: #fff; border-radius: 8px; padding: 20px 28px;
      flex: 1; min-width: 150px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .stat-card .num { font-size: 2rem; font-weight: 700; color: #134e4a; }
    .stat-card .lbl { font-size: .85rem; color: #6b7280; margin-top: 4px; }

    /* 桌面表格 */
    .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
    .card-title { padding: 16px 20px; font-size: .95rem; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th { text-align: left; padding: 10px 14px; color: #6b7280; font-weight: 600; font-size: .82rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    td { padding: 11px 14px; border-bottom: 1px solid #f3f4f6; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .text-muted { color: #9ca3af; }

    /* 手機版卡片 */
    .ride-cards { display: none; }
    .ride-item { padding: 16px; border-bottom: 1px solid #f3f4f6; }
    .ride-item:last-child { border-bottom: none; }
    .ride-item .route { font-size: .95rem; font-weight: 600; margin-bottom: 4px; }
    .ride-item .arrow { color: #9ca3af; margin: 0 4px; }
    .ride-item .meta { font-size: .82rem; color: #6b7280; margin-bottom: 8px; line-height: 1.6; }
    .ride-item .foot { display: flex; justify-content: space-between; align-items: center; }
    .ride-item .price { font-size: 1rem; font-weight: 700; color: #134e4a; }

    .empty { padding: 32px; text-align: center; color: #9ca3af; font-size: .95rem; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }

      .desktop-table { display: none; }
      .ride-cards { display: block; }

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
    <a href="revenue.php" class="active">營收彙整</a>
    <a href="profile.php">帳號設定</a>
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
  <a href="revenue.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1H6.1c.12 2.19 1.76 3.42 3.7 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>營收彙整
  </a>
  <a href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號設定
  </a>
</nav>

<div class="main">
  <h1>營收彙整</h1>

  <div class="stats">
    <div class="stat-card">
      <div class="num"><?= (int)$sum['總行程數'] ?></div>
      <div class="lbl">已完成行程</div>
    </div>
    <div class="stat-card">
      <div class="num">$<?= number_format((float)($sum['累計金額'] ?? 0), 0) ?></div>
      <div class="lbl">累計收入（元）</div>
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
              <th>#</th><th>乘客</th><th>起點 → 終點</th>
              <th>預計乘車時間</th><th>里程</th><th>金額</th><th>結案時間</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rides as $r): ?>
            <tr>
              <td class="text-muted"><?= (int)$r['RideID'] ?></td>
              <td><?= htmlspecialchars($r['乘客姓名'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['起點']) ?> <span class="text-muted">→</span> <?= htmlspecialchars($r['終點']) ?></td>
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
            乘客：<?= htmlspecialchars($r['乘客姓名'] ?? '—') ?><br>
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
