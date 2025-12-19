<?php
require_once(__DIR__ . '/StatsCalculator.php');

class HistoryCollector {
    private static function loadDhcpConfig() {
        $paths = [
            __DIR__ . '/../config/dhcp_servers.json',
            '/health_check/config/dhcp_servers.json',
            '/phpipam/health_dashboard/config/dhcp_servers.json'
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                $data = json_decode(file_get_contents($p), true);
                if ($data) return $data;
            }
        }
        return [
            ["ip" => "172.16.5.196", "hostname" => "DHCP-CH-HQ2"],
            ["ip" => "172.23.13.10", "hostname" => "DHCP-CH-PGT"],
            ["ip" => "172.23.174.5", "hostname" => "DHCP-TC-HQ"],
            ["ip" => "172.23.199.150", "hostname" => "DHCP-TC-UAIC"],
            ["ip" => "172.23.110.1", "hostname" => "DHCP-TP-XY"],
            ["ip" => "172.23.94.254", "hostname" => "DHCP-TP-BaoYu"]
        ];
    }
    
    public static function collectAll($db, $dhcp_ips = null) {
        return ['system' => self::collectSystemResources($db), 'dhcp' => self::collectDhcpStats($db, $dhcp_ips), 'collected_at' => date('c')];
    }
    
    public static function collectSystemResources($db) {
        try {
            $load = sys_getloadavg();
            $cpu_cores = (int)shell_exec("nproc 2>/dev/null") ?: 1;
            $cpu_usage = ($load[0] / $cpu_cores) * 100;
            
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m1);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m2);
            $mem_total = isset($m1[1]) ? (int)$m1[1] : 0;
            $mem_available = isset($m2[1]) ? (int)$m2[1] : 0;
            $mem_used = $mem_total - $mem_available;
            $mem_usage = $mem_total > 0 ? ($mem_used / $mem_total) * 100 : 0;
            
            $disk_total = disk_total_space('/');
            $disk_free = disk_free_space('/');
            $disk_used = $disk_total - $disk_free;
            $disk_usage = $disk_total > 0 ? ($disk_used / $disk_total) * 100 : 0;
            
            $sql = "INSERT INTO health_check_system_history (cpu_usage_percent, cpu_load_1min, cpu_load_5min, cpu_load_15min, memory_usage_percent, memory_used_mb, memory_total_mb, disk_usage_percent, disk_used_gb, disk_total_gb) VALUES (:cpu, :l1, :l5, :l15, :mem, :mem_used, :mem_total, :disk, :disk_used, :disk_total)";
            $stmt = $db->prepare($sql);
            $stmt->execute([':cpu' => round($cpu_usage, 2), ':l1' => $load[0], ':l5' => $load[1], ':l15' => $load[2], ':mem' => round($mem_usage, 2), ':mem_used' => round($mem_used/1024), ':mem_total' => round($mem_total/1024), ':disk' => round($disk_usage, 2), ':disk_used' => round($disk_used/1024/1024/1024, 2), ':disk_total' => round($disk_total/1024/1024/1024, 2)]);
            return ['success' => true, 'cpu' => round($cpu_usage, 2), 'memory' => round($mem_usage, 2), 'disk' => round($disk_usage, 2)];
        } catch (Exception $e) { return ['success' => false, 'error' => $e->getMessage()]; }
    }
    
    public static function collectDhcpStats($db, $dhcp_ips = null) {
        $config = self::loadDhcpConfig();
        $servers = array_filter($config, function($s) { return !isset($s['enabled']) || $s['enabled']; });
        
        $hostnames = [];
        $ips = [];
        foreach ($servers as $s) {
            $ips[] = $s['ip'];
            $hostnames[$s['ip']] = $s['hostname'] ?? null;
        }
        
        $results = [];
        try {
            $sql = "INSERT INTO health_check_dhcp_history (dhcp_ip, dhcp_hostname, reachable, latency_ms, packet_loss_percent, packets_sent, packets_received) VALUES (:ip, :host, :reach, :lat, :loss, :sent, :recv)";
            $stmt = $db->prepare($sql);
            foreach ($ips as $ip) {
                $output = []; $ret = 0;
                exec("ping -c 4 -W 2 " . escapeshellarg($ip) . " 2>&1", $output, $ret);
                $out_str = implode("\n", $output);
                $reachable = ($ret === 0) ? 1 : 0;
                $latency = 0; $loss = 100; $sent = 4; $recv = 0;
                if (preg_match('/(\d+) packets transmitted, (\d+) received, ([\d.]+)% packet loss/', $out_str, $m)) { $sent = (int)$m[1]; $recv = (int)$m[2]; $loss = (float)$m[3]; }
                if (preg_match('/rtt min\/avg\/max\/mdev = [\d.]+\/([\d.]+)\//', $out_str, $m)) { $latency = (float)$m[1]; }
                $stmt->execute([':ip' => $ip, ':host' => $hostnames[$ip] ?? null, ':reach' => $reachable, ':lat' => $latency, ':loss' => $loss, ':sent' => $sent, ':recv' => $recv]);
                $results[] = ['ip' => $ip, 'hostname' => $hostnames[$ip] ?? null, 'reachable' => $reachable, 'latency' => $latency];
            }
            return ['success' => true, 'count' => count($results), 'servers' => $results];
        } catch (Exception $e) { return ['success' => false, 'error' => $e->getMessage()]; }
    }
    
    public static function purgeOldRecords($db, $days = 7) {
        try {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $db->exec("DELETE FROM health_check_system_history WHERE recorded_at < '{$cutoff}'");
            $db->exec("DELETE FROM health_check_dhcp_history WHERE recorded_at < '{$cutoff}'");
            return ['success' => true];
        } catch (Exception $e) { return ['success' => false, 'error' => $e->getMessage()]; }
    }
}
