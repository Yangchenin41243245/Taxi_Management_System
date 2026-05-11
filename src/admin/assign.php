<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rideId   = (int)($_POST['ride_id']   ?? 0);
    $driverId = (int)($_POST['driver_id'] ?? 0);

    if ($rideId <= 0 || $driverId <= 0) {
        $error = '請選擇有效的行程與駕駛';
    } else {
        // 確認行程存在且尚未指派
        $check = $pdo->prepare('SELECT RideID FROM Rides WHERE RideID = ? AND DriverID IS NULL');
        $check->execute([$rideId]);
        if (!$check->fetch()) {
            $error = '此行程不存在或已指派駕駛';
        } else {
            $upd = $pdo->prepare('UPDATE Rides SET DriverID = ? WHERE RideID = ?');
            $upd->execute([$driverId, $rideId]);
            $success = '指派成功！';
        }
    }
}

// 待指派行程（DriverID IS NULL）
$rides = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間, p.乘客姓名
     FROM Rides r
     LEFT JOIN Passengers p ON p.PassengerID = r.PassengerID
     WHERE r.DriverID IS NULL
     ORDER BY r.預計乘車時間 ASC'
);
$rides->execute();
$pendingRides = $rides->fetchAll();

// 所有駕駛
$drivers = $pdo->query('SELECT DriverID, 駕駛姓名 FROM Drivers ORDER BY 駕駛姓名')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>指派駕駛</title>
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
    .main h1 { font-size: 1.4rem; margin: 0 0 8px; }
    .main .subtitle { color: #6b7280; margin: 0 0 24px; font-size: .95rem; }

    .msg-success { color: #16a34a; margin-bottom: 16px; font-size: .9rem; }
    .msg-error   { color: #dc2626; margin-bottom: 16px; font-size: .9rem; }

    /* 行程卡片 */
    .ride-card {
      background: #fff; border-radius: 8px; padding: 20px 24px;
      margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
      display: flex; justify-content: space-between; align-items: center; gap: 16px;
    }
    .ride-info .route { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
    .ride-info .meta  { font-size: .85rem; color: #6b7280; }
    .ride-info .meta span { color: #9ca3af; margin: 0 4px; }

    .assign-form { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
    .assign-form select {
      padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px;
      font-size: .9rem; outline: none; min-width: 130px;
    }
    .assign-form select:focus { border-color: #4f46e5; }
    .btn-assign {
      padding: 8px 18px; background: #4f46e5; color: #fff;
      border: none; border-radius: 6px; font-size: .9rem; cursor: pointer; white-space: nowrap;
    }
    .btn-assign:hover { background: #4338ca; }

    .empty { color: #9ca3af; font-size: .95rem; }
    .no-driver { color: #dc2626; font-size: .85rem; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="brand">叫車系統（管理）</div>
  <nav>
    <a href="dashboard.php">行程總覽</a>
    <a href="assign.php" class="active">指派駕駛</a>
    <a href="report.php">帳務報表</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<div class="main">
  <h1>指派駕駛</h1>
  <p class="subtitle">以下行程尚未指派駕駛，請選擇後送出。</p>

  <?php if ($success): ?><p class="msg-success"><?= htmlspecialchars($success) ?></p><?php endif; ?>
  <?php if ($error):   ?><p class="msg-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

  <?php if (empty($drivers)): ?>
    <p class="no-driver">目前系統沒有任何駕駛帳號，請先請駕駛完成註冊。</p>
  <?php elseif (empty($pendingRides)): ?>
    <p class="empty">目前沒有待指派的行程。</p>
  <?php else: ?>
    <?php foreach ($pendingRides as $r): ?>
    <div class="ride-card">
      <div class="ride-info">
        <div class="route">
          <?= htmlspecialchars($r['起點']) ?>
          <span style="color:#9ca3af"> → </span>
          <?= htmlspecialchars($r['終點']) ?>
        </div>
        <div class="meta">
          乘客：<?= htmlspecialchars($r['乘客姓名'] ?? '—') ?>
          <span>｜</span>
          預計乘車：<?= htmlspecialchars($r['預計乘車時間']) ?>
        </div>
      </div>
      <form class="assign-form" method="post">
        <input type="hidden" name="ride_id" value="<?= (int)$r['RideID'] ?>">
        <select name="driver_id" required>
          <option value="">選擇駕駛</option>
          <?php foreach ($drivers as $d): ?>
            <option value="<?= (int)$d['DriverID'] ?>"><?= htmlspecialchars($d['駕駛姓名']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn-assign" type="submit">指派</button>
      </form>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</body>
</html>
