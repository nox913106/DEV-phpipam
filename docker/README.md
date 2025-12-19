# phpIPAM Health Dashboard - Docker 一鍵部署包

## 📋 概述

這是 phpIPAM 健康檢查監控系統的 Docker Compose 一鍵部署包，包含：
- phpIPAM 主程式 (v1.7.4)
- 健康檢查 Dashboard（含 24 小時歷史統計）
- DHCP 伺服器動態管理 UI
- 自動資料收集 Cron Job

## 🚀 快速部署

### 1. 複製專案
```bash
git clone https://github.com/YOUR_USERNAME/phpipam-health-dashboard.git
cd phpipam-health-dashboard/docker
```

### 2. 修改配置
```bash
# 複製環境變數範本
cp .env.example .env

# 編輯配置
vi .env
```

### 3. 啟動服務
```bash
docker-compose up -d
```

### 4. 初始化資料庫
```bash
# 等待服務啟動完成後執行
docker-compose exec phpipam-web php /phpipam/health_dashboard/scripts/init_database.php
```

### 5. 訪問系統
- phpIPAM: http://YOUR_SERVER/
- Health Dashboard: http://YOUR_SERVER/health_dashboard/

## 📁 目錄結構

```
docker/
├── docker-compose.yml       # Docker Compose 配置
├── .env.example             # 環境變數範本
├── init/
│   └── health_check_tables.sql  # 資料表初始化 SQL
└── health_dashboard/
    ├── index.html           # Dashboard 主頁
    ├── api/
    │   ├── api_stats.php    # 統計 API
    │   └── api_dhcp_config.php  # DHCP 配置 API
    ├── config/
    │   └── dhcp_servers.json    # DHCP 伺服器配置
    ├── includes/
    │   ├── HistoryCollector.php
    │   └── StatsCalculator.php
    └── scripts/
        └── collect_stats.php    # 資料收集腳本
```

## ⚙️ 配置說明

### 環境變數 (.env)

| 變數 | 說明 | 預設值 |
|------|------|--------|
| MYSQL_ROOT_PASSWORD | MariaDB root 密碼 | - |
| MYSQL_DATABASE | 資料庫名稱 | phpipam |
| MYSQL_USER | 資料庫使用者 | phpipam |
| MYSQL_PASSWORD | 資料庫密碼 | - |
| TZ | 時區 | Asia/Taipei |

### DHCP 伺服器配置

編輯 `health_dashboard/config/dhcp_servers.json`:

```json
[
    {"ip": "192.168.1.1", "hostname": "DHCP-01", "location": "總部", "enabled": true},
    {"ip": "192.168.2.1", "hostname": "DHCP-02", "location": "分部", "enabled": true}
]
```

## 🔧 維護命令

```bash
# 查看日誌
docker-compose logs -f phpipam-cron

# 手動執行資料收集
docker-compose exec phpipam-cron php /health_check/scripts/collect_stats.php

# 同步 DHCP 配置
docker-compose exec phpipam-web cat /phpipam/health_dashboard/config/dhcp_servers.json > /tmp/dhcp.json
docker cp /tmp/dhcp.json $(docker-compose ps -q phpipam-cron):/health_check/config/dhcp_servers.json

# 刪除 DHCP 歷史資料
docker-compose exec mariadb mysql -u phpipam -p$MYSQL_PASSWORD phpipam \
  -e "DELETE FROM health_check_dhcp_history WHERE dhcp_ip = '要刪除的IP'"
```

## 📝 License

MIT License
