# phpIPAM Health Dashboard

[![phpIPAM](https://img.shields.io/badge/phpIPAM-v1.7.4-blue)](https://phpipam.net/)
[![Docker](https://img.shields.io/badge/Docker-Ready-brightgreen)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

為 phpIPAM 提供完整的健康檢查監控 Dashboard，包含 24 小時歷史統計、DHCP 伺服器動態管理、多語系介面。

![Dashboard Screenshot](docs/dashboard-screenshot.png)

## ✨ 功能特色

| 功能 | 說明 |
|------|------|
| 🌙 Light/Dark Mode | 深色/淺色主題切換，自動記憶偏好 |
| 🌐 多語系支援 | English / 简体中文 / 繁體中文 |
| 📊 即時曲線圖 | 系統資源 (CPU/Mem/Disk) 及 DHCP 延遲趨勢 |
| ⏱️ 彈性時段查詢 | 固定時段 (1h/3h/6h/8h/12h/24h) 或自訂時間範圍 |
| ⚙️ DHCP 管理 UI | 視覺化新增、編輯、刪除 DHCP 伺服器 |
| 🔄 自動更新 | 每 60 秒自動重新整理 |
| 📈 歷史統計 | avg / min / max 歷史數據 |

## 🚀 快速部署

### 方式一：Docker Compose 一鍵部署

```bash
# 1. 複製專案
git clone https://github.com/nox913106/DEV-phpipam.git
cd DEV-phpipam/docker

# 2. 配置環境變數
cp .env.example .env
vi .env  # 設定資料庫密碼

# 3. 啟動服務
docker-compose up -d

# 4. 設定健康檢查 Cron (容器內)
docker exec phpipam-cron sh -c \
  'echo "*/5 * * * * php /health_check/scripts/collect_stats.php >> /var/log/health_check.log 2>&1" >> /etc/crontabs/root'
```

### 方式二：整合至現有 phpIPAM

請參考 [DEPLOYMENT.md](DEPLOYMENT.md)

## 📁 專案結構

```
dev-phpipam/
├── dashboard/
│   └── index.html              # Dashboard 主頁
├── api/
│   ├── api_stats.php           # 統計 API
│   └── api_dhcp_config.php     # DHCP 配置 API
├── includes/
│   ├── HistoryCollector.php    # 歷史資料收集器
│   └── StatsCalculator.php     # 統計計算器
├── scripts/
│   ├── collect_stats.php           # Cron 排程腳本（系統資源）
│   ├── dhcp_monitor_daemon.php     # DHCP 監控 Daemon（每 5 秒）
│   ├── start_dhcp_monitor.sh       # Daemon 啟動腳本
│   └── entrypoint_wrapper.sh       # 容器啟動包裝腳本
├── config/
│   └── dhcp_servers.json           # DHCP 伺服器配置
├── database/
│   └── health_check_tables.sql     # 資料庫結構
├── docker/                     # Docker 一鍵部署包
│   ├── docker-compose.yml
│   ├── .env.example
│   └── health_dashboard/
└── HEALTH_CHECK_MANUAL.html    # 完整說明書
```

## 📡 API 端點

### 統計 API

```bash
# 取得最新狀態
curl "https://YOUR_SERVER/health_dashboard/api/api_stats.php?action=latest"

# 取得系統歷史 (固定時段: 1/3/6/8/12/24 小時)
curl "https://YOUR_SERVER/health_dashboard/api/api_stats.php?action=system_history&hours=6"

# 取得系統歷史 (自訂時間範圍)
curl "https://YOUR_SERVER/health_dashboard/api/api_stats.php?action=system_history&start_time=2025-12-22%2000:00&end_time=2025-12-22%2005:00"

# 取得 DHCP 歷史 (固定時段)
curl "https://YOUR_SERVER/health_dashboard/api/api_stats.php?action=dhcp_history&hours=3"

# 取得 DHCP 歷史 (自訂時間範圍)
curl "https://YOUR_SERVER/health_dashboard/api/api_stats.php?action=dhcp_history&start_time=2025-12-22%2000:00&end_time=2025-12-22%2005:00"
```

**API 參數說明**:
| 參數 | 說明 | 預設值 |
|------|------|--------|
| `hours` | 固定時段查詢 (小時) | 24 |
| `start_time` | 自訂範圍開始時間 (Y-m-d H:i) | - |
| `end_time` | 自訂範圍結束時間 (Y-m-d H:i) | - |

### DHCP 配置 API

```bash
# 查詢所有 DHCP 伺服器
curl "https://YOUR_SERVER/health_dashboard/api/api_dhcp_config.php"

# 新增
curl -X POST -H "Content-Type: application/json" \
  -d '{"ip":"192.168.1.1","hostname":"DHCP-01","location":"總部"}' \
  "https://YOUR_SERVER/health_dashboard/api/api_dhcp_config.php"

# 修改
curl -X PUT -H "Content-Type: application/json" \
  -d '{"hostname":"Updated-Name"}' \
  "https://YOUR_SERVER/health_dashboard/api/api_dhcp_config.php?ip=192.168.1.1"

# 刪除
curl -X DELETE "https://YOUR_SERVER/health_dashboard/api/api_dhcp_config.php?ip=192.168.1.1"
```

## ⚙️ 配置

### DHCP 伺服器 (`config/dhcp_servers.json`)

```json
[
    {"ip": "192.168.1.1", "hostname": "DHCP-01", "location": "總部", "enabled": true},
    {"ip": "192.168.2.1", "hostname": "DHCP-02", "location": "分部", "enabled": true}
]
```

### 環境變數 (`.env`)

| 變數 | 說明 | 預設值 |
|------|------|--------|
| MYSQL_ROOT_PASSWORD | MariaDB root 密碼 | - |
| MYSQL_PASSWORD | phpIPAM 密碼 | - |
| TZ | 時區 | Asia/Taipei |
| WEB_PORT | Web 服務埠 | 80 |

## 🔧 維護

```bash
# 查看 Cron 日誌
docker exec phpipam-cron tail -f /var/log/health_check.log

# 手動執行資料收集
docker exec phpipam-cron php /health_check/scripts/collect_stats.php

# 刪除 DHCP 歷史資料
docker exec phpipam-mariadb mysql -u phpipam -p phpipam \
  -e "DELETE FROM health_check_dhcp_history WHERE dhcp_ip = '192.168.1.1'"
```

## 📖 文件

- [DEPLOYMENT.md](DEPLOYMENT.md) - 詳細部署步驟
- [HEALTH_CHECK_MANUAL.html](HEALTH_CHECK_MANUAL.html) - 完整說明書
- [docker/README.md](docker/README.md) - Docker 部署說明

## 🛡️ 安全性

- ✅ 使用 phpIPAM Token 認證
- ✅ 嚴格驗證所有輸入參數
- ✅ 限制系統指令白名單
- ✅ 記錄 API 呼叫日誌

## 📝 版本

- **v2.2** (2025-12-24)
  - 🚀 DHCP 監控間隔從 5 分鐘優化為 **5 秒**
  - 新增 `dhcp_monitor_daemon.php` 獨立監控服務
  - 記錄時間對齊到 :00/:05/:10... 模式
  - 新增容器自動啟動包裝腳本
  - 自動清理 7 天以上歷史資料

- **v2.1** (2025-12-23)
  - 新增彈性時段查詢功能
  - 支援固定時段選擇 (1h/3h/6h/8h/12h/24h)
  - 支援自訂時間範圍查詢 (start_time/end_time)
  - Dashboard UI 新增時段選擇器

- **v2.0** (2025-12-19)
  - Dashboard: 語系切換、Light/Dark Mode、DHCP 管理 UI
  - 24 小時歷史統計
  - Docker 一鍵部署包

- **v1.0** (2025-12-18)
  - 基本健康檢查 API

## 📄 License

MIT License

---

**Dashboard URL**: https://ipam-tw.pouchen.com/health_dashboard/
