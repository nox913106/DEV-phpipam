<?php
/**
 * NetworkStats.php
 * 
 * 網路流量統計類別
 * 收集網路介面的流量資訊
 * 
 * @author Jason Cheng
 * @created 2025-12-02
 */

class NetworkStats {
    
    /**
     * 取得網路統計資訊
     * 
     * @param string $interface 網路介面名稱（預設自動偵測）
     * @return array 網路統計資訊
     */
    public static function getStats($interface = null) {
        if ($interface === null) {
            $interface = self::getPrimaryInterface();
        }
        
        $current_stats = self::getCurrentStats($interface);
        
        return [
            'interface' => $interface,
            'current' => $current_stats,
            'last_24h' => self::calculate24hStats($interface, $current_stats)
        ];
    }
    
    /**
     * 取得主要網路介面
     * 
     * @return string 介面名稱
     */
    private static function getPrimaryInterface() {
        // 嘗試取得預設路由的網路介面
        $output = shell_exec("ip route show default 2>/dev/null | awk '/default/ {print $5}'");
        if ($output && trim($output)) {
            return trim($output);
        }
        
        // 備用：取得第一個非 lo 介面
        $interfaces = self::getAllInterfaces();
        foreach ($interfaces as $if => $stats) {
            if ($if !== 'lo') {
                return $if;
            }
        }
        
        return 'eth0'; // 預設值
    }
    
    /**
     * 取得所有網路介面
     * 
     * @return array 所有介面及其統計資訊
     */
    private static function getAllInterfaces() {
        $interfaces = [];
        
        if (file_exists('/proc/net/dev')) {
            $lines = file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            // 跳過前兩行標題
            for ($i = 2; $i < count($lines); $i++) {
                $line = $lines[$i];
                
                // 解析介面名稱和統計資訊
                if (preg_match('/^\s*(\w+):\s*(.+)$/', $line, $matches)) {
                    $interface = $matches[1];
                    $stats = preg_split('/\s+/', trim($matches[2]));
                    
                    if (count($stats) >= 8) {
                        $interfaces[$interface] = [
                            'rx_bytes' => (int)$stats[0],
                            'rx_packets' => (int)$stats[1],
                            'tx_bytes' => (int)$stats[8],
                            'tx_packets' => (int)$stats[9]
                        ];
                    }
                }
            }
        }
        
        return $interfaces;
    }
    
    /**
     * 取得指定介面的當前統計資訊
     * 
     * @param string $interface 介面名稱
     * @return array 統計資訊
     */
    private static function getCurrentStats($interface) {
        $interfaces = self::getAllInterfaces();
        
        if (isset($interfaces[$interface])) {
            return $interfaces[$interface];
        }
        
        return [
            'rx_bytes' => 0,
            'rx_packets' => 0,
            'tx_bytes' => 0,
            'tx_packets' => 0
        ];
    }
    
    /**
     * 計算 24 小時流量統計
     * 
     * @param string $interface 介面名稱
     * @param array $current_stats 當前統計資訊
     * @return array 24 小時統計（簡化版：返回當前值）
     */
    private static function calculate24hStats($interface, $current_stats) {
        // TODO: 實作真正的 24 小時統計需要資料庫儲存歷史資料
        // 目前先返回當前累積值作為示意
        
        // 可以從資料庫讀取 24 小時前的資料並計算差異
        // 這裡先返回當前的累積值
        return [
            'rx_bytes' => $current_stats['rx_bytes'],
            'tx_bytes' => $current_stats['tx_bytes'],
            'rx_packets' => $current_stats['rx_packets'],
            'tx_packets' => $current_stats['tx_packets'],
            'rx_mb' => round($current_stats['rx_bytes'] / 1024 / 1024, 2),
            'tx_mb' => round($current_stats['tx_bytes'] / 1024 / 1024, 2),
            'note' => '累積流量（需實作歷史資料儲存以計算真正的 24h 差異）'
        ];
    }
    
    /**
     * 儲存歷史統計資訊到資料庫（需要整合 phpIPAM 資料庫）
     * 
     * @param string $interface 介面名稱
     * @param array $stats 統計資訊
     * @return bool 是否成功
     */
    public static function saveToDatabase($interface, $stats) {
        // TODO: 實作資料庫儲存邏輯
        // INSERT INTO health_check_history (timestamp, interface, rx_bytes, tx_bytes, ...)
        // VALUES (NOW(), ?, ?, ?, ...)
        
        return true;
    }
}
