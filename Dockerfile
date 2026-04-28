# 1. 使用官方 PHP 8.2 Apache 版本作為基礎鏡像
FROM php:8.2-apache

# 2. 更新系統軟體源並安裝必要套件
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    && rm -rf /var/lib/apt/lists/*

# 3. 安裝 PHP 擴展 (連線資料庫必備)
# mysqli 是傳統寫法，pdo_mysql 是現代物件導向寫法，建議兩者都裝
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 4. 啟用 Apache 的 Rewrite 模組 (如果你有使用 .htaccess 或路由框架時需要)
RUN a2enmod rewrite

# 5. 設定工作目錄
WORKDIR /var/www/html

# 6. 修改權限，確保 Apache 有權限讀取掛載的檔案
RUN chown -R www-data:www-data /var/www/html