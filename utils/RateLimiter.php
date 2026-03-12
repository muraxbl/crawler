<?php

class RateLimiter {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = Database:: getInstance()->getConnection();
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $stmt = $this->db->query("SELECT config_key, config_value FROM config WHERE config_key LIKE 'rate_limit%'");
        $this->config = [];
        while ($row = $stmt->fetch()) {
            $this->config[$row['config_key']] = $row['config_value'];
        }
    }
    
    public function checkLimit($identifier, $endpoint = 'default') {
        // Limpiar registros antiguos (más de 1 hora)
        $this->cleanOldRecords();
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, MIN(window_start) as first_request
            FROM rate_limits
            WHERE ip_address = ? AND endpoint = ?  AND window_start > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$identifier, $endpoint]);
        $result = $stmt->fetch();
        
        $requestsPerMinute = $this->config['rate_limit_requests_per_minute'] ?? 10;
        
        if ($result['count'] >= $requestsPerMinute) {
            return [
                'allowed' => false,
                'retry_after' => 60,
                'message' => 'Rate limit exceeded.  Try again later.'
            ];
        }
        
        // Registrar la petición
        $this->recordRequest($identifier, $endpoint);
        
        return [
            'allowed' => true,
            'remaining' => $requestsPerMinute - ($result['count'] + 1)
        ];
    }
    
    private function recordRequest($identifier, $endpoint) {
        $stmt = $this->db->prepare("
            INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([$identifier, $endpoint]);
    }
    
    private function cleanOldRecords() {
        $this->db->exec("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    }
    
    public static function getClientIdentifier() {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}