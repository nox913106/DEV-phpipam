<?php
/**
 * dhcp_servers.php - DHCP 伺服器配置檔
 * 
 * 此檔案定義要監控的 DHCP 伺服器列表
 * 可隨時修改，無需重啟服務
 * 
 * @author Jason Cheng
 * @created 2025-12-19
 */

return [
    // 彰化區域
    [
        'ip' => '172.16.5.196',
        'hostname' => 'DHCP-CH-HQ2',
        'location' => '彰化總部2',
        'enabled' => true
    ],
    [
        'ip' => '172.23.13.10',
        'hostname' => 'DHCP-CH-PGT',
        'location' => '彰化埔鹽',
        'enabled' => true
    ],
    
    // 台中區域
    [
        'ip' => '172.23.174.5',
        'hostname' => 'DHCP-TC-HQ',
        'location' => '台中總部',
        'enabled' => true
    ],
    [
        'ip' => '172.23.199.150',
        'hostname' => 'DHCP-TC-UAIC',
        'location' => '台中',
        'enabled' => true
    ],
    
    // 台北區域
    [
        'ip' => '172.23.110.1',
        'hostname' => 'DHCP-TP-XY',
        'location' => '台北',
        'enabled' => true
    ],
    [
        'ip' => '172.23.94.254',
        'hostname' => 'DHCP-TP-BaoYu-CoreSW',
        'location' => '台北寶裕',
        'enabled' => true
    ],
    
    // 新增伺服器範例 (設 enabled=false 暫時停用)
    // [
    //     'ip' => '10.1.1.1',
    //     'hostname' => 'DHCP-NEW',
    //     'location' => '新據點',
    //     'enabled' => false
    // ],
];
