<?php
/**
 * DhcpChecker.php
 * 
 * DHCP 伺服器連線檢查類別
 * 使用 ping 檢查 DHCP 伺服器的連線狀態
 * 
 * @author Jason Cheng
 * @created 2025-12-02
 */

class DhcpChecker {
    
    /**
     * 檢查單一或多個 DHCP 伺服器
     * 
     * @param string|array $ips IP 位址（字串或陣列）
     * @param int $count Ping 次數
     * @param int $timeout 逾時秒數
     * @return array 檢查結果
     */
    public static function check($ips, $count = 4, $timeout = 2) {
        // 標準化輸入為陣列
        if (is_string($ips)) {
            // 支援逗號分隔的 IP 字串
            $ips = array_map('trim', explode(',', $ips));
        }
        
        $results = [];
        foreach ($ips as $ip) {
            $results[] = self::checkSingle($ip, $count, $timeout);
        }
        
        return $results;
    }
    
    /**
     * 檢查單一 DHCP 伺服器
     * 
     * @param string $ip IP 位址
     * @param int $count Ping 次數
     * @param int $timeout 逾時秒數
     * @return array 檢查結果
     */
    private static function checkSingle($ip, $count, $timeout) {
        // 驗證 IP 格式
        if (!self::validateIp($ip)) {
            return [
                'ip' => $ip,
                'status' => 'error',
                'reachable' => false,
                'error' => 'Invalid IP address format'
            ];
        }
        
        // 執行 ping 檢查
        $ping_result = self::ping($ip, $count, $timeout);
        
        return array_merge(['ip' => $ip], $ping_result);
    }
    
    /**
     * 驗證 IP 位址格式
     * 
     * @param string $ip IP 位址
     * @return bool 是否有效
     */
    private static function validateIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * 執行 ping 指令
     * 
     * @param string $ip IP 位址
     * @param int $count Ping 次數
     * @param int $timeout 逾時秒數
     * @return array Ping 結果
     */
    private static function ping($ip, $count, $timeout) {
        // 安全驗證：確保 IP 格式正確（防止指令注入）
        $ip = escapeshellarg($ip);
        
        // 建立 ping 指令
        $command = sprintf(
            "ping -c %d -W %d %s 2>&1",
            (int)$count,
            (int)$timeout,
            $ip
        );
        
        // 執行指令
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        // 解析結果
        return self::parsePingOutput($output, $return_code, $count);
    }
    
    /**
     * 解析 ping 指令輸出
     * 
     * @param array $output 指令輸出
     * @param int $return_code 返回碼
     * @param int $expected_count 預期 ping 次數
     * @return array 解析後的結果
     */
    private static function parsePingOutput($output, $return_code, $expected_count) {
        $output_text = implode("\n", $output);
        
        // Ping 失敗
        if ($return_code !== 0) {
            // 嘗試從輸出中提取錯誤訊息
            $error = 'Host unreachable';
            if (preg_match('/(Destination Host Unreachable|Network is unreachable)/i', $output_text, $matches)) {
                $error = $matches[1];
            }
            
            return [
                'status' => 'error',
                'reachable' => false,
                'error' => $error
            ];
        }
        
        // 解析統計資訊
        // 範例: "4 packets transmitted, 4 received, 0% packet loss, time 3003ms"
        if (preg_match('/(\d+) packets transmitted, (\d+) received, ([\d.]+)% packet loss/', $output_text, $matches)) {
            $transmitted = (int)$matches[1];
            $received = (int)$matches[2];
            $packet_loss = (float)$matches[3];
        } else {
            // 如果無法解析，假設全部成功
            $transmitted = $expected_count;
            $received = $expected_count;
            $packet_loss = 0;
        }
        
        // 解析平均延遲
        // 範例: "rtt min/avg/max/mdev = 0.123/0.145/0.167/0.015 ms"
        $avg_latency = 0;
        if (preg_match('/rtt min\/avg\/max\/mdev = ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+) ms/', $output_text, $matches)) {
            $avg_latency = (float)$matches[2];
        }
        
        // 判斷是否可連線（至少收到一個回應）
        $reachable = $received > 0;
        
        return [
            'status' => $reachable ? 'online' : 'offline',
            'reachable' => $reachable,
            'response_time_ms' => round($avg_latency, 2),
            'avg_latency_ms' => round($avg_latency, 2),
            'packet_loss_percent' => round($packet_loss, 2),
            'packets_sent' => $transmitted,
            'packets_received' => $received,
            'check_method' => 'ping'
        ];
    }
}
