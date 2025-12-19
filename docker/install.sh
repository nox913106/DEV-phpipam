#!/bin/bash
# phpIPAM Health Dashboard - 一鍵安裝腳本

set -e

echo "=========================================="
echo "phpIPAM Health Dashboard 一鍵部署"
echo "=========================================="

# 檢查 Docker 是否安裝
if ! command -v docker &> /dev/null; then
    echo "[ERROR] Docker 未安裝，請先安裝 Docker"
    exit 1
fi

# 檢查 docker-compose 是否安裝
if ! command -v docker-compose &> /dev/null; then
    echo "[ERROR] docker-compose 未安裝，請先安裝 docker-compose"
    exit 1
fi

# 切換到腳本目錄
cd "$(dirname "$0")"

# 檢查 .env 檔案
if [ ! -f .env ]; then
    echo "[INFO] 建立 .env 配置檔案..."
    cp .env.example .env
    echo "[WARN] 請編輯 .env 檔案設定密碼後重新執行此腳本"
    echo "       vi .env"
    exit 1
fi

# 檢查密碼是否已設定
source .env
if [ "$MYSQL_ROOT_PASSWORD" = "your_root_password_here" ] || [ "$MYSQL_PASSWORD" = "your_phpipam_password_here" ]; then
    echo "[ERROR] 請先修改 .env 中的密碼設定"
    exit 1
fi

echo "[1/4] 啟動 Docker 服務..."
docker-compose up -d

echo "[2/4] 等待 MariaDB 啟動 (30秒)..."
sleep 30

echo "[3/4] 檢查服務狀態..."
docker-compose ps

echo "[4/4] 初始化健康檢查 Cron..."
docker-compose exec phpipam-cron sh -c 'echo "*/5 * * * * php /health_check/scripts/collect_stats.php >> /var/log/health_check.log 2>&1" >> /etc/crontabs/root'

echo ""
echo "=========================================="
echo "✅ 部署完成！"
echo "=========================================="
echo ""
echo "📌 phpIPAM:           http://$(hostname -I | awk '{print $1}')/"
echo "📌 Health Dashboard:  http://$(hostname -I | awk '{print $1}')/health_dashboard/"
echo ""
echo "⚠️  首次使用請訪問 phpIPAM 完成初始化設定"
echo ""
