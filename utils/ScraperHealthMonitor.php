<?php

class ScraperHealthMonitor {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->createTableIfNotExists();
    }
    
    private function createTableIfNotExists() {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS scraper_health (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                total_requests INT DEFAULT 0,
                successful_requests INT DEFAULT 0,
                failed_requests INT DEFAULT 0,
                blocked_requests INT DEFAULT 0,
                avg_response_time DECIMAL(10, 2),
                status ENUM('healthy', 'warning', 'critical') DEFAULT 'healthy',
                notes TEXT,
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB
        ");
    }
    
    public function recordMetrics($successful, $failed, $blocked, $avgResponseTime) {
        $total = $successful + $failed + $blocked;
        $successRate = $total > 0 ? ($successful / $total) * 100 : 0;
        
        $status = 'healthy';
        if ($successRate < 50 || $blocked > 5) {
            $status = 'critical';
        } elseif ($successRate < 80 || $blocked > 0) {
            $status = 'warning';
        }
        
        $notes = "Success rate: " . round($successRate, 2) . "%";
        
        $stmt = $this->db->prepare("
            INSERT INTO scraper_health 
            (total_requests, successful_requests, failed_requests, blocked_requests, avg_response_time, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $total,
            $successful,
            $failed,
            $blocked,
            $avgResponseTime,
            $status,
            $notes
        ]);
        
        if ($status === 'critical') {
            Logger::error("Scraper health is CRITICAL!  Success rate: {$successRate}%, Blocked: {$blocked}");
            $this->sendAlert($notes);
        } elseif ($status === 'warning') {
            Logger::warning("Scraper health WARNING.  Success rate: {$successRate}%, Blocked: {$blocked}");
        }
    }
    
    private function sendAlert($message) {
        // Integrar con NotificationService para enviar alertas
        $notificationService = new NotificationService();
        // Implementar método de alerta en NotificationService
    }
    
    public function getRecentHealth($hours = 24) {
        $stmt = $this->db->prepare("
            SELECT * FROM scraper_health 
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp DESC
        ");
        $stmt->execute([$hours]);
        return $stmt->fetchAll();
    }
}