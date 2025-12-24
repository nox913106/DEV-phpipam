#!/bin/sh
#
# entrypoint_wrapper.sh
#
# Docker 容器啟動包裝腳本
# 在啟動 phpIPAM cron 服務前，先啟動 DHCP 監控 daemon
#

# 啟動 DHCP 監控 daemon（背景執行）
if [ -f "/health_check/scripts/dhcp_monitor_daemon.php" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting DHCP Monitor Daemon..."
    nohup php /health_check/scripts/dhcp_monitor_daemon.php >> /var/log/dhcp_monitor.log 2>&1 &
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] DHCP Monitor Daemon started (PID: $!)"
fi

# 執行原本的 entrypoint（phpIPAM cron 服務）
exec "$@"
