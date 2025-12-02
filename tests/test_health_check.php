<?php
/**
 * test_health_check.php
 * 
 * 測試健康檢查功能
 * 
 * 執行方式: php tests/test_health_check.php
 * 
 * @author Jason Cheng
 * @created 2025-12-02
 */

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 引入 Controller
require_once(__DIR__ . '/../api/HealthCheckController.php');

echo "========================================\n";
echo "phpIPAM 健康檢查測試\n";
echo "========================================\n\n";

// 測試 1: 無參數（使用預設 DHCP 列表）
echo "[測試 1] 執行健康檢查（預設參數）\n";
echo "----------------------------------------\n";
$result1 = HealthCheckController::execute();
echo json_encode($result1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// 測試 2: 指定單一 DHCP 伺服器
echo "[測試 2] 檢查單一 DHCP 伺服器\n";
echo "----------------------------------------\n";
$result2 = HealthCheckController::execute(['dhcp_server_ip' => '172.16.5.196']);
echo json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// 測試 3: 指定多個 DHCP 伺服器
echo "[測試 3] 檢查多個 DHCP 伺服器\n";
echo "----------------------------------------\n";
$result3 = HealthCheckController::execute([
    'dhcp_server_ip' => '172.16.5.196,172.23.127.169'
]);
echo json_encode($result3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// 測試 4: 測試個別類別
echo "[測試 4] 測試 SystemInfo 類別\n";
echo "----------------------------------------\n";
$system_info = SystemInfo::getAll();
echo json_encode($system_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

echo "[測試 5] 測試 NetworkStats 類別\n";
echo "----------------------------------------\n";
$network_stats = NetworkStats::getStats();
echo json_encode($network_stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

echo "[測試 6] 測試 DhcpChecker 類別\n";
echo "----------------------------------------\n";
$dhcp_results = DhcpChecker::check(['8.8.8.8', '1.1.1.1']); // 測試公開 DNS
echo json_encode($dhcp_results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// 效能測試
echo "[效能測試] 測試執行時間\n";
echo "----------------------------------------\n";
$iterations = 10;
$times = [];

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    HealthCheckController::execute(['dhcp_server_ip' => '172.16.5.196']);
    $times[] = microtime(true) - $start;
}

$avg_time = array_sum($times) / count($times);
$min_time = min($times);
$max_time = max($times);

echo sprintf("執行次數: %d\n", $iterations);
echo sprintf("平均時間: %.3f 秒\n", $avg_time);
echo sprintf("最小時間: %.3f 秒\n", $min_time);
echo sprintf("最大時間: %.3f 秒\n", $max_time);
echo "\n";

echo "========================================\n";
echo "測試完成！\n";
echo "========================================\n";
