-- ============================================================
-- phpIPAM Health Check - 歷史數據資料表
-- 
-- 用於儲存系統資源和 DHCP 伺服器的歷史監控數據
-- 以計算 24 小時統計 (avg, min, max)
-- 
-- @author Jason Cheng
-- @created 2025-12-18
-- ============================================================

-- 系統資源歷史記錄表
CREATE TABLE IF NOT EXISTS health_check_system_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- CPU 指標
    cpu_usage_percent DECIMAL(5,2) COMMENT 'CPU 使用率 (%)',
    cpu_load_1min DECIMAL(6,2) COMMENT '1 分鐘負載平均',
    cpu_load_5min DECIMAL(6,2) COMMENT '5 分鐘負載平均',
    cpu_load_15min DECIMAL(6,2) COMMENT '15 分鐘負載平均',
    
    -- 記憶體指標
    memory_usage_percent DECIMAL(5,2) COMMENT '記憶體使用率 (%)',
    memory_used_mb INT COMMENT '已使用記憶體 (MB)',
    memory_total_mb INT COMMENT '總記憶體 (MB)',
    
    -- 磁碟指標
    disk_usage_percent DECIMAL(5,2) COMMENT '磁碟使用率 (%)',
    disk_used_gb DECIMAL(10,2) COMMENT '已使用磁碟 (GB)',
    disk_total_gb DECIMAL(10,2) COMMENT '總磁碟空間 (GB)',
    
    -- 網路流量指標
    network_rx_bytes BIGINT COMMENT '接收位元組數',
    network_tx_bytes BIGINT COMMENT '傳送位元組數',
    network_interface VARCHAR(32) COMMENT '網路介面名稱',
    
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_recorded_at_desc (recorded_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='系統資源歷史監控數據';

-- DHCP 伺服器歷史記錄表
CREATE TABLE IF NOT EXISTS health_check_dhcp_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- DHCP 伺服器識別
    dhcp_ip VARCHAR(45) NOT NULL COMMENT 'DHCP 伺服器 IP',
    dhcp_hostname VARCHAR(64) COMMENT 'DHCP 伺服器主機名稱',
    
    -- 連線指標
    reachable TINYINT(1) COMMENT '是否可達 (1=是, 0=否)',
    latency_ms DECIMAL(10,2) COMMENT '平均延遲 (ms)',
    packet_loss_percent DECIMAL(5,2) COMMENT '封包遺失率 (%)',
    packets_sent INT COMMENT '發送封包數',
    packets_received INT COMMENT '接收封包數',
    
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_dhcp_ip (dhcp_ip),
    INDEX idx_dhcp_ip_recorded (dhcp_ip, recorded_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='DHCP 伺服器連線歷史監控數據';

-- ============================================================
-- 資料清理排程建議
-- 建議設定 cron job 定期清理超過 7 天的舊資料：
-- 
-- DELETE FROM health_check_system_history 
-- WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
-- 
-- DELETE FROM health_check_dhcp_history 
-- WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
-- ============================================================
