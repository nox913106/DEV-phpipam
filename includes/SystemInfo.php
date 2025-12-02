<?php
/**
 * SystemInfo.php
 * 
 * 系統資訊收集類別
 * 收集 phpIPAM 主機的系統資源使用情況
 * 
 * @author Jason Cheng
 * @created 2025-12-02
 */

class SystemInfo {
    
    /**
     * 取得完整的系統資訊
     * 
     * @return array 系統資訊陣列
     */
    public static function getAll() {
        return [
            'host_info' => self::getHostInfo(),
            'system_resources' => self::getSystemResources()
        ];
    }
    
    /**
     * 取得主機基本資訊
     * 
     * @return array 主機資訊
     */
    public static function getHostInfo() {
        $hostname = gethostname();
        $uptime_seconds = self::getUptime();
        
        return [
            'hostname' => $hostname,
            'os' => self::getOSInfo(),
            'kernel' => php_uname('r'),
            'uptime_seconds' => $uptime_seconds,
            'uptime_formatted' => self::formatUptime($uptime_seconds)
        ];
    }
    
    /**
     * 取得作業系統資訊
     * 
     * @return string OS 資訊
     */
    private static function getOSInfo() {
        if (file_exists('/etc/os-release')) {
            $os_release = parse_ini_file('/etc/os-release');
            return $os_release['PRETTY_NAME'] ?? php_uname('s') . ' ' . php_uname('r');
        }
        return php_uname('s') . ' ' . php_uname('r');
    }
    
    /**
     * 取得系統運行時間（秒）
     * 
     * @return int 運行時間（秒）
     */
    private static function getUptime() {
        if (file_exists('/proc/uptime')) {
            $uptime_content = file_get_contents('/proc/uptime');
            $uptime_array = explode(' ', $uptime_content);
            return (int)$uptime_array[0];
        }
        return 0;
    }
    
    /**
     * 格式化運行時間
     * 
     * @param int $seconds 秒數
     * @return string 格式化的時間字串
     */
    private static function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return sprintf("%d days %d hours %d minutes", $days, $hours, $minutes);
    }
    
    /**
     * 取得系統資源使用情況
     * 
     * @return array 資源使用情況
     */
    public static function getSystemResources() {
        return [
            'cpu' => self::getCpuUsage(),
            'memory' => self::getMemoryUsage(),
            'disk' => self::getDiskUsage()
        ];
    }
    
    /**
     * 取得 CPU 使用率
     * 
     * @return array CPU 資訊
     */
    private static function getCpuUsage() {
        $cpu_usage = 0;
        $cores = 1;
        $load_average = [0, 0, 0];
        
        // 取得 CPU 核心數
        if (file_exists('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        }
        
        // 取得負載平均
        if (function_exists('sys_getloadavg')) {
            $load_average = sys_getloadavg();
        }
        
        // 計算 CPU 使用率（基於 1 分鐘負載平均）
        $cpu_usage = ($load_average[0] / $cores) * 100;
        
        return [
            'usage_percent' => round($cpu_usage, 2),
            'cores' => $cores,
            'load_average' => [
                round($load_average[0], 2),
                round($load_average[1], 2),
                round($load_average[2], 2)
            ]
        ];
    }
    
    /**
     * 取得記憶體使用情況
     * 
     * @return array 記憶體資訊
     */
    private static function getMemoryUsage() {
        $mem_total = 0;
        $mem_free = 0;
        $mem_available = 0;
        
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            
            // 解析 meminfo
            if (preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches)) {
                $mem_total = (int)$matches[1]; // KB
            }
            if (preg_match('/MemFree:\s+(\d+)/', $meminfo, $matches)) {
                $mem_free = (int)$matches[1]; // KB
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches)) {
                $mem_available = (int)$matches[1]; // KB
            }
        }
        
        $mem_used = $mem_total - $mem_available;
        $usage_percent = $mem_total > 0 ? ($mem_used / $mem_total) * 100 : 0;
        
        return [
            'total_mb' => round($mem_total / 1024, 2),
            'used_mb' => round($mem_used / 1024, 2),
            'free_mb' => round($mem_available / 1024, 2),
            'usage_percent' => round($usage_percent, 2)
        ];
    }
    
    /**
     * 取得硬碟使用情況
     * 
     * @param string $path 要檢查的路徑（預設為根目錄）
     * @return array 硬碟資訊
     */
    private static function getDiskUsage($path = '/') {
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $usage_percent = $total > 0 ? ($used / $total) * 100 : 0;
        
        return [
            'path' => $path,
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'usage_percent' => round($usage_percent, 2)
        ];
    }
}
