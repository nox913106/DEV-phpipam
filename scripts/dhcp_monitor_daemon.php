#!/usr/bin/env php
<?php
/**
 * dhcp_monitor_daemon.php
 * 
 * DHCP 伺服器監控 Daemon
 * 每 5 秒執行一次 ping 測試，寫入資料庫
 * 
 * 使用方式:
 *   php dhcp_monitor_daemon.php        # 前景執行
 *   php dhcp_monitor_daemon.php &      # 背景執行
 *   nohup php dhcp_monitor_daemon.php > /var/log/dhcp_monitor.log 2>&1 &
 */

// 設定
define('MONITOR_INTERVAL', 5);  // 監控間隔（秒）
define('PING_TIMEOUT', 2);      // Ping 逾時（秒）
define('MAX_RETRIES', 3);       // 最大重試次數

// 資料庫設定（從 phpIPAM 設定讀取）
$db_config = [
    'host' => getenv('DB_HOST') ?: 'phpipam-mariadb',
    'user' => getenv('DB_USER') ?: 'phpipam',
    'pass' => getenv('DB_PASS') ?: 'my_secret_phpipam_pass',
    'name' => getenv('DB_NAME') ?: 'phpipam'
];

// DHCP 伺服器設定檔路徑
$dhcp_config_path = __DIR__ . '/../config/dhcp_servers.json';

/**
 * 載入 DHCP 伺服器清單
 */
function load_dhcp_servers($config_path) {
    if (!file_exists($config_path)) {
        error_log("DHCP config not found: $config_path");
        return [];
    }
    
    $json = file_get_contents($config_path);
    $servers = json_decode($json, true);
    
    if (!is_array($servers)) {
        error_log("Invalid DHCP config format");
        return [];
    }
    
    // 只返回啟用的伺服器
    return array_filter($servers, function($s) {
        return isset($s['enabled']) ? $s['enabled'] : true;
    });
}

/**
 * Ping 單一伺服器
 */
function ping_server($ip, $timeout = PING_TIMEOUT) {
    $start = microtime(true);
    
    // 使用 fping 或 ping
    if (file_exists('/usr/bin/fping')) {
        $cmd = "fping -c1 -t" . ($timeout * 1000) . " $ip 2>&1";
    } else {
        $cmd = "ping -c 1 -W $timeout $ip 2>&1";
    }
    
    exec($cmd, $output, $return_code);
    $elapsed = (microtime(true) - $start) * 1000;
    
    // 解析延遲
    $latency = null;
    $output_str = implode("\n", $output);
    
    if (preg_match('/time[=<]([0-9.]+)\s*ms/i', $output_str, $matches)) {
        $latency = floatval($matches[1]);
    } elseif (preg_match('/([0-9.]+)\/([0-9.]+)\/([0-9.]+)/i', $output_str, $matches)) {
        // fping 格式: min/avg/max
        $latency = floatval($matches[2]);
    }
    
    return [
        'reachable' => ($return_code === 0),
        'latency_ms' => $latency ?? round($elapsed, 2)
    ];
}

/**
 * 取得資料庫連線
 */
function get_db_connection($config) {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

/**
 * 寫入 DHCP 歷史記錄
 */
function save_dhcp_history($pdo, $records) {
    $sql = "INSERT INTO health_check_dhcp_history 
            (dhcp_ip, dhcp_hostname, dhcp_location, reachable, latency_ms, recorded_at)
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($records as $record) {
        try {
            $stmt->execute([
                $record['ip'],
                $record['hostname'] ?? '',
                $record['location'] ?? '',
                $record['reachable'] ? 1 : 0,
                $record['latency_ms']
            ]);
        } catch (PDOException $e) {
            error_log("Insert failed for {$record['ip']}: " . $e->getMessage());
        }
    }
}

/**
 * 清理舊資料（保留最近 7 天）
 */
function cleanup_old_data($pdo, $days = 7) {
    try {
        $sql = "DELETE FROM health_check_dhcp_history WHERE recorded_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days]);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            error_log("Cleaned up $deleted old DHCP records");
        }
    } catch (PDOException $e) {
        error_log("Cleanup failed: " . $e->getMessage());
    }
}

/**
 * 主要監控迴圈
 */
function main_loop($db_config, $dhcp_config_path) {
    echo "=== DHCP Monitor Daemon ===\n";
    echo "Interval: " . MONITOR_INTERVAL . " seconds\n";
    echo "Starting at: " . date('Y-m-d H:i:s') . "\n";
    echo "===========================\n\n";
    
    $iteration = 0;
    $cleanup_counter = 0;
    
    while (true) {
        $iteration++;
        $start_time = microtime(true);
        
        // 載入 DHCP 伺服器清單（每次重新載入以支援即時更新）
        $servers = load_dhcp_servers($dhcp_config_path);
        
        if (empty($servers)) {
            error_log("No DHCP servers configured, waiting...");
            sleep(MONITOR_INTERVAL);
            continue;
        }
        
        // 取得資料庫連線
        $pdo = get_db_connection($db_config);
        if (!$pdo) {
            error_log("Database unavailable, retrying in " . MONITOR_INTERVAL . "s");
            sleep(MONITOR_INTERVAL);
            continue;
        }
        
        // Ping 所有伺服器
        $records = [];
        foreach ($servers as $server) {
            $result = ping_server($server['ip']);
            $records[] = [
                'ip' => $server['ip'],
                'hostname' => $server['hostname'] ?? '',
                'location' => $server['location'] ?? '',
                'reachable' => $result['reachable'],
                'latency_ms' => $result['latency_ms']
            ];
        }
        
        // 儲存結果
        save_dhcp_history($pdo, $records);
        
        // 每 100 次迭代清理一次舊資料（約 8 分鐘一次）
        $cleanup_counter++;
        if ($cleanup_counter >= 100) {
            cleanup_old_data($pdo);
            $cleanup_counter = 0;
        }
        
        // 計算執行時間並等待
        $elapsed = microtime(true) - $start_time;
        $sleep_time = max(0, MONITOR_INTERVAL - $elapsed);
        
        // 每 12 次（1 分鐘）輸出一次狀態
        if ($iteration % 12 == 0) {
            $online = count(array_filter($records, fn($r) => $r['reachable']));
            echo date('Y-m-d H:i:s') . " - Iteration $iteration: $online/" . count($records) . " online\n";
        }
        
        if ($sleep_time > 0) {
            usleep($sleep_time * 1000000);
        }
    }
}

// 設定信號處理
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        echo "\nReceived SIGTERM, shutting down...\n";
        exit(0);
    });
    pcntl_signal(SIGINT, function() {
        echo "\nReceived SIGINT, shutting down...\n";
        exit(0);
    });
}

// 執行主迴圈
main_loop($db_config, $dhcp_config_path);
