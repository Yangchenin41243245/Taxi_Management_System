# 三端簡易叫車紀錄管理系統

## 專案概述

透過客戶、駕駛與平台三端的配合，手動紀錄行程資訊，並透過乘客確認機制確保帳務真實性，取代傳統紙本派車單。

- **適用對象**：乘客、接單司機、車隊管理人員
- **技術架構**：PHP 8.2 + MariaDB 10.6 + Apache（Docker）
- **開發環境**：`http://localhost:8080`
- **資料庫管理**：phpMyAdmin `http://localhost:8082`（root / myPassword）

---

## 開發環境啟動方式

```powershell
cd C:\dev\docker
docker compose up -d
```

關閉：

```powershell
docker compose down
```

---

## 專案目錄結構

```
C:\dev\docker\
├── docker-compose.yml
├── Dockerfile
└── src/                        ← 所有 PHP 程式碼放這裡
    ├── db.php                  ← 資料庫連線（共用）
    ├── passenger/              ← 乘客端
    │   ├── register.php        ← 建立帳號
    │   ├── login.php           ← 登入
    │   ├── dashboard.php       ← 登入後主頁
    │   ├── book.php            ← 行程預約
    │   ├── confirm.php         ← 結單確認
    │   └── history.php         ← 歷史查詢
    ├── driver/                 ← 駕駛端（待開發）
    │   ├── login.php
    │   ├── dashboard.php
    │   ├── report.php          ← 回報里程與價格
    │   └── revenue.php         ← 營收彙整
    └── admin/                  ← 管理端（待開發）
        ├── login.php
        ├── dashboard.php       ← 所有行程清單
        └── report.php          ← 帳務報表
```

---

## 資料庫設計

資料庫名稱：`my-db`

| 資料表 | 主鍵 | 說明 |
|--------|------|------|
| `Passengers` | PassengerID | 乘客登入資訊 |
| `Drivers` | DriverID | 駕駛登入資訊 |
| `Rides` | RideID | 行程紀錄 |
| `Settlements` | RideID (FK) | 結算明細 |

### 行程狀態流程

```
乘客預約
   ↓
[預約中] — 駕駛接單，尚未抵達目的地
   ↓
駕駛輸入里程與價格
   ↓
[待確認] — 等待乘客確認帳務
   ↓
乘客點擊確認
   ↓
[已完成] — 正式結案，計入駕駛營收
```

---

## 功能需求清單

### A. 乘客端

| 功能 | 狀態 |
|------|------|
| 建立帳號（姓名、密碼） | ⬜ 待開發 |
| 登入 | ⬜ 待開發 |
| 行程預約（起點、終點、預定時間） | ⬜ 待開發 |
| 結單確認（確認里程與價格） | ⬜ 待開發 |
| 歷史查詢 | ⬜ 待開發 |

### B. 駕駛端

| 功能 | 狀態 |
|------|------|
| 登入 | ⬜ 待開發 |
| 查看指派行程（預約中／待確認／已完成） | ⬜ 待開發 |
| 回報實際里程與最終價格 | ⬜ 待開發 |
| 營收彙整（總行程數、累計金額） | ⬜ 待開發 |

### C. 平台管理端

| 功能 | 狀態 |
|------|------|
| 登入 | ⬜ 待開發 |
| 所有行程清單監控 | ⬜ 待開發 |
| 帳務報表（客人、司機、里程、確認狀態） | ⬜ 待開發 |

---

## 已完成進度

- ✅ Docker 環境建立（MariaDB + PHP/Apache + phpMyAdmin）
- ✅ 資料庫 `my-db` 建立
- ✅ 四張資料表建立（Passengers、Drivers、Rides、Settlements）

---

## 當前目標

**開發乘客端**，依序完成：

1. `src/db.php` — 資料庫連線設定
2. `src/passenger/register.php` — 建立帳號
3. `src/passenger/login.php` — 登入
4. `src/passenger/dashboard.php` — 登入後主頁
5. `src/passenger/book.php` — 行程預約
6. `src/passenger/confirm.php` — 結單確認
7. `src/passenger/history.php` — 歷史查詢

---

## 非功能性需求

- 每筆資料存取回應時間需在 **1 秒內**
- 介面設計應直覺，單筆行程錄入與確認時間極短
- 駕駛輸入里程與價格後，客戶端須即時顯示「待確認」狀態
- 地點以**純文字欄位**紀錄，不使用 Google Maps API
