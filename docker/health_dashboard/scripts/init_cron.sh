#!/bin/bash
# phpIPAM Health Dashboard - Cron 初始化腳本
# 此腳本會在 Cron 容器啟動時被呼叫

# 設定健康檢查 Cron Job (每 5 分鐘執行一次)
echo "*/5 * * * * php /health_check/scripts/collect_stats.php >> /var/log/health_check.log 2>&1" >> /etc/crontabs/root

# 建立日誌檔案
touch /var/log/health_check.log
chmod 666 /var/log/health_check.log

echo "[Health Check] Cron job initialized"
