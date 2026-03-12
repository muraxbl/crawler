<?php

class NotificationService {
    private $db;
    private $fromEmail;
    private $toEmail;
    
    public function __construct() {
        $this->db = Database:: getInstance()->getConnection();
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $stmt = $this->db->prepare("SELECT config_value FROM config WHERE config_key = 'notification_email'");
        $stmt->execute();
        $result = $stmt->fetch();
        $this->toEmail = $result['config_value'] ?? 'admin@example.com';
        $this->fromEmail = 'noreply@greenice-monitor.com';
    }
    
    public function sendPriceChangeNotification($productId, $historyId) {
        // Obtener datos del producto y cambio de precio
        $stmt = $this->db->prepare("
            SELECT p.*, ph.old_price, ph.new_price, ph.price_change, ph.percentage_change
            FROM products p
            JOIN price_history ph ON ph.product_id = p.id
            WHERE p.id = ?  AND ph.id = ?
        ");
        $stmt->execute([$productId, $historyId]);
        $data = $stmt->fetch();
        
        if (!$data) {
            Logger::error("Product or history not found for notification");
            return false;
        }
        
        $subject = sprintf(
            "Cambio de precio:  %s (%s%. 2f€)",
            $data['name'],
            $data['price_change'] > 0 ? '+' :  '',
            $data['price_change']
        );
        
        $message = $this->buildEmailTemplate($data);
        
        $headers = [
            'From' => $this->fromEmail,
            'Reply-To' => $this->fromEmail,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }
        
        $sent = mail($this->toEmail, $subject, $message, $headerString);
        
        // Registrar notificación
        $stmt = $this->db->prepare("
            INSERT INTO notifications (product_id, price_history_id, email_to, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $productId,
            $historyId,
            $this->toEmail,
            $sent ? 'sent' : 'failed'
        ]);
        
        if ($sent) {
            Logger:: info("Price change notification sent for product ID: {$productId}");
        } else {
            Logger:: error("Failed to send notification for product ID: {$productId}");
        }
        
        return $sent;
    }
    
    private function buildEmailTemplate($data) {
        $priceChangeClass = $data['price_change'] > 0 ? 'increase' : 'decrease';
        $priceChangeIcon = $data['price_change'] > 0 ? '📈' : '📉';
        
        return <<<HTML
<! DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        . container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
        . content { background: #f9f9f9; padding: 20px; border:  1px solid #ddd; }
        .product-name { font-size: 18px; font-weight: bold; margin-bottom: 10px; }
        .price-box { background: white; padding: 15px; margin:  15px 0; border-left: 4px solid #4CAF50; }
        .price { font-size: 24px; font-weight: bold; }
        .increase { color: #f44336; }
        .decrease { color: #4CAF50; }
        . footer { text-align: center; padding: 20px; font-size: 12px; color: #777; }
        a { color: #4CAF50; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$priceChangeIcon} Alerta de Cambio de Precio</h1>
        </div>
        <div class="content">
            <div class="product-name">{$data['name']}</div>
            <p><strong>Referencia:</strong> {$data['reference']}</p>
            
            <div class="price-box">
                <p><strong>Precio anterior:</strong> <span class="price">{$data['old_price']}€</span></p>
                <p><strong>Precio actual:</strong> <span class="price {$priceChangeClass}">{$data['new_price']}€</span></p>
                <p><strong>Cambio:</strong> <span class="price {$priceChangeClass}">{$data['price_change']}€ ({$data['percentage_change']}%)</span></p>
            </div>
            
            <p><a href="{$data['url']}" target="_blank">Ver producto en GreenICE →</a></p>
            
            <p><small>Fecha de detección: {$data['updated_at']}</small></p>
        </div>
        <div class="footer">
            <p>GreenICE Price Monitor © 2025</p>
            <p>Este es un correo automático, por favor no responder.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}