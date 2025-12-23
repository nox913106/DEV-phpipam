# phpIPAM 健康檢查 API 部署文件

## 前置需求

- phpIPAM 1.4 或更高版本
- PHP 7.4 或更高版本
- Linux 作業系統（Ubuntu/CentOS）
- 具有 root 或 sudo 權限

## 部署步驟

### 1. 備份現有檔案

```bash
cd /var/www/phpipam
sudo tar -czf backup_$(date +%Y%m%d_%H%M%S).tar.gz app/
```

### 2. 建立目錄結構

```bash
sudo mkdir -p /var/www/phpipam/app/tools/health_check
sudo mkdir -p /var/www/phpipam/app/tools/health_check/api
sudo mkdir -p /var/www/phpipam/app/tools/health_check/includes
sudo mkdir -p /var/www/phpipam/app/tools/health_check/config
```

### 3. 上傳檔案

將以下檔案從 `dev-phpipam/` 上傳到 phpIPAM 伺服器：

```bash
# 從本地上傳（需修改主機名稱）
scp -r api/ user@ipam-server:/var/www/phpipam/app/tools/health_check/
scp -r includes/ user@ipam-server:/var/www/phpipam/app/tools/health_check/
scp -r config/ user@ipam-server:/var/www/phpipam/app/tools/health_check/
```

或使用 SFTP/WinSCP 等工具上傳。

### 4. 設定權限

```bash
sudo chown -R www-data:www-data /var/www/phpipam/app/tools/health_check/
sudo chmod -R 755 /var/www/phpipam/app/tools/health_check/
```

### 5. 整合到 phpIPAM API

編輯 phpIPAM 的 API 路由檔案（位置可能因版本而異）：

```bash
sudo nano /var/www/phpipam/app/controllers/Api/ToolsController.php
```

加入健康檢查路由：

```php
/**
 * Daily Health Check endpoint
 * GET /api/{app_id}/tools/daily_health_check/
 */
public function daily_health_check() {
    require_once(__DIR__ . '/../../tools/health_check/api/HealthCheckController.php');
    
    // 取得 GET 參數
    $params = $_GET;
    
    // 執行健康檢查
    $result = HealthCheckController::execute($params);
    
    // 回傳結果
    $this->Response->throw_success($result['data'], 200, $result['time']);
}
```

### 6. 配置 API 權限

在 phpIPAM 管理介面：

1. 登入 phpIPAM 管理介面
2. 前往「管理」→「API」
3. 選擇或建立 APP ID（例如 `mcp`）
4. 確保「Tools」權限已啟用
5. 記下 Token

### 7. 測試 API

使用 curl 測試：

```bash
curl -X GET \
  "https://ipam-tw.pouchen.com/api/mcp/tools/daily_health_check/" \
  -H "token: YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  | jq .
```

預期回應：

```json
{
  "success": true,
  "code": 200,
  "data": {
    "report_type": "daily_health_check",
    "host_info": {...},
    "system_resources": {...},
    "network_stats": {...},
    "dhcp_servers": [...]
  },
  "time": 0.145
}
```

## 故障排除

### 問題：403 Forbidden

**原因**：檔案權限不正確

**解決方式**：
```bash
sudo chown -R www-data:www-data /var/www/phpipam/app/tools/health_check/
sudo chmod -R 755 /var/www/phpipam/app/tools/health_check/
```

### 問題：500 Internal Server Error

**原因**：PHP 錯誤

**檢查方式**：
```bash
sudo tail -f /var/log/apache2/error.log
# 或
sudo tail -f /var/log/nginx/error.log
```

### 問題：API 回傳空值

**原因**：路由未設定

**檢查方式**：確認 ToolsController.php 已正確加入 `daily_health_check()` 方法

### 問題：DHCP 檢查失敗

**原因**：PHP 無法執行系統指令

**解決方式**：
```bash
# 檢查 PHP 配置
grep disable_functions /etc/php/7.4/apache2/php.ini

# 確保 exec 和 shell_exec 未被禁用
```

## 安全性建議

1. **限制 API 存取**：只允許特定 IP 存取 API
2. **定期更新 Token**：每季更新 API Token
3. **啟用 HTTPS**：確保所有 API 呼叫都透過 HTTPS
4. **監控日誌**：定期檢查 API 存取日誌

## 日誌位置

- Apache 錯誤日誌：`/var/log/apache2/error.log`
- Nginx 錯誤日誌：`/var/log/nginx/error.log`
- phpIPAM 日誌：`/var/www/phpipam/app/admin/logs/`

## 回滾步驟

如需回滾變更：

```bash
cd /var/www/phpipam
sudo rm -rf app/tools/health_check/
# 從備份恢復
sudo tar -xzf backup_YYYYMMDD_HHMMSS.tar.gz
```

## 更新 MCP Tool

部署完成後，更新 `mcp_phpipam.py` 恢復嘗試呼叫伺服器端 API：

1. 修改 `daily_health_check` 函數
2. 重新啟動 MCP server 或 Roo Code
3. 測試功能

---

**部署完成後請通知系統管理員測試！**

---

## Docker 環境部署 (v2.1)

### 快速部署

使用持久化腳本一鍵部署 Health Dashboard 和 phpIPAM 整合：

```bash
# 下載並執行設定腳本
curl -sL https://raw.githubusercontent.com/nox913106/DEV-phpipam/master/scripts/setup_phpipam_integration.sh -o /tmp/setup.sh
chmod +x /tmp/setup.sh
/tmp/setup.sh
```

### 手動部署步驟

#### 1. 部署 Health Dashboard

```bash
# 建立暫存目錄
mkdir -p /tmp/health_update && cd /tmp/health_update

# 下載更新的檔案
curl -sLO https://raw.githubusercontent.com/nox913106/DEV-phpipam/master/api/api_stats.php
curl -sLO https://raw.githubusercontent.com/nox913106/DEV-phpipam/master/dashboard/index.html

# 複製到 Web 容器
docker cp api_stats.php phpipam_phpipam-web_1:/phpipam/health_dashboard/api/
docker cp index.html phpipam_phpipam-web_1:/phpipam/health_dashboard/
```

#### 2. 整合到 phpIPAM Tools 選單

```bash
# 建立工具目錄
docker exec phpipam_phpipam-web_1 mkdir -p /phpipam/app/tools/health-monitor

# 建立 index.php
docker exec phpipam_phpipam-web_1 sh -c 'cat > /phpipam/app/tools/health-monitor/index.php << '\''EOF'\''
<?php
$User->check_user_session();
?>
<h4><i class="fa fa-heartbeat"></i> Health Monitor Dashboard</h4>
<hr>
<div style="width:100%; height:calc(100vh - 180px); min-height:600px;">
    <iframe src="/health_dashboard/" style="width:100%; height:100%; border:none;"></iframe>
</div>
EOF'

# 添加到 config.php
docker exec phpipam_phpipam-web_1 sh -c 'echo "\$private_subpages = [\"health-monitor\"];" >> /phpipam/config.php'

# 添加到 tools_menu_items
docker exec phpipam_phpipam-web_1 sed -i '/"vaults".*=>/a\                                                "health-monitor"       => _("health-monitor"),' /phpipam/app/tools/tools-menu-config.php

# 驗證語法
docker exec phpipam_phpipam-web_1 php -l /phpipam/app/tools/tools-menu-config.php
```

#### 3. 驗證

```bash
# 測試 API
curl -sk "https://ipam-tw.pouchen.com/health_dashboard/api/api_stats.php?action=latest"

# 訪問 Dashboard
# https://ipam-tw.pouchen.com/index.php?page=tools&section=health-monitor
```

### 容器重啟後恢復

```bash
# 執行設定腳本恢復所有設定
./scripts/setup_phpipam_integration.sh
```

