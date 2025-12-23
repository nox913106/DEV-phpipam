# phpIPAM 健康檢查功能部署紀錄

**部署日期**: 2025-12-19  
**部署目標**: Docker phpIPAM 環境  
**功能**: 24 小時歷史統計 (CPU/記憶體/磁碟/DHCP)

---

## 部署步驟

### Step 1: 環境確認
- [ ] 確認 phpIPAM Docker 容器狀態
- [ ] 確認 MariaDB 容器狀態
- [ ] 確認掛載目錄

**執行時間**: 待記錄  
**執行結果**: 待記錄

---

### Step 2: 建立資料表
- [ ] 連線到 MariaDB 容器
- [ ] 執行 health_check_tables.sql

**執行時間**: 待記錄  
**執行結果**: 待記錄

---

### Step 3: 上傳程式檔案
- [ ] 複製 includes/ 目錄
- [ ] 複製 scripts/ 目錄
- [ ] 複製 api/ 目錄
- [ ] 設定檔案權限

**執行時間**: 待記錄  
**執行結果**: 待記錄

---

### Step 4: 設定 Cron 排程
- [ ] 在容器內設定 cron job
- [ ] 驗證 cron 執行

**執行時間**: 待記錄  
**執行結果**: 待記錄

---

### Step 5: 驗證部署
- [ ] 測試 API 回應
- [ ] 確認資料收集功能

**執行時間**: 待記錄  
**執行結果**: 待記錄

---

## 部署紀錄

### 環境資訊
```
伺服器: stwphpipam-p
phpIPAM Web 容器: phpipam_phpipam-web_1 (phpipam/phpipam-www:v1.7.4)
phpIPAM Cron 容器: phpipam_phpipam-cron_1 (phpipam/phpipam-cron:v1.7.4)
MariaDB 容器: phpipam_phpipam-mariadb_1 (mariadb:latest, port 3306)
Nginx 容器: nginx_nginx_1 (反向代理)
```

### 執行紀錄

#### [08:19] Step 1 - 環境確認
```bash
root@stwphpipam-p:/home/chadmin# docker ps | grep -i ipam
5193192bcf13   phpipam/phpipam-cron:v1.7.4   ... Up 2 days   phpipam_phpipam-cron_1
e4d1b2afde17   phpipam/phpipam-www:v1.7.4    ... Up 2 days   phpipam_phpipam-web_1
bb79db9903d1   mariadb:latest                ... Up 2 days   phpipam_phpipam-mariadb_1
```
✅ 所有容器運行正常

**掛載目錄**:
```
/var/lib/docker/volumes/phpipam_phpipam-ca/_data -> /usr/local/share/ca-certificates
/var/lib/docker/volumes/phpipam_phpipam-logo/_data -> /phpipam/css/images/logo
```

**Docker Compose**: `/opt/phpipam/docker-compose.yml`
**資料庫主機**: phpipam-mariadb (容器內部網路)
**資料庫密碼**: my_secret_phpipam_pass

---

#### [08:28] Step 2 - 建立資料表
```bash
# 建立 SQL 檔案並複製到容器
docker cp /tmp/health_check_tables.sql phpipam_phpipam-mariadb_1:/tmp/
Successfully copied 3.07kB to phpipam_phpipam-mariadb_1:/tmp/

# 執行 SQL
docker exec -i phpipam_phpipam-mariadb_1 mariadb -u phpipam -pmy_secret_phpipam_pass phpipam < /tmp/health_check_tables.sql

# 驗證
docker exec -i phpipam_phpipam-mariadb_1 mariadb -u phpipam -pmy_secret_phpipam_pass phpipam -e "SHOW TABLES LIKE 'health_check%';"
Tables_in_phpipam (health_check%)
health_check_dhcp_history
health_check_system_history
```
✅ 資料表建立成功

---

#### [08:30] Step 3 - 上傳程式檔案
```bash
# 在主機建立暫存目錄
mkdir -p /tmp/health_check/{includes,scripts,api}

# 建立 PHP 檔案
# - StatsCalculator.php (5175 bytes)
# - HistoryCollector.php (4667 bytes)
# - collect_stats.php (1969 bytes)

# 複製到容器
docker cp /tmp/health_check phpipam_phpipam-cron_1:/
Successfully copied 17.4kB to phpipam_phpipam-cron_1:/
```
✅ 檔案上傳成功

---

#### [08:34] Step 4 - 測試腳本
```bash
docker exec phpipam_phpipam-cron_1 php /health_check/scripts/collect_stats.php

=== Health Check Collector ===
Time: 2025-12-19 08:34:33
[OK] DB connected
[OK] System: CPU=0.05%, Mem=16.5%, Disk=59.15%
[OK] DHCP: 6 servers checked
     - 172.16.5.196: Online (0.287ms)
     - 172.23.13.10: Online (27.631ms)
     - 172.23.174.5: Online (8.269ms)
     - 172.23.199.150: Online (4.161ms)
     - 172.23.110.1: Online (6.796ms)
     - 172.23.94.254: Online (39.825ms)
Done in 18176.25ms
```
✅ 腳本執行成功，資料已寫入資料庫

---

#### [08:37] Step 5 - 設定 Cron Job
```bash
docker exec phpipam_phpipam-cron_1 sh -c 'echo "*/5 * * * * php /health_check/scripts/collect_stats.php >> /var/log/health_check.log 2>&1" >> /etc/crontabs/root'
```
✅ Cron 已設定：每 5 分鐘執行一次

---

#### [11:08] Step 6 - 部署監控 Dashboard
```bash
# 建立 API 端點和 Dashboard HTML
mkdir -p /tmp/health_check/dashboard
# 建立 api_stats.php 和 index.html

# 複製到 web 容器
docker cp /tmp/health_check phpipam_phpipam-web_1:/
Successfully copied 28.2kB to phpipam_phpipam-web_1:/

# 複製到 phpIPAM web root
docker exec phpipam_phpipam-web_1 cp -r /health_check/dashboard /phpipam/health_dashboard
docker exec phpipam_phpipam-web_1 cp -r /health_check/api /phpipam/health_dashboard/
docker exec phpipam_phpipam-web_1 cp -r /health_check/includes /phpipam/health_dashboard/
```
✅ Dashboard 部署成功

**Dashboard URL**: https://ipam-tw.pouchen.com/health_dashboard/

---

## 部署結果

| 項目 | 狀態 | 說明 |
|------|------|------|
| 資料表 | ✅ | `health_check_system_history`, `health_check_dhcp_history` |
| 資料收集 | ✅ | Cron job 每 5 分鐘執行 |
| API 端點 | ✅ | `/health_dashboard/api/api_stats.php` |
| Dashboard | ✅ | 曲線圖正常顯示 |

---

**部署完成時間**: 2025-12-19 11:11

---

## v2.1 更新紀錄 (2025-12-23)

### 新增功能：彈性時段查詢

#### API 更新
- 新增 `start_time` 和 `end_time` 參數支援自訂時間範圍查詢
- 更新 `getSystemHistory()` 和 `getDhcpHistory()` 函數

**API 範例**:
```bash
# 固定時段 (3 小時)
?action=system_history&hours=3

# 自訂時間範圍
?action=system_history&start_time=2025-12-22 00:00&end_time=2025-12-22 05:00
```

#### Dashboard 更新
- 新增時段選擇器 (1h/3h/6h/8h/12h/24h + 自訂範圍)
- 新增自訂時間範圍選擇器
- 新增 `changePeriod()` 和 `applyCustomRange()` JavaScript 函數
- 完整多語系支援 (EN/簡中/繁中)

#### 更新步驟
```bash
# 複製更新的檔案到容器
docker cp api_stats.php phpipam_phpipam-web_1:/phpipam/health_dashboard/api/
docker cp index.html phpipam_phpipam-web_1:/phpipam/health_dashboard/

# 驗證
curl -sk "https://ipam-tw.pouchen.com/health_dashboard/api/api_stats.php?action=system_history&hours=3"
```

**更新時間**: 2025-12-23 11:50

---

### phpIPAM Tools 整合 (2025-12-23 13:20)

#### 整合步驟
1. 建立工具目錄 `/phpipam/app/tools/health-monitor/`
2. 建立 index.php (iframe 嵌入 Dashboard)
3. 添加 `$private_subpages = ["health-monitor"];` 到 config.php
4. 添加 `"health-monitor"` 到 tools-menu-config.php 的 $tools_menu_items

#### 存取方式
- phpIPAM 選單：**Tools → Custom Tools → Health-monitor**
- 直接 URL：`https://ipam-tw.pouchen.com/index.php?page=tools&section=health-monitor`

#### 持久化腳本
```bash
# 容器重啟後執行恢復設定
./scripts/setup_phpipam_integration.sh
```

**整合時間**: 2025-12-23 13:20

