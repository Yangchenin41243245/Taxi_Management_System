<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

// 篩選條件
$filterStatus = $_GET['status'] ?? '';
$validStatus  = ['', '預約中', '待確認', '已完成'];
if (!in_array($filterStatus, $validStatus, true)) {
    $filterStatus = '';
}

$sql = 'SELECT r.RideID, r.預計乘車時間, r.預約建立時間,
               p.乘客姓名, d.駕駛姓名,
               r.起點, r.終點,
               s.實際里程, s.最終價格, s.確認狀態
        FROM Rides r
        LEFT JOIN Passengers p ON p.PassengerID = r.PassengerID
        LEFT JOIN Drivers    d ON d.DriverID    = r.DriverID
        LEFT JOIN Settlements s ON s.RideID     = r.RideID';

$params = [];

if ($filterStatus === '預約中') {
    $sql .= ' WHERE s.RideID IS NULL';
} elseif ($filterStatus === '待確認') {
    $sql .= ' WHERE s.確認狀態 = ?';
    $params[] = '待確認';
} elseif ($filterStatus === '已完成') {
    $sql .= ' WHERE s.確認狀態 = ?';
    $params[] = '已確認';
}

$sql .= ' ORDER BY r.預計乘車時間 DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rides = $stmt->fetchAll();

// 各狀態總數
$counts = $pdo->query(
    'SELECT
       SUM(s.RideID IS NULL)           AS pending,
       SUM(s.確認狀態 = \'待確認\')     AS awaiting,
       SUM(s.確認狀態 = \'已確認\')     AS done
     FROM Rides r
     LEFT JOIN Settlements s ON s.RideID = r.RideID'
)->fetch();

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
  <title>行程總覽</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: sans-serif; display: flex; min-height: 100vh; background: #f3f4f6; }

    .sidebar {
      width: 200px; background: #312e81; color: #fff;
      display: flex; flex-direction: column; padding: 32px 0; flex-shrink: 0;
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

    .main { flex: 1; padding: 40px 36px; overflow-y: auto; }
    .main h1 { font-size: 1.4rem; margin: 0 0 24px; }

    /* 統計 */
    .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
    .stat-card {
      background: #fff; border-radius: 8px; padding: 16px 24px;
      flex: 1; min-width: 120px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .stat-card .num { font-size: 1.8rem; font-weight: 700; }
    .stat-card .lbl { font-size: .82rem; color: #6b7280; margin-top: 2px; }

    /* 篩選 */
    .filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .filter-bar a {
      padding: 6px 16px; border-radius: 9999px; font-size: .85rem;
      text-decoration: none; border: 1px solid #d1d5db;
      color: #374151; background: #fff; transition: background .15s;
    }
    .filter-bar a:hover { background: #f3f4f6; }
    .filter-bar a.active { background: #312e81; color: #fff; border-color: #312e81; }

    /* 表格 */
    .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    th { text-align: left; padding: 10px 12px; color: #6b7280; font-weight: 600; font-size: .8rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    td { padding: 11px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: .78rem; font-weight: 600; color: #fff; }
    .text-muted { color: #9ca3af; }
    .empty { padding: 32px; text-align: center; color: #9ca3af; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統（管理）</div>
  <nav>
    <a href="dashboard.php" class="active">行程總覽</a>
    <a href="assign.php">指派駕駛</a>
    <a href="report.php">帳務報表</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<div class="main">
  <h1>行程總覽</h1>

  <div class="stats">
    <div class="stat-card">
      <div class="num"><?= (int)(($counts['pending'] ?? 0) + ($counts['awaiting'] ?? 0) + ($counts['done'] ?? 0)) ?></div>
      <div class="lbl">全部行程</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:#d97706"><?= (int)($counts['pending'] ?? 0) ?></div>
      <div class="lbl">預約中</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:#2563eb"><?= (int)($counts['awaiting'] ?? 0) ?></div>
      <div class="lbl">待確認</div>
    </div>
    <div class="stat-card">
      <div class="num" style="color:#16a34a"><?= (int)($counts['done'] ?? 0) ?></div>
      <div class="lbl">已完成</div>
    </div>
  </div>

  <div class="filter-bar">
    <a href="dashboard.php"               class="<?= $filterStatus === ''     ? 'active' : '' ?>">全部</a>
    <a href="dashboard.php?status=預約中"  class="<?= $filterStatus === '預約中' ? 'active' : '' ?>">預約中</a>
    <a href="dashboard.php?status=待確認"  class="<?= $filterStatus === '待確認' ? 'active' : '' ?>">待確認</a>
    <a href="dashboard.php?status=已完成"  class="<?= $filterStatus === '已完成' ? 'active' : '' ?>">已完成</a>
  </div>

  <div class="card">
    <?php if (empty($rides)): ?>
      <div class="empty">沒有符合條件的行程。</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>乘客</th>
          <th>駕駛</th>
          <th>起點 → 終點</th>
          <th>預計乘車時間</th>
          <th>里程</th>
          <th>金額</th>
          <th>狀態</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rides as $r):
          $sl = statusLabel($r['確認狀態']);
          $sc = statusColor($r['確認狀態']);
        ?>
        <tr>
          <td class="text-muted"><?= (int)$r['RideID'] ?></td>
          <td><?= htmlspecialchars($r['乘客姓名'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['駕駛姓名'] ?? '—') ?></td>
          <td>
            <?= htmlspecialchars($r['起點']) ?>
            <span class="text-muted"> → </span>
            <?= htmlspecialchars($r['終點']) ?>
          </td>
          <td><?= htmlspecialchars($r['預計乘車時間']) ?></td>
          <td>
            <?= $r['實際里程'] !== null
                ? htmlspecialchars($r['實際里程']) . ' km'
                : '<span class="text-muted">—</span>' ?>
          </td>
          <td>
            <?= $r['最終價格'] !== null
                ? '$' . number_format((float)$r['最終價格'], 0)
                : '<span class="text-muted">—</span>' ?>
          </td>
          <td><span class="badge" style="background:<?= $sc ?>"><?= $sl ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
