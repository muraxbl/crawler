<?php

class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
public function create($data) {
    try {
        // Verificar si el producto ya existe por URL
        $stmt = $this->db->prepare("SELECT id, current_price FROM products WHERE url = ?");
        $stmt->execute([$data['url']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Producto existe - actualizar precio si cambió
            $oldPrice = (float)$existing['current_price'];
            $newPrice = (float)$data['price'];
            
            if (abs($oldPrice - $newPrice) > 0.01) {
                // Precio cambió - actualizar y registrar
                $stmt = $this->db->prepare("
                    UPDATE products 
                    SET current_price = ?, last_scraped_at = NOW(), updated_at = NOW()
                    WHERE id = ? 
                ");
                $stmt->execute([$newPrice, $existing['id']]);
                
                // Registrar cambio de precio
                $stmt = $this->db->prepare("
                    INSERT INTO price_history (product_id, old_price, new_price)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$existing['id'], $oldPrice, $newPrice]);
                
                Logger:: info("Updated product price: {$data['name']} - {$oldPrice}€ → {$newPrice}€");
                
                // ENVIAR EMAIL DE ALERTA
                if (defined('SEND_PRICE_ALERTS') && SEND_PRICE_ALERTS) {
                    try {
                        require_once BASE_PATH . '/services/EmailService.php';
                        $emailService = new EmailService();
                        
                        // Preparar datos completos del producto para el email
                        $productData = [
                            'id' => $existing['id'],
                            'name' => $data['name'],
                            'reference' => $data['reference'] ??  'N/A',
                            'url' => $data['url']
                        ];
                        
                        $emailService->sendPriceChangeAlert($productData, $oldPrice, $newPrice);
                        Logger::info("Price change alert sent for product: {$data['name']}");
                    } catch (Exception $e) {
                        Logger::error("Failed to send price alert email: " . $e->getMessage());
                        // No lanzar excepción para que no afecte al scraping
                    }
                }
                
                return ['id' => $existing['id'], 'action' => 'updated'];
            } else {
                // Solo actualizar fecha de scraping
                $stmt = $this->db->prepare("UPDATE products SET last_scraped_at = NOW() WHERE id = ?");
                $stmt->execute([$existing['id']]);
                return ['id' => $existing['id'], 'action' => 'unchanged'];
            }
        }
        
        // Producto nuevo - insertar
        $stmt = $this->db->prepare("
            INSERT INTO products (url, name, reference, current_price, last_scraped_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['url'],
            $data['name'],
            $data['reference'] ?? null,
            $data['price']
        ]);
        
        $productId = $this->db->lastInsertId();
        Logger::info("Created new product: {$data['name']} - {$data['price']}€");
        
        return ['id' => $productId, 'action' => 'created'];
        
    } catch (Exception $e) {
        Logger::error("Error creating/updating product: " . $e->getMessage());
        throw $e;
    }
}    
    public function getByUrl($url) {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE url = ? ");
        $stmt->execute([$url]);
        return $stmt->fetch();
    }
    
    public function getAll($status = 'active', $limit = null, $offset = 0) {
        $sql = "SELECT * FROM products WHERE status = ?  ORDER BY updated_at DESC";
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = [$status];
        if ($limit) {
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function updatePrice($productId, $newPrice) {
        $stmt = $this->db->prepare("SELECT current_price FROM products WHERE id = ? ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (! $product) {
            return false;
        }
        
        $oldPrice = $product['current_price'];
        
        // Solo actualizar si el precio cambió
        if (abs($oldPrice - $newPrice) > 0.01) {
            // Actualizar precio
            $stmt = $this->db->prepare("
                UPDATE products 
                SET current_price = ?, last_scraped_at = NOW() 
                WHERE id = ? 
            ");
            $stmt->execute([$newPrice, $productId]);
            
            // Registrar en histórico
            $stmt = $this->db->prepare("
                INSERT INTO price_history (product_id, old_price, new_price)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$productId, $oldPrice, $newPrice]);
            
            return [
                'changed' => true,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'history_id' => $this->db->lastInsertId()
            ];
        }
        
        // Actualizar fecha sin cambio de precio
        $stmt = $this->db->prepare("UPDATE products SET last_scraped_at = NOW() WHERE id = ?");
        $stmt->execute([$productId]);
        
        return ['changed' => false];
    }
    
    public function getPriceHistory($productId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM price_history 
            WHERE product_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getStats() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
                AVG(current_price) as avg_price,
                MIN(current_price) as min_price,
                MAX(current_price) as max_price
            FROM products
        ");
        return $stmt->fetch();
    }
}