<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

// 整體彙整
$overall = $pdo->query(
    'SELECT COUNT(*) AS 總行程數,
            SUM(s.最終價格)  AS 總營收,
            SUM(s.實際里程)  AS 總里程
     FROM Rides r
     JOIN Settlements s ON s.RideID = r.RideID
     WHERE s.確認狀態 = \'已確認\''
)->fetch();

// 乘客彙整
$byPassenger = $pdo->query(
    'SELECT p.乘客姓名,
            COUNT(*)          AS 行程數,
            SUM(s.最終價格)   AS 累計金額
     FROM Rides r
     JOIN Passengers p   ON p.PassengerID = r.PassengerID
     JOIN Settlements s  ON s.RideID = r.RideID
     WHERE s.確認狀態 = \'已確認\'
     GROUP BY p.PassengerID
     ORDER BY 累計金額 DESC'
)->fetchAll();

// 駕駛彙整
$byDriver = $pdo->query(
    'SELECT d.駕駛姓名,
            COUNT(*)          AS 行程數,
            SUM(s.實際里程)   AS 累計里程,
            SUM(s.最終價格)   AS 累計金額
     FROM Rides r
     JOIN Drivers d      ON d.DriverID = r.DriverID
     JOIN Settlements s  ON s.RideID = r.RideID
     WHERE s.確認狀態 = \'已確認\'
     GROUP BY d.DriverID
     ORDER BY 累計金額 DESC'
)->fetchAll();

// 全部已確認行程明細
$detail = $pdo->query(
    'SELECT r.RideID, r.預計乘車時間,
            p.乘客姓名, d.駕駛姓名,
            r.起點, r.終點,
            s.實際里程, s.最終價格, s.完成確認時間
     FROM Rides r
     JOIN Passengers p  ON p.PassengerID = r.PassengerID
     JOIN Drivers d     ON d.DriverID    = r.DriverID
     JOIN Settlements s ON s.RideID      = r.RideID
     WHERE s.確認狀態 = \'已確認\'
     ORDER BY s.完成確認時間 DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>帳務報表</title>
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
    .section-title { font-size: 1rem; font-weight: 600; color: #374151; margin: 28px 0 12px; }

    /* 整體統計 */
    .stats { display: flex; gap: 16px; margin-bottom: 8px; flex-wrap: wrap; }
    .stat-card {
      background: #fff; border-radius: 8px; padding: 16px 24px;
      flex: 1; min-width: 130px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .stat-card .num { font-size: 1.8rem; font-weight: 700; color: #312e81; }
    .stat-card .lbl { font-size: .82rem; color: #6b7280; margin-top: 2px; }

    /* 表格共用 */
    .card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    th { text-align: left; padding: 10px 14px; color: #6b7280; font-weight: 600; font-size: .8rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    td { padding: 11px 14px; border-bottom: 1px solid #f3f4f6; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .text-muted { color: #9ca3af; }
    .empty { padding: 24px; text-align: center; color: #9ca3af; font-size: .9rem; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統（管理）</div>
  <nav>
    <a href="dashboard.php">行程總覽</a>
    <a href="assign.php">指派駕駛</a>
    <a href="report.php" class="active">帳務報表</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<div class="main">
  <h1>帳務報表</h1>

  <!-- 整體彙整 -->
  <div class="stats">
    <div class="stat-card">
      <div class="num"><?= (int)($overall['總行程數'] ?? 0) ?></div>
      <div class="lbl">已結案行程</div>
    </div>
    <div class="stat-card">
      <div class="num">$<?= number_format((float)($overall['總營收'] ?? 0), 0) ?></div>
      <div class="lbl">總營收（元）</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= number_format((float)($overall['總里程'] ?? 0), 1) ?></div>
      <div class="lbl">總里程（km）</div>
    </div>
  </div>

  <!-- 乘客彙整 -->
  <p class="section-title">乘客消費彙整</p>
  <div class="card">
    <?php if (empty($byPassenger)): ?>
      <div class="empty">尚無資料。</div>
    <?php else: ?>
    <table>
      <thead><tr><th>乘客</th><th>行程數</th><th>累計消費</th></tr></thead>
      <tbody>
        <?php foreach ($byPassenger as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['乘客姓名']) ?></td>
          <td><?= (int)$row['行程數'] ?></td>
          <td>$<?= number_format((float)$row['累計金額'], 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- 駕駛彙整 -->
  <p class="section-title">駕駛營收彙整</p>
  <div class="card">
    <?php if (empty($byDriver)): ?>
      <div class="empty">尚無資料。</div>
    <?php else: ?>
    <table>
      <thead><tr><th>駕駛</th><th>行程數</th><th>累計里程</th><th>累計收入</th></tr></thead>
      <tbody>
        <?php foreach ($byDriver as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['駕駛姓名']) ?></td>
          <td><?= (int)$row['行程數'] ?></td>
          <td><?= number_format((float)$row['累計里程'], 1) ?> km</td>
          <td>$<?= number_format((float)$row['累計金額'], 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- 明細 -->
  <p class="section-title">已結案行程明細</p>
  <div class="card">
    <?php if (empty($detail)): ?>
      <div class="empty">尚無已結案行程。</div>
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
          <th>結案時間</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detail as $r): ?>
        <tr>
          <td class="text-muted"><?= (int)$r['RideID'] ?></td>
          <td><?= htmlspecialchars($r['乘客姓名']) ?></td>
          <td><?= htmlspecialchars($r['駕駛姓名']) ?></td>
          <td>
            <?= htmlspecialchars($r['起點']) ?>
            <span class="text-muted"> → </span>
            <?= htmlspecialchars($r['終點']) ?>
          </td>
          <td><?= htmlspecialchars($r['預計乘車時間']) ?></td>
          <td><?= number_format((float)$r['實際里程'], 1) ?> km</td>
          <td>$<?= number_format((float)$r['最終價格'], 0) ?></td>
          <td><?= htmlspecialchars($r['完成確認時間']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
