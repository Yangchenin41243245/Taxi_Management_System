# 三端簡易叫車紀錄管理系統

## 專案概述

透過客戶、駕駛與平台三端的配合，手動紀錄行程資訊，並透過乘客確認機制確保帳務真實性，取代傳統紙本派車單。

- **適用對象**：乘客、接單司機、車隊管理人員
- **技術架構**：PHP 8.2 + MariaDB 10.6 + Apache（Docker）
- **本機開發**：`http://localhost:8080`
- **資料庫管理**：phpMyAdmin `http://localhost:8082`（帳號：root／密碼：myPassword）

---

## 快速啟動（本機）

```powershell
cd C:\dev\docker
docker compose up -d
```

關閉：

```powershell
docker compose down
```

---

## 在其他電腦上開啟

### 前置需求

- 安裝 [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- 安裝 [Git](https://git-scm.com/)

### 步驟

**1. 取得專案**

```powershell
git clone <repository-url>
cd docker
```

**2. 啟動容器**

```powershell
docker compose up -d
```

**3. 建立資料庫結構**

開啟瀏覽器前往 `http://localhost:8082`，以 root / myPassword 登入 phpMyAdmin，
在 `my-db` 資料庫執行以下 SQL：

```sql
CREATE TABLE Passengers (
  PassengerID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  乘客姓名 VARCHAR(50) NOT NULL,
  密碼 VARCHAR(255) NOT NULL
);

CREATE TABLE Drivers (
  DriverID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  駕駛姓名 VARCHAR(50) NOT NULL,
  密碼 VARCHAR(255) NOT NULL
);

CREATE TABLE Admins (
  AdminID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  帳號 VARCHAR(50) NOT NULL UNIQUE,
  密碼 VARCHAR(255) NOT NULL
);

CREATE TABLE Rides (
  RideID INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  PassengerID INT(11) NOT NULL,
  DriverID INT(11) DEFAULT NULL,
  起點 VARCHAR(255) NOT NULL,
  終點 VARCHAR(255) NOT NULL,
  預計乘車時間 DATETIME NOT NULL,
  預約建立時間 DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (PassengerID) REFERENCES Passengers(PassengerID),
  FOREIGN KEY (DriverID) REFERENCES Drivers(DriverID)
);

CREATE TABLE Settlements (
  RideID INT(11) NOT NULL PRIMARY KEY,
  實際里程 DECIMAL(6,2) DEFAULT NULL,
  最終價格 DECIMAL(8,2) DEFAULT NULL,
  確認狀態 ENUM('待確認','已確認') DEFAULT '待確認',
  完成確認時間 DATETIME DEFAULT NULL,
  FOREIGN KEY (RideID) REFERENCES Rides(RideID)
);
```

**4. 開始使用**

| 端口 | 網址 |
|------|------|
| 乘客端 | `http://localhost:8080/passenger/register.php` |
| 駕駛端 | `http://localhost:8080/driver/register.php` |
| 管理端 | `http://localhost:8080/admin/register.php` |

### 同網段手機存取

1. 查詢電腦 IP（Windows：執行 `ipconfig`，找無線網路的 IPv4）
2. 手機與電腦連同一個 Wi-Fi 或熱點
3. 手機瀏覽器輸入 `http://<電腦IP>:8080/passenger/register.php`

---

## 專案目錄結構

```
docker/
├── .gitignore
├── docker-compose.yml
├── Dockerfile
└── src/
    ├── db.php                      ← 資料庫連線（共用）
    ├── passenger/                  ← 乘客端
    │   ├── register.php            ← 建立帳號
    │   ├── login.php               ← 登入
    │   ├── logout.php              ← 登出
    │   ├── dashboard.php           ← 主頁（最近行程）
    │   ├── book.php                ← 行程預約
    │   ├── confirm.php             ← 結單確認
    │   └── history.php             ← 歷史查詢
    ├── driver/                     ← 駕駛端
    │   ├── register.php            ← 建立帳號
    │   ├── login.php               ← 登入
    │   ├── logout.php              ← 登出
    │   ├── dashboard.php           ← 主頁（行程概覽）
    │   ├── report.php              ← 回報里程與價格
    │   └── revenue.php             ← 營收彙整
    └── admin/                      ← 管理端
        ├── register.php            ← 建立帳號
        ├── login.php               ← 登入
        ├── logout.php              ← 登出
        ├── dashboard.php           ← 所有行程清單
        ├── assign.php              ← 指派駕駛
        └── report.php              ← 帳務報表
```

---

## 資料庫設計

資料庫名稱：`my-db`

| 資料表 | 主鍵 | 欄位 | 說明 |
|--------|------|------|------|
| `Passengers` | PassengerID | 乘客姓名、密碼 | 乘客帳號 |
| `Drivers` | DriverID | 駕駛姓名、密碼 | 駕駛帳號 |
| `Admins` | AdminID | 帳號、密碼 | 管理員帳號 |
| `Rides` | RideID | PassengerID、DriverID、起點、終點、預計乘車時間 | 行程紀錄 |
| `Settlements` | RideID (FK) | 實際里程、最終價格、確認狀態、完成確認時間 | 結算明細 |

### 行程狀態流程

```
乘客預約
   ↓
[預約中] — 等待管理端指派駕駛
   ↓
管理端指派駕駛
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

## 功能清單

### A. 乘客端

| 功能 | 狀態 |
|------|------|
| 建立帳號 | ✅ 完成 |
| 登入／登出 | ✅ 完成 |
| 行程預約（起點、終點、預定時間） | ✅ 完成 |
| 結單確認（確認里程與價格） | ✅ 完成 |
| 歷史查詢（含狀態篩選） | ✅ 完成 |

### B. 駕駛端

| 功能 | 狀態 |
|------|------|
| 建立帳號 | ✅ 完成 |
| 登入／登出 | ✅ 完成 |
| 查看指派行程（預約中／待確認／已完成） | ✅ 完成 |
| 回報實際里程與最終價格 | ✅ 完成 |
| 營收彙整（總行程數、累計金額） | ✅ 完成 |

### C. 平台管理端

| 功能 | 狀態 |
|------|------|
| 建立帳號 | ✅ 完成 |
| 登入／登出 | ✅ 完成 |
| 指派駕駛至行程 | ✅ 完成 |
| 所有行程清單監控（含狀態篩選） | ✅ 完成 |
| 帳務報表（乘客消費、駕駛營收、明細） | ✅ 完成 |

---

## 非功能性需求

- 每筆資料存取回應時間需在 **1 秒內**
- 介面支援手機瀏覽（RWD，底部導覽列）
- 介面設計直覺，單筆行程錄入與確認時間極短
- 地點以**純文字欄位**紀錄，不使用 Google Maps API
- 密碼以 **bcrypt** 雜湊儲存，不存明文
