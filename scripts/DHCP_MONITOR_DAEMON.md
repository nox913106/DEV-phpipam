# DHCP 監控 Daemon 部署說明

## 功能說明

`dhcp_monitor_daemon.php` (v2.2.1) 是一個持續運行的 PHP daemon，每 **5 秒** 執行一次 DHCP 伺服器 ping 測試。

### 主要特點

- ⏱️ 每 5 秒監控一次
- 🕐 記錄時間對齊到 :00/:05/:10... 模式
- 📊 正確解析 ping 延遲（0.2ms - 15ms）
- 🔄 自動重新載入 DHCP 伺服器設定檔
- 🗑️ 自動清理 7 天以上的舊資料
- 📊 與現有 Health Dashboard 完全相容

---

## 部署步驟

### 方式一：手動啟動

```bash
# 進入 Cron 容器
docker exec -it phpipam_phpipam-cron_1 sh

# 啟動 daemon（背景執行）
nohup php /health_check/scripts/dhcp_monitor_daemon.php > /var/log/dhcp_monitor.log 2>&1 &

# 查看日誌
tail -f /var/log/dhcp_monitor.log
```

### 方式二：容器自動啟動（推薦）

修改 `/opt/phpipam/docker-compose.yml`，在 phpipam-cron 服務加入：

```yaml
phpipam-cron:
  image: phpipam/phpipam-cron:v1.7.4
  # ... 其他設定不變 ...
  command: >
    sh -c "
      if [ -f /health_check/scripts/dhcp_monitor_daemon.php ]; then
        nohup php /health_check/scripts/dhcp_monitor_daemon.php >> /var/log/dhcp_monitor.log 2>&1 &
      fi
      exec /sbin/tini -- /bin/sh -c 'crond -f'
    "
```

或使用包裝腳本：

```bash
# 複製包裝腳本到容器
docker cp entrypoint_wrapper.sh phpipam_phpipam-cron_1:/health_check/scripts/
docker exec phpipam_phpipam-cron_1 chmod +x /health_check/scripts/entrypoint_wrapper.sh

# 修改 docker-compose.yml 使用包裝腳本
# entrypoint: ["/health_check/scripts/entrypoint_wrapper.sh"]
```

---

## 資料庫影響

| 時間      | 每 5 分鐘 (原) | 每 5 秒 (新) | 增長倍數 |
|-----------|---------------|--------------|---------|
| 每小時    | 12 筆         | 720 筆       | 60x     |
| 每天      | 288 筆        | 17,280 筆    | 60x     |
| 每週      | 2,016 筆      | 120,960 筆   | 60x     |

### 資料清理策略

Daemon 內建自動清理功能：
- 預設保留 7 天資料
- 約每 8 分鐘執行一次清理
- 可修改 `cleanup_old_data($pdo, $days)` 的參數調整

---

## 監控與維護

### 確認 daemon 運行中

```bash
# 查看程序
docker exec phpipam_phpipam-cron_1 ps aux | grep dhcp_monitor

# 查看日誌
docker exec phpipam_phpipam-cron_1 tail -50 /var/log/dhcp_monitor.log
```

### 查看最新資料

```bash
docker exec phpipam_phpipam-mariadb_1 mariadb -u phpipam -p phpipam -e \
  "SELECT * FROM health_check_dhcp_history ORDER BY recorded_at DESC LIMIT 10;"
```

### 查看資料量

```bash
docker exec phpipam_phpipam-mariadb_1 mariadb -u phpipam -p phpipam -e \
  "SELECT COUNT(*) as total, 
          MIN(recorded_at) as oldest, 
          MAX(recorded_at) as newest 
   FROM health_check_dhcp_history;"
```

---

## 重啟 Daemon

```bash
# 停止
docker exec phpipam_phpipam-cron_1 pkill -9 -f dhcp_monitor

# 啟動
docker exec phpipam_phpipam-cron_1 sh -c "nohup php /health_check/scripts/dhcp_monitor_daemon.php > /var/log/dhcp_monitor.log 2>&1 &"

# 確認
docker exec phpipam_phpipam-cron_1 ps aux | grep dhcp_monitor
```
