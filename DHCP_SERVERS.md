# DHCP 伺服器資訊

本專案監控的 DHCP 伺服器列表

## 伺服器列表

| IP 位址 | 主機名稱 | 位置 | 狀態 |
|---------|---------|------|------|
| 172.16.5.196 | DHCP-CH-HQ2 | 彰化總部2 | ✅ 運作中 |
| 172.23.13.10 | DHCP-CH-PGT | 彰化埔鹽 | ✅ 運作中 |
| 172.23.174.5 | DHCP-TC-HQ | 台中總部 | ✅ 運作中 |
| 172.23.199.150 | DHCP-TC-UAIC | 台中 | ✅ 運作中 |
| 172.23.110.1 | DHCP-TP-XY | 台北 | ✅ 運作中 |
| 172.23.94.254 | DHCP-TP-BaoYu-CoreSW | 台北寶裕 | ✅ 運作中 |

## 預設監控範圍

健康檢查 API 預設會檢查上述所有 6 台 DHCP 伺服器。

## 自訂監控範圍

如需監控特定伺服器，可在 API 請求中指定：

```bash
# 監控單一伺服器
curl -X GET "https://ipam-tw.pouchen.com/api/mcp/tools/daily_health_check/?dhcp_server_ip=172.16.5.196" \
  -H "token: YOUR_TOKEN"

# 監控多個伺服器
curl -X GET "https://ipam-tw.pouchen.com/api/mcp/tools/daily_health_check/?dhcp_server_ip=172.16.5.196,172.23.13.10" \
  -H "token: YOUR_TOKEN"
```

## 變更歷史

- 2025-12-02: 初始版本，包含 6 台 DHCP 伺服器

## 維護

如需新增或移除 DHCP 伺服器：

1. 編輯 `config/health_check_config.php`
2. 更新 `default_dhcp_servers` 陣列
3. 重新部署到 phpIPAM 伺服器
