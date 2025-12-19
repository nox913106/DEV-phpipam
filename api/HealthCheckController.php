<?php
/**
 * HealthCheckController.php
 * 
 * phpIPAM API Controller for Health Check
 * 整合系統資訊、網路統計、DHCP 檢查和 24 小時歷史統計功能
 * 
 * @author Jason Cheng
 * @created 2025-12-02
 * @updated 2025-12-18 - 加入 24 小時歷史統計功能
 */

// 引入必要的類別
require_once(__DIR__ . '/../includes/SystemInfo.php');
require_once(__DIR__ . '/../includes/NetworkStats.php');
require_once(__DIR__ . '/../includes/DhcpChecker.php');
require_once(__DIR__ . '/../includes/StatsCalculator.php');

/**
 * 健康檢查 API Controller
 * 
 * 此 Controller 應整合到 phpIPAM 的 API 架構中
 * 路徑: /api/{app_id}/tools/daily_health_check/
 */
class HealthCheckController {
    
    /** @var PDO 資料庫連線 (用於歷史統計) */
    private static $db = null;
    
    /**
     * 設定資料庫連線
     * 
     * @param PDO $db 資料庫連線
     */
    public static function setDatabase($db) {
        self::$db = $db;
    }
    
    /**
     * 取得資料庫連線
     * 嘗試從 phpIPAM 環境取得資料庫連線
     * 
     * @return PDO|null 資料庫連線或 null
     */
    private static function getDatabase() {
        // 如果已設定，直接返回
        if (self::$db !== null) {
            return self::$db;
        }
        
        // 嘗試從 phpIPAM 環境取得
        global $Database;
        if (isset($Database) && $Database instanceof Database) {
            try {
                // phpIPAM 的 Database 類別
                self::$db = $Database->getConnection();
                return self::$db;
            } catch (Exception $e) {
                // 忽略錯誤，返回 null
            }
        }
        
        // 嘗試從配置檔建立連線
        $config_file = __DIR__ . '/../config/database.php';
        if (file_exists($config_file)) {
            try {
                $db_config = require($config_file);
                $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
                self::$db = new PDO($dsn, $db_config['user'], $db_config['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                return self::$db;
            } catch (Exception $e) {
                // 忽略錯誤
            }
        }
        
        return null;
    }
    
    /**
     * 執行健康檢查
     * 
     * @param array $params GET 參數
     * @return array API 回應
     */
    public static function execute($params = []) {
        try {
            $start_time = microtime(true);
            
            // 解析參數
            $dhcp_ips = isset($params['dhcp_server_ip']) ? $params['dhcp_server_ip'] : '';
            $include_history = isset($params['include_history']) ? 
                filter_var($params['include_history'], FILTER_VALIDATE_BOOLEAN) : true;
            
            // 預設 DHCP 伺服器列表
            if (empty($dhcp_ips)) {
                $dhcp_ips = '172.16.5.196,172.23.13.10,172.23.174.5,172.23.199.150,172.23.110.1,172.23.94.254';
            }
            
            // 取得資料庫連線 (用於歷史統計)
            $db = $include_history ? self::getDatabase() : null;
            
            // 收集系統資訊 (含歷史統計)
            if ($db !== null) {
                $system_info = SystemInfo::getAllWithHistory($db);
            } else {
                $system_info = SystemInfo::getAll();
            }
            
            // 收集網路統計
            $network_stats = NetworkStats::getStats();
            
            // 檢查 DHCP 伺服器 (含歷史統計)
            if ($db !== null) {
                $dhcp_results = DhcpChecker::checkWithHistory($dhcp_ips, $db);
            } else {
                $dhcp_results = DhcpChecker::check($dhcp_ips);
            }
            
            // 計算執行時間
            $execution_time = microtime(true) - $start_time;
            
            // 建立回應資料
            $result = [
                'report_type' => 'daily_health_check',
                'generated_at' => date('c'),
                'execution_time_ms' => round($execution_time * 1000, 2),
                'host_info' => $system_info['host_info'],
                'system_resources' => $system_info['system_resources'],
                'network_stats' => $network_stats,
                'dhcp_servers' => $dhcp_results,
                'historical_data_available' => ($db !== null)
            ];
            
            return self::successResponse($result, $execution_time);
            
        } catch (Exception $e) {
            return self::errorResponse($e->getMessage());
        }
    }
    
    /**
     * 僅取得 24 小時統計摘要
     * 
     * @return array API 回應
     */
    public static function getStatsSummary() {
        try {
            $db = self::getDatabase();
            
            if ($db === null) {
                return self::errorResponse('資料庫連線不可用');
            }
            
            $summary = StatsCalculator::getSummary($db);
            
            return self::successResponse([
                'report_type' => 'stats_summary_24h',
                'summary' => $summary
            ], 0);
            
        } catch (Exception $e) {
            return self::errorResponse($e->getMessage());
        }
    }
    
    /**
     * 成功回應格式（符合 phpIPAM API 規範）
     * 
     * @param array $data 資料
     * @param float $time 執行時間
     * @return array 格式化回應
     */
    private static function successResponse($data, $time) {
        return [
            'success' => true,
            'code' => 200,
            'data' => $data,
            'time' => round($time, 3)
        ];
    }
    
    /**
     * 錯誤回應格式（符合 phpIPAM API 規範）
     * 
     * @param string $message 錯誤訊息
     * @return array 格式化回應
     */
    private static function errorResponse($message) {
        return [
            'success' => false,
            'code' => 500,
            'message' => $message,
            'time' => 0
        ];
    }
}

// 如果直接執行此檔案（用於測試）
if (php_sapi_name() === 'cli') {
    // CLI 模式測試
    header('Content-Type: application/json');
    
    // 模擬 GET 參數
    $params = [];
    if (isset($argv[1])) {
        parse_str($argv[1], $params);
    }
    
    $result = HealthCheckController::execute($params);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
