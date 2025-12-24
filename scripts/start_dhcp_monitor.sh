#!/bin/bash
#
# start_dhcp_monitor.sh
# 
# 啟動 DHCP 監控 Daemon
# 用於 Docker 容器內或手動啟動
#

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DAEMON_SCRIPT="$SCRIPT_DIR/dhcp_monitor_daemon.php"
PID_FILE="/var/run/dhcp_monitor.pid"
LOG_FILE="/var/log/dhcp_monitor.log"

start() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if kill -0 "$PID" 2>/dev/null; then
            echo "DHCP Monitor already running (PID: $PID)"
            return 1
        fi
        rm -f "$PID_FILE"
    fi
    
    echo "Starting DHCP Monitor Daemon..."
    nohup php "$DAEMON_SCRIPT" >> "$LOG_FILE" 2>&1 &
    echo $! > "$PID_FILE"
    echo "Started with PID: $(cat $PID_FILE)"
}

stop() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        echo "Stopping DHCP Monitor (PID: $PID)..."
        kill "$PID" 2>/dev/null
        rm -f "$PID_FILE"
        echo "Stopped"
    else
        echo "DHCP Monitor not running"
    fi
}

status() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if kill -0 "$PID" 2>/dev/null; then
            echo "DHCP Monitor is running (PID: $PID)"
            return 0
        fi
    fi
    echo "DHCP Monitor is not running"
    return 1
}

case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 2
        start
        ;;
    status)
        status
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
