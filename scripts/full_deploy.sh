#!/bin/bash
#
# phpIPAM Health Monitor - Full Deployment Script
# 一鍵部署 Health Monitor (包含 Dashboard + phpIPAM Tools 整合)
#
# Usage: curl -sL https://raw.githubusercontent.com/nox913106/DEV-phpipam/master/scripts/full_deploy.sh | bash
#
# @author Jason Cheng
# @version 2.1
# @date 2025-12-23
#

set -e

GITHUB_RAW="https://raw.githubusercontent.com/nox913106/DEV-phpipam/master"
WEB_CONTAINER="phpipam_phpipam-web_1"
CRON_CONTAINER="phpipam_phpipam-cron_1"
DB_CONTAINER="phpipam_phpipam-mariadb_1"
DB_USER="phpipam"
DB_PASS="my_secret_phpipam_pass"
DB_NAME="phpipam"

echo "=============================================="
echo "  phpIPAM Health Monitor - Full Deployment"
echo "  Version: 2.1"
echo "  Time: $(date)"
echo "=============================================="
echo ""

# Check containers
echo "[CHECK] Verifying containers..."
for container in $WEB_CONTAINER $CRON_CONTAINER $DB_CONTAINER; do
    if ! docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
        echo "[ERROR] Container $container is not running!"
        exit 1
    fi
done
echo "[OK] All containers are running"
echo ""

# Create temp directory
TMPDIR=$(mktemp -d)
cd $TMPDIR
echo "[INFO] Working directory: $TMPDIR"
echo ""

# Step 1: Database tables
echo "[1/5] Creating database tables..."
curl -sL "$GITHUB_RAW/database/health_check_tables.sql" -o health_check_tables.sql
if [ -s health_check_tables.sql ]; then
    docker exec -i $DB_CONTAINER mariadb -u $DB_USER -p$DB_PASS $DB_NAME < health_check_tables.sql 2>/dev/null || true
    echo "[OK] Database tables ready"
else
    echo "[WARN] Could not download SQL file, tables may already exist"
fi
echo ""

# Step 2: Download all files
echo "[2/5] Downloading files from GitHub..."
curl -sL "$GITHUB_RAW/dashboard/index.html" -o index.html
curl -sL "$GITHUB_RAW/api/api_stats.php" -o api_stats.php
curl -sL "$GITHUB_RAW/api/api_dhcp_config.php" -o api_dhcp_config.php
curl -sL "$GITHUB_RAW/includes/StatsCalculator.php" -o StatsCalculator.php
curl -sL "$GITHUB_RAW/includes/HistoryCollector.php" -o HistoryCollector.php
curl -sL "$GITHUB_RAW/config/dhcp_servers.json" -o dhcp_servers.json
curl -sL "$GITHUB_RAW/scripts/collect_stats.php" -o collect_stats.php
echo "[OK] Files downloaded"
echo ""

# Step 3: Deploy to Web container
echo "[3/5] Deploying to Web container..."
docker exec $WEB_CONTAINER mkdir -p /phpipam/health_dashboard/api
docker exec $WEB_CONTAINER mkdir -p /phpipam/health_dashboard/config
docker exec $WEB_CONTAINER mkdir -p /phpipam/health_dashboard/includes

docker cp index.html $WEB_CONTAINER:/phpipam/health_dashboard/
docker cp api_stats.php $WEB_CONTAINER:/phpipam/health_dashboard/api/
docker cp api_dhcp_config.php $WEB_CONTAINER:/phpipam/health_dashboard/api/
docker cp StatsCalculator.php $WEB_CONTAINER:/phpipam/health_dashboard/includes/
docker cp HistoryCollector.php $WEB_CONTAINER:/phpipam/health_dashboard/includes/
docker cp dhcp_servers.json $WEB_CONTAINER:/phpipam/health_dashboard/config/
echo "[OK] Web container updated"
echo ""

# Step 4: Deploy to Cron container
echo "[4/5] Deploying to Cron container..."
docker exec $CRON_CONTAINER mkdir -p /health_check/scripts
docker exec $CRON_CONTAINER mkdir -p /health_check/includes
docker exec $CRON_CONTAINER mkdir -p /health_check/config

docker cp collect_stats.php $CRON_CONTAINER:/health_check/scripts/
docker cp StatsCalculator.php $CRON_CONTAINER:/health_check/includes/
docker cp HistoryCollector.php $CRON_CONTAINER:/health_check/includes/
docker cp dhcp_servers.json $CRON_CONTAINER:/health_check/config/

# Setup cron job if not exists
if ! docker exec $CRON_CONTAINER cat /etc/crontabs/root 2>/dev/null | grep -q "health_check"; then
    docker exec $CRON_CONTAINER sh -c 'echo "*/5 * * * * php /health_check/scripts/collect_stats.php >> /var/log/health_check.log 2>&1" >> /etc/crontabs/root'
    echo "[OK] Cron job created"
else
    echo "[OK] Cron job already exists"
fi
echo ""

# Step 5: phpIPAM Tools Integration
echo "[5/5] Integrating with phpIPAM Tools..."

# Create health-monitor tool
docker exec $WEB_CONTAINER mkdir -p /phpipam/app/tools/health-monitor
docker exec $WEB_CONTAINER sh -c 'cat > /phpipam/app/tools/health-monitor/index.php << '\''EOFPHP'\''
<?php
$User->check_user_session();
?>
<h4><i class="fa fa-heartbeat"></i> Health Monitor Dashboard</h4>
<hr>
<div style="width:100%; height:calc(100vh - 180px); min-height:600px;">
    <iframe src="/health_dashboard/" style="width:100%; height:100%; border:none; border-radius:8px;"></iframe>
</div>
EOFPHP'

# Add to config.php if not exists
if ! docker exec $WEB_CONTAINER grep -q "private_subpages" /phpipam/config.php 2>/dev/null; then
    docker exec $WEB_CONTAINER sh -c 'echo "" >> /phpipam/config.php'
    docker exec $WEB_CONTAINER sh -c 'echo "// Custom tools" >> /phpipam/config.php'
    docker exec $WEB_CONTAINER sh -c "echo '\$private_subpages = [\"health-monitor\"];' >> /phpipam/config.php"
    echo "[OK] Added private_subpages to config.php"
else
    echo "[OK] private_subpages already exists"
fi

# Add to tools_menu_items if not exists
if ! docker exec $WEB_CONTAINER grep -q "health-monitor" /phpipam/app/tools/tools-menu-config.php 2>/dev/null; then
    docker exec $WEB_CONTAINER sed -i '/"vaults".*=>/a\                                                "health-monitor"       => _("health-monitor"),' /phpipam/app/tools/tools-menu-config.php
    echo "[OK] Added health-monitor to tools menu"
else
    echo "[OK] health-monitor already in tools menu"
fi

# Verify PHP syntax
docker exec $WEB_CONTAINER php -l /phpipam/app/tools/tools-menu-config.php > /dev/null 2>&1
echo "[OK] PHP syntax verified"
echo ""

# Cleanup
rm -rf $TMPDIR

# Summary
echo "=============================================="
echo "  Deployment Complete!"
echo "=============================================="
echo ""
echo "Access URLs:"
echo "  Dashboard: https://YOUR_SERVER/health_dashboard/"
echo "  phpIPAM:   https://YOUR_SERVER/index.php?page=tools&section=health-monitor"
echo ""
echo "Verification commands:"
echo "  curl -sk 'https://YOUR_SERVER/health_dashboard/api/api_stats.php?action=latest'"
echo "  docker exec $CRON_CONTAINER php /health_check/scripts/collect_stats.php"
echo ""
