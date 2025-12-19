<?php
/**
 * api_dhcp_config.php - DHCP 伺服器配置管理 API
 * 
 * 提供 DHCP 伺服器列表的增刪修查功能
 * 配置儲存在 JSON 檔案中，可動態修改
 * 
 * @author Jason Cheng
 * @created 2025-12-19
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 配置檔路徑
define('CONFIG_FILE', __DIR__ . '/../config/dhcp_servers.json');

/**
 * 讀取 DHCP 伺服器配置
 */
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        // 預設配置
        $default = [
            ['ip' => '172.16.5.196', 'hostname' => 'DHCP-CH-HQ2', 'location' => '彰化總部2', 'enabled' => true],
            ['ip' => '172.23.13.10', 'hostname' => 'DHCP-CH-PGT', 'location' => '彰化埔鹽', 'enabled' => true],
            ['ip' => '172.23.174.5', 'hostname' => 'DHCP-TC-HQ', 'location' => '台中總部', 'enabled' => true],
            ['ip' => '172.23.199.150', 'hostname' => 'DHCP-TC-UAIC', 'location' => '台中', 'enabled' => true],
            ['ip' => '172.23.110.1', 'hostname' => 'DHCP-TP-XY', 'location' => '台北', 'enabled' => true],
            ['ip' => '172.23.94.254', 'hostname' => 'DHCP-TP-BaoYu-CoreSW', 'location' => '台北寶裕', 'enabled' => true],
        ];
        saveConfig($default);
        return $default;
    }
    
    $content = file_get_contents(CONFIG_FILE);
    return json_decode($content, true) ?: [];
}

/**
 * 儲存 DHCP 伺服器配置
 */
function saveConfig($config) {
    $dir = dirname(CONFIG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 驗證 IP 格式
 */
function validateIp($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * 取得所有伺服器
 */
function getAll() {
    $servers = loadConfig();
    return ['success' => true, 'data' => $servers, 'count' => count($servers)];
}

/**
 * 取得單一伺服器
 */
function getOne($ip) {
    $servers = loadConfig();
    foreach ($servers as $server) {
        if ($server['ip'] === $ip) {
            return ['success' => true, 'data' => $server];
        }
    }
    return ['success' => false, 'error' => 'Server not found'];
}

/**
 * 新增伺服器
 */
function addServer($data) {
    if (empty($data['ip'])) {
        return ['success' => false, 'error' => 'IP is required'];
    }
    
    if (!validateIp($data['ip'])) {
        return ['success' => false, 'error' => 'Invalid IP format'];
    }
    
    $servers = loadConfig();
    
    // 檢查是否已存在
    foreach ($servers as $server) {
        if ($server['ip'] === $data['ip']) {
            return ['success' => false, 'error' => 'Server already exists'];
        }
    }
    
    $newServer = [
        'ip' => $data['ip'],
        'hostname' => $data['hostname'] ?? '',
        'location' => $data['location'] ?? '',
        'enabled' => isset($data['enabled']) ? (bool)$data['enabled'] : true
    ];
    
    $servers[] = $newServer;
    saveConfig($servers);
    
    // 同時更新 HistoryCollector 的 hostnames
    updateHistoryCollector($servers);
    
    return ['success' => true, 'data' => $newServer, 'message' => 'Server added'];
}

/**
 * 更新伺服器
 */
function updateServer($ip, $data) {
    $servers = loadConfig();
    $found = false;
    
    foreach ($servers as &$server) {
        if ($server['ip'] === $ip) {
            if (isset($data['hostname'])) $server['hostname'] = $data['hostname'];
            if (isset($data['location'])) $server['location'] = $data['location'];
            if (isset($data['enabled'])) $server['enabled'] = (bool)$data['enabled'];
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'error' => 'Server not found'];
    }
    
    saveConfig($servers);
    updateHistoryCollector($servers);
    
    return ['success' => true, 'message' => 'Server updated'];
}

/**
 * 刪除伺服器
 */
function deleteServer($ip) {
    $servers = loadConfig();
    $newServers = array_filter($servers, function($s) use ($ip) {
        return $s['ip'] !== $ip;
    });
    
    if (count($newServers) === count($servers)) {
        return ['success' => false, 'error' => 'Server not found'];
    }
    
    saveConfig(array_values($newServers));
    updateHistoryCollector(array_values($newServers));
    
    return ['success' => true, 'message' => 'Server deleted'];
}

/**
 * 同步更新 HistoryCollector
 */
function updateHistoryCollector($servers) {
    // 這個函數用於在容器內更新程式碼中的 hostnames
    // 由於我們現在使用 JSON 配置，HistoryCollector 也應該從 JSON 讀取
    // 這裡暫時不做任何事，因為我們需要修改 HistoryCollector 來從 JSON 讀取
}

/**
 * 取得啟用的伺服器 IP 列表
 */
function getEnabledIps() {
    $servers = loadConfig();
    $ips = [];
    foreach ($servers as $server) {
        if ($server['enabled']) {
            $ips[] = $server['ip'];
        }
    }
    return ['success' => true, 'data' => $ips, 'count' => count($ips)];
}

// 主程式
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $ip = $_GET['ip'] ?? '';
    
    // 取得 POST/PUT 資料
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    
    switch ($method) {
        case 'GET':
            if ($ip) {
                $result = getOne($ip);
            } elseif ($action === 'enabled') {
                $result = getEnabledIps();
            } else {
                $result = getAll();
            }
            break;
            
        case 'POST':
            $result = addServer($input);
            break;
            
        case 'PUT':
            if (!$ip) {
                $result = ['success' => false, 'error' => 'IP is required for update'];
            } else {
                $result = updateServer($ip, $input);
            }
            break;
            
        case 'DELETE':
            if (!$ip) {
                $result = ['success' => false, 'error' => 'IP is required for delete'];
            } else {
                $result = deleteServer($ip);
            }
            break;
            
        default:
            $result = ['success' => false, 'error' => 'Method not allowed'];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
