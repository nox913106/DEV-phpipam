#!/bin/bash
#
# phpIPAM Health Monitor Integration Setup Script
# 用於容器重啟後恢復 Health Monitor 設定
#
# Usage: ./setup_phpipam_integration.sh
#
# @author Jason Cheng
# @version 2.1
# @date 2025-12-23
#

set -e

echo "=== phpIPAM Health Monitor Integration Setup ==="
echo "Time: $(date)"
echo ""

# 容器名稱
WEB_CONTAINER="phpipam_phpipam-web_1"

# 檢查容器是否運行
if ! docker ps | grep -q "$WEB_CONTAINER"; then
    echo "[ERROR] Container $WEB_CONTAINER is not running"
    exit 1
fi

echo "[1/4] Creating health-monitor tool directory..."
docker exec $WEB_CONTAINER mkdir -p /phpipam/app/tools/health-monitor

echo "[2/4] Creating health-monitor index.php..."
docker exec $WEB_CONTAINER sh -c 'cat > /phpipam/app/tools/health-monitor/index.php << '\''EOFPHP'\''
<?php
/**
 * Health Monitor Tool
 */

# verify that user is logged in
$User->check_user_session();
?>

<h4><i class="fa fa-heartbeat"></i> Health Monitor Dashboard</h4>
<hr>
<div style="width:100%; height:calc(100vh - 180px); min-height:600px;">
    <iframe src="/health_dashboard/" 
            style="width:100%; height:100%; border:none; border-radius:8px;"
            title="Health Monitor Dashboard">
    </iframe>
</div>
EOFPHP'

echo "[3/4] Adding private_subpages to config.php (if not exists)..."
if ! docker exec $WEB_CONTAINER grep -q "private_subpages" /phpipam/config.php; then
    docker exec $WEB_CONTAINER sh -c 'echo "" >> /phpipam/config.php'
    docker exec $WEB_CONTAINER sh -c 'echo "// Custom tools" >> /phpipam/config.php'
    docker exec $WEB_CONTAINER sh -c "echo '\$private_subpages = [\"health-monitor\"];' >> /phpipam/config.php"
    echo "    Added private_subpages configuration"
else
    echo "    private_subpages already exists, skipping"
fi

echo "[4/4] Adding health-monitor to tools_menu_items (if not exists)..."
if ! docker exec $WEB_CONTAINER grep -q "health-monitor" /phpipam/app/tools/tools-menu-config.php; then
    docker exec $WEB_CONTAINER sed -i '/"vaults".*=>/a\                                                "health-monitor"       => _("health-monitor"),' /phpipam/app/tools/tools-menu-config.php
    echo "    Added health-monitor to tools_menu_items"
else
    echo "    health-monitor already exists, skipping"
fi

# 驗證
echo ""
echo "=== Verification ==="
docker exec $WEB_CONTAINER php -l /phpipam/app/tools/tools-menu-config.php
docker exec $WEB_CONTAINER ls -la /phpipam/app/tools/health-monitor/

echo ""
echo "=== Setup Complete ==="
echo "Access URL: https://ipam-tw.pouchen.com/index.php?page=tools&section=health-monitor"
echo ""
