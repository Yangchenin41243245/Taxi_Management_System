<?php
/* =====================================================================
 *  三端簡易叫車紀錄管理系統 — 專案入口頁（index.php）
 *  第十二組  資料庫：csieDBTeam12
 * =====================================================================
 *
 *  ── 一、啟動方式 ────────────────────────────────────────────────
 *    1. 開啟終端機，切換到專案根目錄（含 docker-compose.yml 的位置）
 *    2. 執行：  docker compose up -d
 *    3. 關閉：  docker compose down
 *
 *  ── 二、各服務網址 ──────────────────────────────────────────────
 *    入口頁         http://localhost:8080
 *    乘客端         http://localhost:8080/src/passenger/register.php
 *    駕駛端         http://localhost:8080/src/driver/register.php
 *    管理端         http://localhost:8080/src/admin/register.php
 *    phpMyAdmin     http://localhost:8082
 *    連線測試       http://localhost:8080/src/dbtest.php
 *
 *  ── 三、phpMyAdmin 登入帳密 ────────────────────────────────────
 *    使用者名稱：  root
 *    密碼：        myPassword
 *    （設定於 docker-compose.yml 的 MYSQL_ROOT_PASSWORD）
 *
 *    PHP 程式連線用帳號（寫在 src/db.php）：
 *    host = db-proj-db    db = csieDBTeam12
 *    user = mymy          pass = myPassword
 *
 *  ── 四、資料庫自動建立 ──────────────────────────────────────────
 *    docker-compose.yml 在「全新啟動」時會自動完成：
 *      (1) MYSQL_DATABASE: csieDBTeam12  → 自動建立空資料庫
 *      (2) 掛載 init.sql 至初始化目錄     → 自動執行建立 5 張資料表
 *          ( Passengers / Drivers / Admins / Rides / Settlements )
 *    因此首次啟動後「不需手動進 phpMyAdmin 跑 SQL」。
 *
 *    ⚠ 注意：上述自動初始化只在 db_data 資料夾為空（第一次）時生效。
 *          若資料庫名稱或 root 密碼有更動，需先刪除 db_data 再重啟才會套用。
 *
 *  ── 五、檢查清單（部署後驗證） ──────────────────────────────────
 *    □ http://localhost:8082 能以 root / myPassword 登入
 *    □ 左側可見 csieDBTeam12 資料庫，內含 5 張資料表
 *    □ http://localhost:8080/src/dbtest.php 顯示「✅ 成功連線」
 *    □ 三端 register.php 皆可正常開啟並註冊
 * =====================================================================
 */
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>三端簡易叫車紀錄管理系統</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Microsoft JhengHei', sans-serif;
      background: #f1f5f9; color: #1e293b; min-height: 100vh;
    }

    header {
      background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
      color: #fff; padding: 48px 24px 40px; text-align: center;
    }
    header h1 { margin: 0 0 10px; font-size: 1.8rem; letter-spacing: .03em; }
    header p  { margin: 0; font-size: 1rem; color: #bfdbfe; }

    .container { max-width: 860px; margin: 0 auto; padding: 40px 20px 60px; }

    /* 入口卡片 */
    .portals { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 40px; }
    .portal-card {
      flex: 1; min-width: 220px;
      background: #fff; border-radius: 10px;
      padding: 28px 24px; text-align: center;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
      text-decoration: none; color: inherit;
      border-top: 4px solid transparent;
      transition: transform .15s, box-shadow .15s;
    }
    .portal-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,.12); }
    .portal-card.passenger { border-color: #2563eb; }
    .portal-card.driver    { border-color: #0f766e; }
    .portal-card.admin     { border-color: #4f46e5; }

    .portal-card .icon {
      width: 52px; height: 52px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px; font-size: 1.5rem;
    }
    .passenger .icon { background: #dbeafe; }
    .driver    .icon { background: #ccfbf1; }
    .admin     .icon { background: #e0e7ff; }

    .portal-card h2 { font-size: 1.05rem; margin: 0 0 8px; }
    .portal-card p  { font-size: .85rem; color: #64748b; margin: 0 0 18px; line-height: 1.6; }

    .btn {
      display: inline-block; padding: 8px 22px;
      border-radius: 6px; font-size: .9rem;
      color: #fff; text-decoration: none;
    }
    .passenger .btn { background: #2563eb; }
    .driver    .btn { background: #0f766e; }
    .admin     .btn { background: #4f46e5; }
    .btn:hover { opacity: .88; }

    /* 流程說明 */
    .section-title {
      font-size: 1rem; font-weight: 700; color: #334155;
      margin: 0 0 16px; padding-bottom: 8px;
      border-bottom: 2px solid #e2e8f0;
    }
    .flow {
      background: #fff; border-radius: 10px;
      padding: 24px 28px; margin-bottom: 32px;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
      font-size: .9rem; line-height: 2;
    }
    .flow .step {
      display: flex; align-items: flex-start; gap: 12px; margin-bottom: 6px;
    }
    .flow .badge {
      background: #2563eb; color: #fff;
      border-radius: 50%; width: 22px; height: 22px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 700; margin-top: 2px;
    }
    .flow .arrow { color: #94a3b8; padding-left: 34px; font-size: .8rem; }

    /* 技術說明 */
    .tech {
      background: #fff; border-radius: 10px;
      padding: 20px 28px;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
      font-size: .88rem; color: #475569; line-height: 1.8;
    }
    .tech span {
      display: inline-block; background: #f1f5f9;
      border: 1px solid #e2e8f0; border-radius: 4px;
      padding: 2px 8px; margin: 2px 4px 2px 0; font-size: .82rem;
    }

    /* 部署說明 */
    .info {
      background: #fff; border-radius: 10px;
      padding: 22px 28px; margin-bottom: 32px;
      box-shadow: 0 2px 8px rgba(0,0,0,.06);
      font-size: .88rem; color: #475569; line-height: 1.9;
    }
    .info dl { margin: 0; }
    .info dt { font-weight: 700; color: #334155; margin-top: 12px; }
    .info dt:first-child { margin-top: 0; }
    .info dd { margin: 2px 0 0; }
    .info code {
      background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px;
      padding: 1px 6px; font-size: .85rem; color: #1e3a5f;
    }

    footer {
      text-align: center; padding: 24px;
      font-size: .82rem; color: #94a3b8;
    }
  </style>
</head>
<body>

<header>
  <h1>三端簡易叫車紀錄管理系統</h1>
  <p>透過乘客、駕駛、管理三端協作，取代傳統紙本派車單</p>
</header>

<div class="container">

  <!-- 三個入口 -->
  <p class="section-title">系統入口</p>
  <div class="portals">
    <a href="src/passenger/register.php" class="portal-card passenger">
      <div class="icon">🧍</div>
      <h2>乘客端</h2>
      <p>預約行程、查看歷史、確認帳務、查詢累積里程</p>
      <span class="btn">進入乘客端</span>
    </a>
    <a href="src/driver/register.php" class="portal-card driver">
      <div class="icon">🚗</div>
      <h2>駕駛端</h2>
      <p>查看指派行程、回報里程與費用、查看個人營收</p>
      <span class="btn">進入駕駛端</span>
    </a>
    <a href="src/admin/register.php" class="portal-card admin">
      <div class="icon">🖥️</div>
      <h2>管理端</h2>
      <p>指派駕駛至行程、監控所有行程狀態、產出帳務報表</p>
      <span class="btn">進入管理端</span>
    </a>
  </div>

  <!-- 行程流程 -->
  <p class="section-title">行程狀態流程</p>
  <div class="flow">
    <div class="step"><div class="badge">1</div><div>乘客填寫起點、終點、預計乘車時間完成預約 → <strong>預約中</strong></div></div>
    <div class="arrow">↓</div>
    <div class="step"><div class="badge">2</div><div>管理員從後台選擇駕駛並指派行程</div></div>
    <div class="arrow">↓</div>
    <div class="step"><div class="badge">3</div><div>駕駛完成行程後回報實際里程與最終金額 → <strong>待確認</strong></div></div>
    <div class="arrow">↓</div>
    <div class="step"><div class="badge">4</div><div>乘客確認里程與金額後結案 → <strong>已完成</strong>，計入駕駛營收</div></div>
  </div>

  <!-- 啟動與檢查說明 -->
  <p class="section-title">啟動與檢查</p>
  <div class="info">
    <dl>
      <dt>啟動指令</dt>
      <dd>於專案根目錄執行 <code>docker compose up -d</code>（關閉用 <code>docker compose down</code>）</dd>

      <dt>資料庫</dt>
      <dd>名稱 <code>csieDBTeam12</code>；首次啟動時由 <code>docker-compose.yml</code> 自動建立資料庫並執行 <code>init.sql</code> 建好 5 張資料表，無需手動操作。</dd>

      <dt>phpMyAdmin</dt>
      <dd><code>http://localhost:8082</code> ｜ 帳號 <code>root</code> ／ 密碼 <code>myPassword</code></dd>

      <dt>連線測試</dt>
      <dd>開啟 <code>src/dbtest.php</code> 顯示「✅ 成功連線」即代表資料庫正常。</dd>
    </dl>
  </div>

</div>

<footer>資料庫期末專案 &nbsp;|&nbsp; 三端簡易叫車紀錄管理系統</footer>

</body>
</html>
