<?php

class ShopifyScraperService {
    private $db;
    private $domain = 'https://greenice.com';
    private $throttler;
    private $userAgentRotator;
    private $emailService; // ✅ Añadir EmailService
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->throttler = new Throttler(2, 5);
        $this->userAgentRotator = new UserAgentRotator();
        $this->emailService = new EmailService(); // ✅ Inicializar EmailService
    }
    
    public function scrapeAllProducts($maxProducts = 10000) {
        Logger::info("Starting Shopify JSON scrape - max products: {$maxProducts}");
        
        $allProducts = [];
        $page = 1;
        $limit = 250;
        $priceChanges = []; // ✅ Acumular cambios aquí
        $productsCreated = 0;
        $productsUpdated = 0;
        $errors = 0;
        
        while (count($allProducts) < $maxProducts) {
            $this->throttler->throttle();
            
            $url = "{$this->domain}/products.json?limit={$limit}&page={$page}";
            Logger::info("Fetching page {$page}:  {$url}");
            
            $json = $this->fetchJson($url);
            
            if (! $json || !isset($json['products']) || empty($json['products'])) {
                Logger::info("No more products found on page {$page}");
                break;
            }
            
            $products = $json['products'];
            Logger::info("Found " .  count($products) . " products on page {$page}");
            
            foreach ($products as $shopifyProduct) {
                if (count($allProducts) >= $maxProducts) {
                    break;
                }
                
                $product = $this->parseShopifyProduct($shopifyProduct);
                if (! $product) {
                    $errors++;
                    continue;
                }
                
                $allProducts[] = $product;
                
                // ✅ Procesar producto y detectar cambios
                try {
                    $change = $this->saveOrUpdateProduct($product);
                    
                    if ($change['action'] === 'created') {
                        $productsCreated++;
                    } elseif ($change['action'] === 'updated') {
                        $productsUpdated++;
                        
                        // ✅ Si hubo cambio de precio, acumular
                        if ($change['price_changed']) {
                            $priceChanges[] = $change;
                        }
                    }
                } catch (Exception $e) {
                    Logger::error("Error saving product: " . $e->getMessage());
                    $errors++;
                }
            }
            
            if (count($products) < $limit) {
                break;
            }
            
            $page++;
        }
        
        Logger::info("Scrape completed. Total products: " . count($allProducts));
        
        // ✅ Enviar UN SOLO email con todos los cambios
        if (! empty($priceChanges)) {
            Logger::info("Sending price changes summary with " . count($priceChanges) . " changes");
            $this->emailService->sendPriceChangesSummary($priceChanges);
        } else {
            Logger::info("No price changes detected");
        }
        
        return [
            'products_found' => count($allProducts),
            'products_created' => $productsCreated,
            'products_updated' => $productsUpdated,
            'price_changes' => count($priceChanges),
            'errors' => $errors
        ];
    }
    
    // ✅ Nueva función para guardar/actualizar y detectar cambios
    private function saveOrUpdateProduct($product) {
        $stmt = $this->db->prepare("SELECT id, current_price, name, reference FROM products WHERE url = ?");
        $stmt->execute([$product['url']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $oldPrice = floatval($existing['current_price']);
            $newPrice = floatval($product['price']);
            
            // Actualizar producto
            $stmt = $this->db->prepare("
                UPDATE products 
                SET name = ?, reference = ?, current_price = ?, 
                    updated_at = NOW(), last_scraped_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $product['name'],
                $product['reference'],
                $product['price'],
                $existing['id']
            ]);
            
            // Detectar cambio de precio
            if (abs($newPrice - $oldPrice) > 0.50) {
                $priceChange = $newPrice - $oldPrice;
                $percentageChange = (($priceChange / $oldPrice) * 100);
                
                // Guardar en historial
                $stmt = $this->db->prepare("
                    INSERT INTO price_history 
                    (product_id, old_price, new_price, price_change, percentage_change, recorded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $existing['id'],
                    $oldPrice,
                    $newPrice,
                    $priceChange,
                    $percentageChange
                ]);
                
                Logger::info("Updated product price:  {$product['name']} - {$oldPrice}€ → {$newPrice}€");
                
                return [
                    'action' => 'updated',
                    'price_changed' => true,
                    'id' => $existing['id'],
                    'name' => $product['name'],
                    'reference' => $product['reference'],
                    'url' => $product['url'],
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'price_change' => $priceChange,
                    'percentage_change' => $percentageChange
                ];
            }
            
            return [
                'action' => 'updated',
                'price_changed' => false
            ];
            
        } else {
            // Crear nuevo producto
            $stmt = $this->db->prepare("
                INSERT INTO products (url, name, reference, current_price, created_at, updated_at, last_scraped_at, status)
                VALUES (?, ?, ?, ?, NOW(), NOW(), NOW(), 'active')
            ");
            $stmt->execute([
                $product['url'],
                $product['name'],
                $product['reference'],
                $product['price']
            ]);
            
            Logger::info("Created new product: {$product['name']}");
            
            return [
                'action' => 'created',
                'price_changed' => false
            ];
        }
    }
    
    private function fetchJson($url) {
        Logger::info("Attempting to fetch JSON from: {$url}");
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $this->userAgentRotator->getRandom(),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: es-ES,es;q=0.9',
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        Logger::info("cURL response - HTTP {$httpCode}, Response length: " . strlen($response) . " bytes, Error: {$error}");
        
        if ($response === false || $httpCode !== 200) {
            Logger::error("Failed to fetch JSON from {$url}:  HTTP {$httpCode}, Error: {$error}");
            return null;
        }
        
        $json = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("JSON decode error: " . json_last_error_msg());
            Logger::error("Raw response preview: " . substr($response, 0, 500));
            return null;
        }
        
        Logger::info("Successfully decoded JSON with " . (isset($json['products']) ? count($json['products']) : 0) . " products");
        
        return $json;
    }
    
    private function parseShopifyProduct($shopifyProduct) {
        try {
            $variant = $shopifyProduct['variants'][0] ?? null;
            $price = null;
            
            if ($variant && isset($variant['price'])) {
                $price = (float) $variant['price'];
            }
            
            $handle = $shopifyProduct['handle'];
            $url = "{$this->domain}/products/{$handle}";
            
            $reference = null;
            if ($variant && isset($variant['sku']) && !empty($variant['sku'])) {
                $reference = $variant['sku'];
            } elseif (isset($shopifyProduct['id'])) {
                $reference = 'SHOP-' . $shopifyProduct['id'];
            }
            
            $product = [
                'url' => $url,
                'name' => $shopifyProduct['title'] ??  'Unknown',
                'reference' => $reference,
                'price' => $price,
            ];
            
            if (empty($product['name']) || $product['price'] === null) {
                Logger::warning("Incomplete product data for:  {$url}");
                return null;
            }
            
            return $product;
            
        } catch (Exception $e) {
            Logger::error("Error parsing Shopify product: " .  $e->getMessage());
            return null;
        }
    }
}
