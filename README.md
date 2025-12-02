# dev-phpipam - phpIPAM 伺服器端健康檢查 API

為 phpIPAM 伺服器端開發完整的健康檢查 API，提供系統資訊給 MCP tool 調用。

## 專案目標

擴展 phpIPAM API 功能，新增健康檢查 endpoint，回傳：
- ✅ phpIPAM 主機系統資訊（CPU、記憶體、硬碟、運行時間）
- ✅ 網路流量統計（24 小時）
- ✅ DHCP 伺服器連線狀態

## 專案結構

```
dev-phpipam/
├── README.md                       # 專案說明
├── DEPLOYMENT.md                   # 部署文件
├── api/
│   └── HealthCheckController.php   # API Controller
├── includes/
│   ├── SystemInfo.php              # 系統資訊收集
│   ├── NetworkStats.php            # 網路統計
│   └── DhcpChecker.php             # DHCP 檢查
├── config/
│   └── health_check_config.php     # 配置檔
└── tests/
    └── test_health_check.php       # 測試腳本
```

## API Endpoint

```
GET /api/{app_id}/tools/daily_health_check/?dhcp_server_ip=172.16.5.196
```

### 回應範例

```json
{
  "success": true,
  "code": 200,
  "data": {
    "report_type": "daily_health_check",
    "host_info": {
      "hostname": "ipam-server",
      "uptime_seconds": 2592000
    },
    "system_resources": {
      "cpu": {"usage_percent": 15.5},
      "memory": {"usage_percent": 50.0},
      "disk": {"usage_percent": 45.0}
    },
    "network_stats": {
      "last_24h": {"rx_bytes": 1073741824}
    },
    "dhcp_servers": [
      {"ip": "172.16.5.196", "status": "online"}
    ]
  }
}
```

## 快速開始

### 開發環境

- PHP 7.4+
- phpIPAM 1.4+
- Linux 系統（Ubuntu/CentOS）

### 測試

```bash
# 執行單元測試
php tests/test_health_check.php

# 測試 API
curl -X GET "http://localhost/api/mcp/tools/daily_health_check/" \
  -H "token: YOUR_TOKEN"
```

### 部署到 phpIPAM

請參考 [DEPLOYMENT.md](DEPLOYMENT.md)

## 整合 MCP Tool

部署後，MCP tool 的 `daily_health_check` 會自動偵測並使用伺服器端 API，回傳完整的系統資訊。

## 安全性

- ✅ 使用 phpIPAM Token 認證
- ✅ 嚴格驗證所有輸入參數
- ✅ 限制系統指令白名單
- ✅ 記錄 API 呼叫日誌

## License

MIT License
