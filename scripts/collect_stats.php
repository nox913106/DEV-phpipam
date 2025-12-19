#!/usr/bin/env php
<?php
/**
 * collect_stats.php
 * 
 * 資料收集排程腳本
 * 建議每 5 分鐘執行一次，收集系統和 DHCP 監控數據
 * 
 * Cron 設定範例:
 * */5 * * * * php /var/www/phpipam/app/tools/health_check/scripts/collect_stats.php
 * 
 * @author Jason Cheng
 * @created 2025-12-18
 */

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定義基礎路徑
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

// 引入必要類別
require_once(INCLUDES_PATH . '/HistoryCollector.php');

/**
 * 取得 phpIPAM 資料庫連線
 * 
 * @return PDO 資料庫連線
 */
function getPhpIpamDatabase() {
    // 載入 phpIPAM 配置 (部署到 phpIPAM 時使用)
    $config_file = '/var/www/phpipam/config.php';
    
    if (file_exists($config_file)) {
        // 從 phpIPAM 配置讀取資料庫設定
        require_once($config_file);
        
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        return $pdo;
    }
    
    // 開發環境：使用獨立配置
    $dev_config = BASE_PATH . '/config/database.php';
    if (file_exists($dev_config)) {
        $db = require($dev_config);
        
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        return $pdo;
    }
    
    throw new Exception("無法找到資料庫配置檔案");
}

/**
 * 主程式
 */
function main() {
    $start_time = microtime(true);
    $timestamp = date('Y-m-d H:i:s');
    
    echo "=== Health Check Data Collector ===\n";
    echo "Started at: {$timestamp}\n\n";
    
    try {
        // 取得資料庫連線
        $db = getPhpIpamDatabase();
        echo "[OK] 資料庫連線成功\n";
        
        // 執行資料收集
        $results = HistoryCollector::collectAll($db);
        
        // 輸出結果
        if ($results['system']['success']) {
            $cpu = $results['system']['data']['cpu'];
            $mem = $results['system']['data']['memory'];
            $disk = $results['system']['data']['disk'];
            echo "[OK] 系統資源: CPU={$cpu}%, Memory={$mem}%, Disk={$disk}%\n";
        } else {
            echo "[ERROR] 系統資源收集失敗: {$results['system']['error']}\n";
        }
        
        if ($results['dhcp']['success']) {
            $count = $results['dhcp']['count'];
            echo "[OK] DHCP 伺服器: 已檢查 {$count} 台伺服器\n";
            
            foreach ($results['dhcp']['servers'] as $server) {
                $status = $server['reachable'] ? '✓ Online' : '✗ Offline';
                $hostname = $server['hostname'] ?? $server['ip'];
                echo "     - {$hostname} ({$server['ip']}): {$status}\n";
            }
        } else {
            echo "[ERROR] DHCP 檢查失敗: {$results['dhcp']['error']}\n";
        }
        
        // 清理舊資料 (每次執行都檢查)
        $purge_result = HistoryCollector::purgeOldRecords($db, 7);
        if ($purge_result['success']) {
            $sys_del = $purge_result['deleted']['system_records'];
            $dhcp_del = $purge_result['deleted']['dhcp_records'];
            if ($sys_del > 0 || $dhcp_del > 0) {
                echo "[OK] 已清理舊資料: 系統={$sys_del}筆, DHCP={$dhcp_del}筆\n";
            }
        }
        
    } catch (Exception $e) {
        echo "[FATAL] 執行錯誤: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // 完成
    $elapsed = round((microtime(true) - $start_time) * 1000, 2);
    echo "\nCompleted in {$elapsed}ms\n";
    echo "================================\n";
}

// 執行主程式
main();
