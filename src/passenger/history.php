<?php
session_start();

if (!isset($_SESSION['passenger_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name = $_SESSION['passenger_name'];
$id   = $_SESSION['passenger_id'];

$filterStatus = $_GET['status'] ?? '';
$validStatus  = ['', '預約中', '待確認', '已完成'];
if (!in_array($filterStatus, $validStatus, true)) $filterStatus = '';

$sql = 'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間,
               d.駕駛姓名, s.實際里程, s.最終價格, s.確認狀態
        FROM Rides r
        LEFT JOIN Settlements s ON s.RideID = r.RideID
        LEFT JOIN Drivers d ON d.DriverID = r.DriverID
        WHERE r.PassengerID = ?';
$params = [$id];

if ($filterStatus === '預約中') {
    $sql .= ' AND s.RideID IS NULL';
} elseif ($filterStatus === '待確認') {
    $sql .= ' AND s.確認狀態 = ?'; $params[] = '待確認';
} elseif ($filterStatus === '已完成') {
    $sql .= ' AND s.確認狀態 = ?'; $params[] = '已確認';
}
$sql .= ' ORDER BY r.預計乘車時間 DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rides = $stmt->fetchAll();

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
  <title>歷史查詢</title>
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
    .main h1 { font-size: 1.4rem; margin: 0 0 20px; }

    /* 篩選列 */
    .filter-bar { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-bar a {
      padding: 6px 16px; border-radius: 9999px; font-size: .85rem;
      text-decoration: none; border: 1px solid #d1d5db;
      color: #374151; background: #fff; transition: background .15s;
    }
    .filter-bar a:hover { background: #f3f4f6; }
    .filter-bar a.active { background: #1e3a5f; color: #fff; border-color: #1e3a5f; }

    /* 桌面版表格 */
    .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    th { text-align: left; padding: 10px 14px; color: #6b7280; font-weight: 600; font-size: .82rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }

    /* 手機版卡片（預設隱藏） */
    .ride-cards { display: none; }
    .ride-item {
      padding: 16px; border-bottom: 1px solid #f3f4f6;
    }
    .ride-item:last-child { border-bottom: none; }
    .ride-item .route { font-size: .95rem; font-weight: 600; margin-bottom: 4px; }
    .ride-item .route .arrow { color: #9ca3af; margin: 0 4px; }
    .ride-item .meta { font-size: .82rem; color: #6b7280; margin-bottom: 8px; line-height: 1.6; }
    .ride-item .foot { display: flex; justify-content: space-between; align-items: center; }
    .ride-item .price { font-size: .95rem; font-weight: 600; }

    .badge { display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: .78rem; font-weight: 600; color: #fff; }
    .text-muted { color: #9ca3af; }
    .link-confirm { color: #2563eb; text-decoration: none; font-size: .82rem; display: block; margin-top: 4px; }
    .link-confirm:hover { text-decoration: underline; }
    .empty { padding: 32px; text-align: center; color: #9ca3af; font-size: .95rem; }

    /* 底部導覽列（手機） */
    .bottom-nav { display: none; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { margin-left: 0; padding: 24px 16px 88px; }
      .main h1 { font-size: 1.2rem; }

      /* 切換 */
      .desktop-table { display: none; }
      .ride-cards { display: block; }

      .filter-bar a { padding: 5px 12px; font-size: .8rem; }

      .bottom-nav {
        display: flex; position: fixed; bottom: 0; left: 0; right: 0;
        background: #1e3a5f; z-index: 100; border-top: 1px solid #2d527a;
      }
      .bottom-nav a {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 10px 4px; color: #94a3b8;
        text-decoration: none; font-size: .7rem; gap: 3px;
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
    <a href="history.php" class="active">歷史查詢</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<nav class="bottom-nav">
  <a href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="book.php">
    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 18H5V8h14v13zM7 10h5v5H7z"/></svg>行程預約
  </a>
  <a href="history.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6a7 7 0 1 1 2.05 4.95L6.64 18.36A9 9 0 1 0 13 3zm-1 5v5l4.25 2.53.77-1.28-3.52-2.09V8H12z"/></svg>歷史查詢
  </a>
  <a href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號設定
  </a>
</nav>

<div class="main">
  <h1>歷史查詢</h1>

  <div class="filter-bar">
    <a href="history.php"               class="<?= $filterStatus === ''     ? 'active' : '' ?>">全部</a>
    <a href="history.php?status=預約中"  class="<?= $filterStatus === '預約中' ? 'active' : '' ?>">預約中</a>
    <a href="history.php?status=待確認"  class="<?= $filterStatus === '待確認' ? 'active' : '' ?>">待確認</a>
    <a href="history.php?status=已完成"  class="<?= $filterStatus === '已完成' ? 'active' : '' ?>">已完成</a>
  </div>

  <div class="card">
    <?php if (empty($rides)): ?>
      <div class="empty">沒有符合條件的行程紀錄。</div>
    <?php else: ?>

      <!-- 桌面版表格 -->
      <div class="desktop-table">
        <table>
          <thead>
            <tr>
              <th>#</th><th>起點 → 終點</th><th>預計乘車時間</th>
              <th>駕駛</th><th>里程</th><th>金額</th><th>狀態</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rides as $r):
              $sl = statusLabel($r['確認狀態']);
              $sc = statusColor($r['確認狀態']);
            ?>
            <tr>
              <td class="text-muted"><?= (int)$r['RideID'] ?></td>
              <td><?= htmlspecialchars($r['起點']) ?> <span class="text-muted">→</span> <?= htmlspecialchars($r['終點']) ?></td>
              <td><?= htmlspecialchars($r['預計乘車時間']) ?></td>
              <td><?= htmlspecialchars($r['駕駛姓名'] ?? '—') ?></td>
              <td><?= $r['實際里程'] !== null ? htmlspecialchars($r['實際里程']) . ' km' : '<span class="text-muted">—</span>' ?></td>
              <td><?= $r['最終價格'] !== null ? '$' . number_format($r['最終價格'], 0) : '<span class="text-muted">—</span>' ?></td>
              <td>
                <span class="badge" style="background:<?= $sc ?>"><?= $sl ?></span>
                <?php if ($r['確認狀態'] === '待確認'): ?>
                  <a class="link-confirm" href="confirm.php">前往確認</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 手機版卡片 -->
      <div class="ride-cards">
        <?php foreach ($rides as $r):
          $sl = statusLabel($r['確認狀態']);
          $sc = statusColor($r['確認狀態']);
        ?>
        <div class="ride-item">
          <div class="route">
            <?= htmlspecialchars($r['起點']) ?>
            <span class="arrow">→</span>
            <?= htmlspecialchars($r['終點']) ?>
          </div>
          <div class="meta">
            <?= htmlspecialchars($r['預計乘車時間']) ?><br>
            駕駛：<?= htmlspecialchars($r['駕駛姓名'] ?? '—') ?>
            　里程：<?= $r['實際里程'] !== null ? htmlspecialchars($r['實際里程']) . ' km' : '—' ?>
          </div>
          <div class="foot">
            <div>
              <span class="badge" style="background:<?= $sc ?>"><?= $sl ?></span>
              <?php if ($r['確認狀態'] === '待確認'): ?>
                <a class="link-confirm" href="confirm.php">前往確認</a>
              <?php endif; ?>
            </div>
            <div class="price"><?= $r['最終價格'] !== null ? '$' . number_format($r['最終價格'], 0) : '' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>
</div>

</body>
</html>
