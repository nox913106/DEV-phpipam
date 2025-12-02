<?php
/**
 * health_check_config.example.php
 * 
 * 健康檢查功能配置檔範例
 * 使用時請複製為 health_check_config.php 並修改設定
 * 
 * @author Jason Cheng
 * @created 2025-12-02
 */

return [
    // DHCP 伺服器列表（IP => 主機名稱）
    'default_dhcp_servers' => [
        '172.16.5.196'   => 'DHCP-CH-HQ2',           // 彰化總部2
        '172.23.13.10'   => 'DHCP-CH-PGT',           // 彰化埔鹽
        '172.23.174.5'   => 'DHCP-TC-HQ',            // 台中總部
        '172.23.199.150' => 'DHCP-TC-UAIC',          // 台中
        '172.23.110.1'   => 'DHCP-TP-XY',            // 台北
        '172.23.94.254'  => 'DHCP-TP-BaoYu-CoreSW'   // 台北寶裕
    ],
    
    // Ping 檢查參數
    'ping_count' => 4,           // Ping 次數
    'ping_timeout' => 2,         // 逾時秒數
    
    // 網路介面設定
    'network_interface' => null, // null = 自動偵測主要介面
    
    // 硬碟檢查路徑
    'disk_check_path' => '/',    // 要檢查的硬碟路徑
    
    // 快取設定
    'cache_enabled' => false,    // 是否啟用快取
    'cache_ttl' => 60,          // 快取時間（秒）
    
    // 日誌設定
    'log_enabled' => true,       // 是否記錄日誌
    'log_path' => '/var/log/phpipam/health_check.log',
    
    // 安全性設定
    'allowed_app_ids' => ['mcp'], // 允許存取的 APP ID 列表（空陣列 = 全部允許）
];
