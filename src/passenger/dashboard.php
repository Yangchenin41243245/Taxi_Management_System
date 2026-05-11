<?php
session_start();

if (!isset($_SESSION['passenger_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$name = $_SESSION['passenger_name'];
$id   = $_SESSION['passenger_id'];

$stmt = $pdo->prepare(
    'SELECT r.RideID, r.起點, r.終點, r.預計乘車時間, s.確認狀態
     FROM Rides r
     LEFT JOIN Settlements s ON s.RideID = r.RideID
     WHERE r.PassengerID = ?
     ORDER BY r.預計乘車時間 DESC
     LIMIT 5'
);
$stmt->execute([$id]);
$recent = $stmt->fetchAll();

$statusLabel = ['待確認' => '待確認', '已確認' => '已完成'];
$statusColor = ['待確認' => '#2563eb', '已確認' => '#16a34a'];
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>乘客主頁</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: sans-serif; background: #f3f4f6; }

    /* ── 桌面版側欄 ── */
    .sidebar {
      position: fixed; top: 0; left: 0;
      width: 200px; height: 100vh;
      background: #1e3a5f; color: #fff;
      display: flex; flex-direction: column;
      padding: 32px 0; z-index: 100;
    }
    .sidebar .brand {
      font-size: 1rem; font-weight: bold;
      padding: 0 24px 28px;
      border-bottom: 1px solid #2d527a; letter-spacing: .05em;
    }
    .sidebar nav { margin-top: 16px; flex: 1; }
    .sidebar nav a {
      display: block; padding: 12px 24px;
      color: #cbd5e1; text-decoration: none; font-size: .95rem;
      border-left: 3px solid transparent; transition: background .15s, color .15s;
    }
    .sidebar nav a:hover,
    .sidebar nav a.active { background: #2d527a; color: #fff; border-left-color: #60a5fa; }
    .sidebar .logout { padding: 16px 24px; border-top: 1px solid #2d527a; }
    .sidebar .logout a { color: #94a3b8; font-size: .85rem; text-decoration: none; }
    .sidebar .logout a:hover { color: #fff; }

    /* ── 主內容 ── */
    .main { margin-left: 200px; padding: 40px 36px; min-height: 100vh; }
    .main h1 { font-size: 1.4rem; margin: 0 0 8px; }
    .main .subtitle { color: #6b7280; margin: 0 0 28px; font-size: .95rem; }

    /* 卡片 */
    .card {
      background: #fff; border-radius: 8px;
      padding: 24px; margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,.08);
    }
    .card h2 { font-size: 1rem; margin: 0 0 16px; color: #374151; }

    /* 快捷按鈕 */
    .shortcuts { display: flex; gap: 12px; flex-wrap: wrap; }
    .shortcuts a {
      display: inline-block; padding: 10px 20px;
      background: #2563eb; color: #fff;
      border-radius: 6px; text-decoration: none; font-size: .9rem;
    }
    .shortcuts a:hover { background: #1d4ed8; }
    .shortcuts a.secondary {
      background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;
    }
    .shortcuts a.secondary:hover { background: #e5e7eb; }

    /* 桌面版表格 */
    .ride-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
    .ride-table th { text-align: left; padding: 8px 12px; color: #6b7280; font-weight: 600; border-bottom: 1px solid #e5e7eb; }
    .ride-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; }
    .ride-table tr:last-child td { border-bottom: none; }

    /* 手機版行程卡片（預設隱藏） */
    .ride-cards { display: none; }
    .ride-row {
      padding: 14px 0; border-bottom: 1px solid #f3f4f6;
    }
    .ride-row:last-child { border-bottom: none; }
    .ride-row .route { font-size: .95rem; font-weight: 600; margin-bottom: 4px; }
    .ride-row .route .arrow { color: #9ca3af; margin: 0 4px; }
    .ride-row .meta { font-size: .82rem; color: #6b7280; margin-bottom: 6px; }

    .badge {
      display: inline-block; padding: 2px 10px;
      border-radius: 9999px; font-size: .78rem; font-weight: 600; color: #fff;
    }
    .empty { color: #9ca3af; font-size: .9rem; }

    /* ── 手機版底部導覽列 ── */
    .bottom-nav { display: none; }

    /* ── RWD 手機 ── */
    @media (max-width: 768px) {
      .sidebar { display: none; }

      .main {
        margin-left: 0;
        padding: 24px 16px 80px; /* 底部留空給 bottom-nav */
      }
      .main h1 { font-size: 1.2rem; }

      .shortcuts a { flex: 1; text-align: center; }

      /* 切換顯示：隱藏表格，改顯示卡片列 */
      .ride-table-wrap { display: none; }
      .ride-cards { display: block; }

      /* 底部導覽列 */
      .bottom-nav {
        display: flex;
        position: fixed; bottom: 0; left: 0; right: 0;
        background: #1e3a5f; z-index: 100;
        border-top: 1px solid #2d527a;
      }
      .bottom-nav a {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 10px 4px; color: #94a3b8;
        text-decoration: none; font-size: .7rem; gap: 3px;
      }
      .bottom-nav a svg { width: 22px; height: 22px; fill: currentColor; }
      .bottom-nav a.active,
      .bottom-nav a:hover { color: #60a5fa; }
      .bottom-nav .logout-btn {
        flex: 1; display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        padding: 10px 4px; color: #94a3b8;
        background: none; border: none; font-size: .7rem; gap: 3px; cursor: pointer;
      }
      .bottom-nav .logout-btn svg { width: 22px; height: 22px; fill: currentColor; }
      .bottom-nav .logout-btn:hover { color: #f87171; }
    }
  </style>
</head>
<body>

<!-- 桌面版側欄 -->
<aside class="sidebar">
  <div class="brand">叫車系統</div>
  <nav>
    <a href="dashboard.php" class="active">主頁</a>
    <a href="book.php">行程預約</a>
    <a href="history.php">歷史查詢</a>
    <a href="profile.php">帳號設定</a>
  </nav>
  <div class="logout"><a href="logout.php">登出</a></div>
</aside>

<!-- 手機版底部導覽列 -->
<nav class="bottom-nav">
  <a href="dashboard.php" class="active">
    <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>主頁
  </a>
  <a href="book.php">
    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 18H5V8h14v13zM7 10h5v5H7z"/></svg>行程預約
  </a>
  <a href="history.php">
    <svg viewBox="0 0 24 24"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6a7 7 0 1 1 2.05 4.95L6.64 18.36A9 9 0 1 0 13 3zm-1 5v5l4.25 2.53.77-1.28-3.52-2.09V8H12z"/></svg>歷史查詢
  </a>
  <a href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>帳號設定
  </a>
</nav>

<div class="main">
  <h1>歡迎，<?= htmlspecialchars($name) ?></h1>
  <p class="subtitle">今天要去哪裡？</p>

  <div class="card">
    <h2>快捷操作</h2>
    <div class="shortcuts">
      <a href="book.php">＋ 預約新行程</a>
      <a href="history.php" class="secondary">查詢歷史</a>
    </div>
  </div>

  <div class="card">
    <h2>最近行程</h2>
    <?php if (empty($recent)): ?>
      <p class="empty">尚無行程紀錄。<a href="book.php">立即預約</a></p>
    <?php else: ?>

      <!-- 桌面版表格 -->
      <div class="ride-table-wrap">
        <table class="ride-table">
          <thead>
            <tr>
              <th>起點</th><th>終點</th><th>預計乘車時間</th><th>狀態</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r):
              $s     = $r['確認狀態'];
              $label = $s ? $statusLabel[$s] : '預約中';
              $color = $s ? $statusColor[$s] : '#d97706';
            ?>
            <tr>
              <td><?= htmlspecialchars($r['起點']) ?></td>
              <td><?= htmlspecialchars($r['終點']) ?></td>
              <td><?= htmlspecialchars($r['預計乘車時間']) ?></td>
              <td><span class="badge" style="background:<?= $color ?>"><?= $label ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- 手機版卡片列 -->
      <div class="ride-cards">
        <?php foreach ($recent as $r):
          $s     = $r['確認狀態'];
          $label = $s ? $statusLabel[$s] : '預約中';
          $color = $s ? $statusColor[$s] : '#d97706';
        ?>
        <div class="ride-row">
          <div class="route">
            <?= htmlspecialchars($r['起點']) ?>
            <span class="arrow">→</span>
            <?= htmlspecialchars($r['終點']) ?>
          </div>
          <div class="meta"><?= htmlspecialchars($r['預計乘車時間']) ?></div>
          <span class="badge" style="background:<?= $color ?>"><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>
</div>

</body>
</html>
