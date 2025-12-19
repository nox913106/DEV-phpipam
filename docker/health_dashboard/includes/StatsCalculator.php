<?php
/**
 * StatsCalculator.php
 * 
 * 統計計算器
 * 從歷史資料計算 24 小時統計 (平均值、最小值、最大值)
 * 
 * @author Jason Cheng
 * @created 2025-12-18
 */

class StatsCalculator {
    
    /**
     * 取得 24 小時系統資源統計
     * 
     * @param PDO $db 資料庫連線
     * @param int $hours 統計時間範圍 (預設 24 小時)
     * @return array 統計結果
     */
    public static function getSystemStats24h($db, $hours = 24) {
        try {
            $sql = "SELECT 
                -- CPU 統計
                AVG(cpu_usage_percent) as cpu_avg,
                MIN(cpu_usage_percent) as cpu_min,
                MAX(cpu_usage_percent) as cpu_max,
                
                -- 記憶體統計
                AVG(memory_usage_percent) as memory_avg,
                MIN(memory_usage_percent) as memory_min,
                MAX(memory_usage_percent) as memory_max,
                
                -- 磁碟統計
                AVG(disk_usage_percent) as disk_avg,
                MIN(disk_usage_percent) as disk_min,
                MAX(disk_usage_percent) as disk_max,
                
                -- 樣本數
                COUNT(*) as samples
                
            FROM health_check_system_history
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([':hours' => $hours]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果沒有資料，返回空統計
            if (!$row || $row['samples'] == 0) {
                return self::emptySystemStats();
            }
            
            return [
                'cpu' => [
                    'avg' => round((float)$row['cpu_avg'], 2),
                    'min' => round((float)$row['cpu_min'], 2),
                    'max' => round((float)$row['cpu_max'], 2),
                    'samples' => (int)$row['samples']
                ],
                'memory' => [
                    'avg' => round((float)$row['memory_avg'], 2),
                    'min' => round((float)$row['memory_min'], 2),
                    'max' => round((float)$row['memory_max'], 2),
                    'samples' => (int)$row['samples']
                ],
                'disk' => [
                    'avg' => round((float)$row['disk_avg'], 2),
                    'min' => round((float)$row['disk_min'], 2),
                    'max' => round((float)$row['disk_max'], 2),
                    'samples' => (int)$row['samples']
                ],
                'period_hours' => $hours,
                'has_data' => true
            ];
            
        } catch (Exception $e) {
            return self::emptySystemStats($e->getMessage());
        }
    }
    
    /**
     * 取得 24 小時 DHCP 統計
     * 
     * @param PDO $db 資料庫連線
     * @param string $ip DHCP 伺服器 IP (可選，空值則返回所有伺服器)
     * @param int $hours 統計時間範圍 (預設 24 小時)
     * @return array 統計結果
     */
    public static function getDhcpStats24h($db, $ip = null, $hours = 24) {
        try {
            $params = [':hours' => $hours];
            
            $sql = "SELECT 
                dhcp_ip,
                dhcp_hostname,
                
                -- 延遲統計 (只計算可達的記錄)
                AVG(CASE WHEN reachable = 1 THEN latency_ms ELSE NULL END) as avg_latency,
                MIN(CASE WHEN reachable = 1 THEN latency_ms ELSE NULL END) as min_latency,
                MAX(CASE WHEN reachable = 1 THEN latency_ms ELSE NULL END) as max_latency,
                
                -- 封包遺失率統計
                AVG(packet_loss_percent) as avg_packet_loss,
                
                -- 可用性統計
                SUM(CASE WHEN reachable = 1 THEN 1 ELSE 0 END) as reachable_count,
                COUNT(*) as total_count,
                (SUM(CASE WHEN reachable = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as availability_percent
                
            FROM health_check_dhcp_history
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
            
            // 如果指定 IP，加入篩選條件
            if ($ip !== null) {
                $sql .= " AND dhcp_ip = :ip";
                $params[':ip'] = $ip;
            }
            
            $sql .= " GROUP BY dhcp_ip, dhcp_hostname ORDER BY dhcp_ip";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 如果指定單一 IP，直接返回結果
            if ($ip !== null) {
                if (empty($rows)) {
                    return self::emptyDhcpStats($ip);
                }
                return self::formatDhcpStats($rows[0], $hours);
            }
            
            // 返回所有伺服器的統計
            $results = [];
            foreach ($rows as $row) {
                $results[$row['dhcp_ip']] = self::formatDhcpStats($row, $hours);
            }
            
            return $results;
            
        } catch (Exception $e) {
            if ($ip !== null) {
                return self::emptyDhcpStats($ip, $e->getMessage());
            }
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * 格式化 DHCP 統計結果
     * 
     * @param array $row 資料庫查詢結果
     * @param int $hours 統計時間範圍
     * @return array 格式化的統計
     */
    private static function formatDhcpStats($row, $hours) {
        return [
            'ip' => $row['dhcp_ip'],
            'hostname' => $row['dhcp_hostname'],
            'avg_latency_ms' => round((float)$row['avg_latency'], 2),
            'min_latency_ms' => round((float)$row['min_latency'], 2),
            'max_latency_ms' => round((float)$row['max_latency'], 2),
            'avg_packet_loss' => round((float)$row['avg_packet_loss'], 2),
            'availability_percent' => round((float)$row['availability_percent'], 2),
            'samples' => (int)$row['total_count'],
            'period_hours' => $hours,
            'has_data' => true
        ];
    }
    
    /**
     * 返回空的系統統計結構
     * 
     * @param string $error 錯誤訊息 (可選)
     * @return array 空統計結構
     */
    private static function emptySystemStats($error = null) {
        $empty = [
            'avg' => null,
            'min' => null,
            'max' => null,
            'samples' => 0
        ];
        
        $result = [
            'cpu' => $empty,
            'memory' => $empty,
            'disk' => $empty,
            'has_data' => false,
            'note' => '尚無歷史資料，請等待數據收集'
        ];
        
        if ($error) {
            $result['error'] = $error;
        }
        
        return $result;
    }
    
    /**
     * 返回空的 DHCP 統計結構
     * 
     * @param string $ip DHCP 伺服器 IP
     * @param string $error 錯誤訊息 (可選)
     * @return array 空統計結構
     */
    private static function emptyDhcpStats($ip, $error = null) {
        $result = [
            'ip' => $ip,
            'avg_latency_ms' => null,
            'min_latency_ms' => null,
            'max_latency_ms' => null,
            'avg_packet_loss' => null,
            'availability_percent' => null,
            'samples' => 0,
            'has_data' => false,
            'note' => '尚無歷史資料，請等待數據收集'
        ];
        
        if ($error) {
            $result['error'] = $error;
        }
        
        return $result;
    }
    
    /**
     * 取得統計摘要 (用於快速總覽)
     * 
     * @param PDO $db 資料庫連線
     * @return array 統計摘要
     */
    public static function getSummary($db) {
        $system = self::getSystemStats24h($db);
        $dhcp = self::getDhcpStats24h($db);
        
        // 計算 DHCP 整體可用性
        $total_availability = 0;
        $dhcp_count = 0;
        foreach ($dhcp as $ip => $stats) {
            if (isset($stats['availability_percent'])) {
                $total_availability += $stats['availability_percent'];
                $dhcp_count++;
            }
        }
        
        return [
            'system' => [
                'cpu_avg' => $system['cpu']['avg'] ?? null,
                'memory_avg' => $system['memory']['avg'] ?? null,
                'disk_avg' => $system['disk']['avg'] ?? null,
                'samples' => $system['cpu']['samples'] ?? 0
            ],
            'dhcp' => [
                'servers_monitored' => $dhcp_count,
                'overall_availability' => $dhcp_count > 0 ? round($total_availability / $dhcp_count, 2) : null
            ],
            'generated_at' => date('c')
        ];
    }
}
