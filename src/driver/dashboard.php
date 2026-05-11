<?php
session_start();

if (!isset($_SESSION['driver_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name = $_SESSION['driver_name'];
$id   = $_SESSION['driver_id'];

$countStmt = $pdo->prepare(
    'SELECT
       SUM(s.RideID IS NULL)            AS cnt_pending,
       SUM(s.確認狀態 = \'待確認\')       AS cnt_awaiting,
       SUM(s.確認狀態 = \'已確認\')       AS cnt_done
     FROM Rides r
     LEFT JOIN Settlements s ON s.RideID = r.RideID
     WHERE r.DriverID = ?'
);
$countStmt->execute([$id]);
$counts = $countStmt->fetch();

$stmt = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間, p.乘客姓名, s.確認狀態
     FROM Rides r
     LEFT JOIN Settlements s ON s.RideID = r.RideID
     LEFT JOIN Passengers p ON p.PassengerID = r.PassengerID
     WHERE r.DriverID = ?
     ORDER BY r.預計乘車時間 DESC
     LIMIT 5'
);
$stmt->execute([$id]);
$recent = $stmt->fetchAll();

function statusLabel(?string $s): string {
    if ($s === null)     return '預約中';
    if ($s === '待確認') return '待確認';
    return '已完成';
}
function statusColor(?string $s): string {
    if ($s === null)     return '#d97706';
    if ($s === '待確認') return '#2563eb';
    return '#16a34a';
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>駕駛主頁</title>
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
    .main h1 { font-size: 1.4rem; margin: 0 0 8px; }
    .main .subtitle { color: #6b7280; margin: 0 0 24px; font-size: .95rem; }

    .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .stat-card {
      background: #fff; border-radius: 8px; padding: 20px 24px;
      flex: 1; min-width: 130px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .stat-card .num { font-size: 2rem; font-weight: 700; }
    .stat-card .lbl { font-size: .85rem; color: #6b7280; margin-top: 4px; }

    .shortcuts { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
    .shortcuts a {
      padding: 10px 20px; background: #0f766e; color: #fff;
      border-radius: 6px; text-decoration: none; font-size: .9rem;
    }
    .shortcuts a:hover { background: #0d6460; }

    .card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .card h2 { font-size: 1rem; margin: 0 0 16px; color: #374151; }

    /* 桌面表格 */
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th { text-align: left; padding: 8px 12px; color: #6b7280; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
    td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
    tr:last-child td { border-bottom: none; }

    /* 手機卡片列 */
    .ride-cards { display: none; }
    .ride-item { padding: 14px 0; border-bottom: 1px solid #f3f4f6; }
    .ride-item:last-child { border-bottom: none; }
    .ride-item .route { font-size: .95rem; font-weight: 600; margin-bottom: 4px; }
    .ride-item .arrow { color: #9ca3af; margin: 0 4px; }
    .ride-item .meta { font-size: .82rem; color: #6b7280; margin-bottom: 6px; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: .8rem; font-weight: 600; color: #fff; }
    .empty { color: #9ca3af; font-size: .9rem; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }

      .shortcuts a { flex: 1; text-align: center; }
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
    <a href="dashboard.php" class="active">主頁</a>
    <a href="report.php">回報里程</a>
    <a href="revenue.php">營收彙整</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="report.php">
    <svg viewBox="0 0 24 24"><path d="M20 6h-2.18c.07-.44.18-.88.18-1a3 3 0 0 0-6 0c0 .12.11.56.18 1H10a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zm-7-1a1 1 0 0 1 2 0c0 .5-.25 1-.68 1.41L14 6.83l-.32-.42C13.25 6 13 5.5 13 5zm7 15H10V8h2v1a1 1 0 0 0 2 0V8h2v1a1 1 0 0 0 2 0V8h2v12z"/></svg>回報里程
  </a>
  <a href="revenue.php">
    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1H6.1c.12 2.19 1.76 3.42 3.7 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>營收彙整
  </a>
  <a href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號設定
  </a>
</nav>

<div class="main">
  <h1>歡迎，<?= htmlspecialchars($name) ?> 駕駛</h1>
  <p class="subtitle">今日行程概覽</p>

  <div class="stats">
    <div class="stat-card">
      <div class="num" style="color:#d97706"><?= (int)$counts['cnt_pending'] ?></div>
      <div class="lbl">預約中</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:#2563eb"><?= (int)$counts['cnt_awaiting'] ?></div>
      <div class="lbl">待確認</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:#16a34a"><?= (int)$counts['cnt_done'] ?></div>
      <div class="lbl">已完成</div>
    </div>
  </div>

  <div class="shortcuts">
    <a href="report.php">回報里程與價格</a>
  </div>

  <div class="card">
    <h2>最近行程</h2>
    <?php if (empty($recent)): ?>
      <p class="empty">目前沒有指派行程。</p>
    <?php else: ?>
      <!-- 桌面表格 -->
      <div class="desktop-table">
        <table>
          <thead>
            <tr><th>乘客</th><th>起點</th><th>終點</th><th>預計乘車時間</th><th>狀態</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r):
              $sl = statusLabel($r['確認狀態']);
              $sc = statusColor($r['確認狀態']);
            ?>
            <tr>
              <td><?= htmlspecialchars($r['乘客姓名'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['起點']) ?></td>
              <td><?= htmlspecialchars($r['終點']) ?></td>
              <td><?= htmlspecialchars($r['預計乘車時間']) ?></td>
              <td><span class="badge" style="background:<?= $sc ?>"><?= $sl ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <!-- 手機卡片 -->
      <div class="ride-cards">
        <?php foreach ($recent as $r):
          $sl = statusLabel($r['確認狀態']);
          $sc = statusColor($r['確認狀態']);
        ?>
        <div class="ride-item">
          <div class="route">
            <?= htmlspecialchars($r['起點']) ?><span class="arrow">→</span><?= htmlspecialchars($r['終點']) ?>
          </div>
          <div class="meta">
            乘客：<?= htmlspecialchars($r['乘客姓名'] ?? '—') ?>　<?= htmlspecialchars($r['預計乘車時間']) ?>
          </div>
          <span class="badge" style="background:<?= $sc ?>"><?= $sl ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
