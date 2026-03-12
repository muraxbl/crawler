<?php

class EmailService {
    private $fromEmail;
    private $fromName;
    private $adminEmail;
    private $recipients;

    
    public function __construct() {
        $this->fromEmail = EMAIL_FROM ??  'noreply@crawl.mihtml.es';
        $this->fromName = EMAIL_FROM_NAME ?? 'Masterled Price monitor';
        $this->adminEmail = ADMIN_EMAIL ?? 'luiscoves@homekit.es';
        $this->recipients = defined('ALERT_EMAIL_RECIPIENTS') ? ALERT_EMAIL_RECIPIENTS : null;
    }
    
    // ✅ NUEVO: Enviar resumen de múltiples cambios (UN SOLO EMAIL)
    public function sendPriceChangesSummary($priceChanges) {
        if (empty($priceChanges)) {
            Logger::info("No price changes to send");
            return true;
        }
        
        // Obtener destinatarios
        $recipients = null;
        if(! empty($this->recipients) && $this->recipients != null) {
            $recipients = $this->recipients;
        } else {
            $recipients = $this->adminEmail;
        }
        
        // Convertir a array
        if (is_string($recipients)) {
            $recipients = array_map('trim', explode(',', $recipients));
        }
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        
        // Filtrar emails válidos
        $recipients = array_filter($recipients, function($email) {
            return ! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        if (empty($recipients)) {
            Logger::error("No valid email recipients for summary");
            return false;
        }
        
        // Contar subidas y bajadas
        $increases = 0;
        $decreases = 0;
        foreach ($priceChanges as $change) {
            if ($change['price_change'] > 0) {
                $increases++;
            } else {
                $decreases++;
            }
        }
        
        $totalChanges = count($priceChanges);
        $subject = "📊 Monitorización de precios ML - {$totalChanges} productos actualizados";
        
        $body = $this->buildSummaryEmailBody($priceChanges, $increases, $decreases);
        
        // Enviar a cada destinatario
        $successCount = 0;
        foreach ($recipients as $recipient) {
            Logger::info("Sending price summary to:  {$recipient} - {$totalChanges} changes");
            
            if ($this->send($recipient, $subject, $body)) {
                $successCount++;
            }
        }
        
        Logger::info("📧 Price summary sent to {$successCount}/{" . count($recipients) . "} recipients ({$totalChanges} changes)");
        
        return $successCount > 0;
    }
    
    // ✅ NUEVO: Construir email de resumen con tabla de cambios
    private function buildSummaryEmailBody($priceChanges, $increases, $decreases) {
        $totalChanges = count($priceChanges);
        $currentDate = date('d/m/Y H:i:s');
        
        // Construir tabla de cambios
        $tableRows = '';
        foreach ($priceChanges as $change) {
            $isIncrease = $change['price_change'] > 0;
            $arrow = $isIncrease ? '📈' : '📉';
            $color = $isIncrease ? '#e74c3c' : '#27ae60';
            $sign = $isIncrease ? '+' : '';
            
            $productName = htmlspecialchars($change['name']);
            $productRef = htmlspecialchars($change['reference'] ?? 'N/A');
            $productUrl = htmlspecialchars($change['url']);
            
            $tableRows .= "
                <tr style='border-bottom: 1px solid #f0f0f0;'>
                    <td style='padding: 12px 8px;'>
                        <strong>{$productName}</strong><br>
                        <small style='color: #999;'>{$productRef}</small>
                    </td>
                    <td style='padding:  12px 8px; text-align: center; color: #999; text-decoration: line-through;'>
                        " . number_format($change['old_price'], 2) . "€
                    </td>
                    <td style='padding:  12px 8px; text-align: center; font-weight: bold;'>
                        " . number_format($change['new_price'], 2) . "€
                    </td>
                    <td style='padding: 12px 8px; text-align: center; color:  {$color}; font-weight: bold;'>
                        {$arrow} {$sign}" . number_format(abs($change['price_change']), 2) . "€<br>
                        <small>({$sign}" . number_format($change['percentage_change'], 2) . "%)</small>
                    </td>
                    <td style='padding: 12px 8px; text-align: center;'>
                        <a href='{$productUrl}' target='_blank' style='color: #667eea; text-decoration: none; font-weight: 500;'>Ver →</a>
                    </td>
                </tr>
            ";
        }
        
        return "
        <! DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background:  #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 900px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .header p { margin: 10px 0 0; opacity: 0.9; }
                .content { padding: 30px; }
                .stats-box { display: flex; justify-content: space-around; margin:  20px 0; }
                .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; flex: 1; margin: 0 10px; }
                .stat-number { font-size: 32px; font-weight: bold; margin: 10px 0; }
                . stat-label { color: #666; font-size: 14px; text-transform: uppercase; }
                .increase { color: #e74c3c; }
                .decrease { color: #27ae60; }
                table { width: 100%; background: white; border-collapse: collapse; margin: 20px 0; }
                th { background: #667eea; color: white; padding: 12px 8px; text-align: left; font-weight: 600; font-size: 14px; }
                . btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin:  10px 5px 0 0; }
                .footer { text-align: center; padding:  20px; background: #f8f9fa; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 Resumen de Cambios de Precios</h1>
                    <p>Masterled Price Monitor</p>
                </div>
                <div class='content'>
                    <div class='stats-box'>
                        <div class='stat-card'>
                            <div class='stat-number'>{$totalChanges}</div>
                            <div class='stat-label'>Total Cambios</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-number decrease'>📉 {$decreases}</div>
                            <div class='stat-label'>Bajadas</div>
                        </div>
                        <div class='stat-card'>
                            <div class='stat-number increase'>📈 {$increases}</div>
                            <div class='stat-label'>Subidas</div>
                        </div>
                    </div>
                    
                    <h2 style='margin-top: 30px; color: #333;'>Detalle de Cambios:</h2>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th style='text-align: center;'>Precio Anterior</th>
                                <th style='text-align: center;'>Precio Nuevo</th>
                                <th style='text-align: center;'>Cambio</th>
                                <th style='text-align: center;'>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$tableRows}
                        </tbody>
                    </table>
                    
                    <p style='color: #666; font-size:  14px; margin-top:  20px;'>
                        <strong>Fecha del escaneo: </strong> {$currentDate}
                    </p>
                    
                    <center>
                        <a href='https://crawl.mihtml.es/' class='btn'>Ver Dashboard Completo</a>
                    </center>
                </div>
                <div class='footer'>
                    <p>Masterled Price Monitor © 2025</p>
                    <p>Este email fue enviado automáticamente por el sistema de monitorización</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    // Método antiguo (mantener por compatibilidad, pero ya no se usará)
    public function sendPriceChangeAlert($product, $oldPrice, $newPrice, $recipients = null) {
        // Si no se pasan destinatarios, usar el de config
        if ($recipients === null) {
            if(! empty($this->recipients) && $this->recipients != null) {
              $recipients = $this->recipients;
            } else {
            $recipients = $this->adminEmail;
            }
        }
        
        // Convertir a array si es string
        if (is_string($recipients)) {
            $recipients = array_map('trim', explode(',', $recipients));
        }
        
        // Asegurar que sea array
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }
        
        // Filtrar emails vacíos y validar
        $recipients = array_filter($recipients, function($email) {
            return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        
        if (empty($recipients)) {
            Logger:: error("No valid email recipients configured");
            return false;
        }
        
        $change = $newPrice - $oldPrice;
        $percentageChange = (($change / $oldPrice) * 100);
        
        $subject = sprintf(
            "[GreenICE Monitor] %s precio:  %s",
            $change > 0 ? '📈 Subió' : '📉 Bajó',
            $product['name']
        );
        
        $body = $this->buildPriceChangeEmailBody($product, $oldPrice, $newPrice, $change, $percentageChange);
        
        // Enviar a cada destinatario
        $successCount = 0;
        $failCount = 0;
        
        foreach ($recipients as $recipient) {
            Logger::info("Sending price alert to: {$recipient} - Product: {$product['name']}");
            
            if ($this->send($recipient, $subject, $body)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        Logger::info("📧 Price alert summary:  {$successCount} sent, {$failCount} failed (Total: " . count($recipients) . " recipients)");
        
        // Retornar true si al menos uno se envió
        return $successCount > 0;
    }
    
    private function buildPriceChangeEmailBody($product, $oldPrice, $newPrice, $change, $percentageChange) {
        $changeIcon = $change > 0 ? '📈' : '📉';
        $changeColor = $change > 0 ? '#e74c3c' : '#27ae60';
        $changeText = $change > 0 ? 'SUBIÓ' : 'BAJÓ';
        
        return "
        <! DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .alert-box { background: white; border-left: 4px solid {$changeColor}; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .price-change { font-size: 24px; font-weight: bold; color: {$changeColor}; }
                .product-name { font-size: 18px; font-weight: bold; margin: 10px 0; }
                .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; padding: 20px; color: #999; font-size:  12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$changeIcon} Alerta de Cambio de Precio</h1>
                    <p>GreenICE Price Monitor</p>
                </div>
                <div class='content'>
                    <div class='alert-box'>
                        <div class='product-name'>{$product['name']}</div>
                        <p><strong>Referencia:</strong> {$product['reference']}</p>
                        
                        <table style='width: 100%; margin: 20px 0;'>
                            <tr>
                                <td><strong>Precio anterior:</strong></td>
                                <td style='text-align: right;'>" . number_format($oldPrice, 2) . "€</td>
                            </tr>
                            <tr>
                                <td><strong>Precio nuevo:</strong></td>
                                <td style='text-align: right; font-size: 20px; font-weight: bold; color: {$changeColor};'>" . number_format($newPrice, 2) . "€</td>
                            </tr>
                            <tr style='border-top: 2px solid #ddd;'>
                                <td><strong>{$changeText}:</strong></td>
                                <td style='text-align:  right;'>
                                    <span class='price-change'>{$changeIcon} " . number_format(abs($change), 2) . "€ (" . number_format($percentageChange, 2) . "%)</span>
                                </td>
                            </tr>
                        </table>
                        
                        <p><strong>Fecha:</strong> " . date('d/m/Y H:i: s') . "</p>
                        
                        <a href='{$product['url']}' class='btn' target='_blank'>Ver Producto en GreenICE</a>
                        <a href='https://crawl.mihtml.es/' class='btn' style='background: #764ba2;' target='_blank'>Ver en Dashboard</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>GreenICE Price Monitor © 2025</p>
                    <p>Este email fue enviado automáticamente por el sistema de monitorización</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function send($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            "From: {$this->fromName} <{$this->fromEmail}>",
            "Reply-To: {$this->fromEmail}",
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($success) {
            Logger::info("✓ Email sent successfully to {$to}");
        } else {
            Logger::error("✗ Failed to send email to {$to}");
        }
        
        return $success;
    }
}
?>