#!/usr/bin/env php
<?php
/**
 * DHCP 監控 Daemon
 * 每 5 秒執行一次 ping 測試，寫入資料庫
 * 
 * v2.2.1 - 簡化版（已驗證可正確運行）
 */

define('MONITOR_INTERVAL', 5);

$pdo = null;
$dhcp_config_path = '/health_check/config/dhcp_servers.json';

function get_db() {
    global $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=phpipam-mariadb;dbname=phpipam;charset=utf8mb4', 
                       'phpipam', 'my_secret_phpipam_pass', 
                       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    return $pdo;
}

function get_next_aligned_time() {
    $now = time();
    return $now - ($now % MONITOR_INTERVAL) + MONITOR_INTERVAL;
}

echo "=== DHCP Monitor Daemon (v2.2.1) ===\n";
echo "Interval: " . MONITOR_INTERVAL . " seconds\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n";

// 等待對齊到 :00/:05/:10... 模式
$next = get_next_aligned_time();
$wait = $next - time();
if ($wait > 0) {
    echo "Waiting {$wait}s to align to :00/:05/:10... pattern\n";
    sleep($wait);
}
echo "First run at: " . date('Y-m-d H:i:s') . "\n";
echo "=====================================\n\n";

$iteration = 0;
while (true) {
    $iteration++;
    
    // 載入 DHCP 伺服器清單
    $servers = json_decode(file_get_contents($dhcp_config_path), true);
    if (!$servers) { 
        error_log("No DHCP servers configured");
        sleep(MONITOR_INTERVAL); 
        continue; 
    }
    
    // 取得資料庫連線
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO health_check_dhcp_history 
        (dhcp_ip, dhcp_hostname, reachable, latency_ms, recorded_at) VALUES (?,?,?,?,NOW())");
    
    // Ping 所有伺服器
    $online = 0;
    foreach ($servers as $s) {
        if (!($s['enabled'] ?? true)) continue;
        
        // 初始化 output 陣列（重要！）
        $output = [];
        exec("ping -c 1 -W 2 " . $s['ip'] . " 2>&1", $output, $code);
        $output_str = implode("\n", $output);
        
        // 解析延遲
        $latency = null;
        if (preg_match('/time[=<]([0-9.]+)\s*ms/i', $output_str, $m)) {
            $latency = floatval($m[1]);
        }
        
        $reachable = ($code === 0) ? 1 : 0;
        if ($reachable) $online++;
        
        // 寫入資料庫
        try {
            $stmt->execute([$s['ip'], $s['hostname'] ?? '', $reachable, $latency]);
        } catch (PDOException $e) {
            error_log("Insert failed for {$s['ip']}: " . $e->getMessage());
        }
    }
    
    // 清理舊資料（每 100 次迭代，約 8 分鐘一次）
    if ($iteration % 100 == 0) {
        try {
            $deleted = $db->exec("DELETE FROM health_check_dhcp_history WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            if ($deleted > 0) {
                error_log("Cleaned up $deleted old records");
            }
        } catch (PDOException $e) {
            error_log("Cleanup failed: " . $e->getMessage());
        }
    }
    
    // 每分鐘輸出狀態
    if ($iteration % 12 == 0) {
        echo date('Y-m-d H:i:s') . " - Iteration $iteration: $online/" . count($servers) . " online\n";
    }
    
    // 對齊到下一個 5 秒點
    $next = get_next_aligned_time();
    $sleep = max(0, $next - time());
    if ($sleep > 0) sleep($sleep);
}
