<?php
class ProductController {
    private $productModel;
    private $scraperService;
    
    public function __construct() {
        $this->productModel = new Product();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $requestUri = $_SERVER['REQUEST_URI'];
        
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = str_replace('/api/index.php', '', $path);
        $path = str_replace('/api', '', $path);
        $path = trim($path, '/');
        
        $pathParts = array_filter(explode('/', $path));
        $pathParts = array_values($pathParts);
        
        Logger::info("ProductController - Method: {$method}, Path: {$path}");
        
        // Rate limiting
        $rateLimiter = new RateLimiter();
        $check = $rateLimiter->checkLimit(RateLimiter::getClientIdentifier(), $path);
        
        if (! $check['allowed']) {
            http_response_code(429);
            echo json_encode([
                'error' => $check['message'],
                'retry_after' => $check['retry_after']
            ]);
            return;
        }
        
        try {
            switch ($method) {
                case 'GET':   
                    if (isset($pathParts[0]) && $pathParts[0] === 'products' && 
                        isset($pathParts[1]) && $pathParts[1] === 'stats') {
                        $this->getStats();
                    }
                    elseif (isset($pathParts[0]) && $pathParts[0] === 'products' && 
                            isset($pathParts[1]) && is_numeric($pathParts[1])) {
                        $this->getProduct($pathParts[1]);
                    }
                    else {
                        $this->listProducts();
                    }
                    break;
                    
                case 'POST':  
                    // POST /api/products/scrape-shopify
                    if (isset($pathParts[0]) && $pathParts[0] === 'products' && 
                        isset($pathParts[1]) && $pathParts[1] === 'scrape-shopify') {
                        $this->startShopifyScrape();
                    }
                    // POST /api/products/scrape
                    elseif (isset($pathParts[0]) && $pathParts[0] === 'products' && 
                            isset($pathParts[1]) && $pathParts[1] === 'scrape') {
                        $this->startScraping();
                    }
                    // POST /api/products
                    else {
                        $this->createProduct();
                    }
                    break;
                    
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
            }
        } catch (Exception $e) {
            Logger::error("Controller error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    private function listProducts() {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = ($page - 1) * $limit;
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Consulta mejorada con precio anterior y cambio
            $sql = "
                SELECT 
                    p.*,
                    ph.old_price as previous_price,
                    ph.price_change,
                    ph.percentage_change,
                    ph.recorded_at as last_price_change_date
                FROM products p
                LEFT JOIN (
                    SELECT 
                        ph1.product_id,
                        ph1.old_price,
                        ph1.new_price - ph1.old_price as price_change,
                        ((ph1.new_price - ph1.old_price) / ph1.old_price * 100) as percentage_change,
                        ph1.recorded_at
                    FROM price_history ph1
                    INNER JOIN (
                        SELECT product_id, MAX(recorded_at) as max_date
                        FROM price_history
                        GROUP BY product_id
                    ) ph2 ON ph1.product_id = ph2.product_id AND ph1.recorded_at = ph2.max_date
                ) ph ON p.id = ph.product_id
                WHERE p.status = 'active'
                ORDER BY p.id DESC
                LIMIT ?  OFFSET ?
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$limit, $offset]);
            $products = $stmt->fetchAll();
            
            Logger::info("Listed " . count($products) . " products with price history");
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $products,
                'page' => $page,
                'limit' => $limit,
                'count' => count($products)
            ]);
            
        } catch (Exception $e) {
            Logger::error("Error listing products:  " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener productos'
            ]);
        }
    }
    
    private function getProduct($id) {
        $stmt = Database::getInstance()->getConnection()->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            return;
        }
        
        $history = $this->productModel->getPriceHistory($id, 20);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $product,
            'price_history' => $history
        ]);
    }
    
    private function createProduct() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['url'])) {
            http_response_code(400);
            echo json_encode(['error' => 'URL is required']);
            return;
        }
        
        if (!$this->scraperService) {
            require_once BASE_PATH . '/services/ScraperService.php';
            $this->scraperService = new ScraperService();
        }
        
        $html = $this->scraperService->fetchPage($input['url']);
        $product = $this->scraperService->parseProduct($html, $input['url']);
        
        if (!$product) {
            http_response_code(400);
            echo json_encode(['error' => 'Could not parse product']);
            return;
        }
        
        $productId = $this->productModel->create($product);
        
        if ($productId) {
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'product_id' => $productId,
                'data' => $product
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create product']);
        }
    }
    
    private function startScraping() {
        $input = json_decode(file_get_contents('php://input'), true);
        $baseUrl = $input['base_url'] ?? 'https://greenice.com/';
        $maxPages = $input['max_pages'] ?? 10;
        
        if (!$this->scraperService) {
            require_once BASE_PATH . '/services/ScraperService.php';
            $this->scraperService = new ScraperService();
        }
        
        $products = $this->scraperService->scrapeProductCatalog($baseUrl, $maxPages);
        
        $created = 0;
        foreach ($products as $product) {
            if ($this->productModel->create($product)) {
                $created++;
            }
        }
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Scraping completed",
            'products_found' => count($products),
            'products_created' => $created
        ]);
    }
    
    private function getStats() {
        $stats = $this->productModel->getStats();
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    private function startShopifyScrape() {
        $input = json_decode(file_get_contents('php://input'), true);
        $maxProducts = $input['max_products'] ??  500;
        
        Logger::info("Starting Shopify scrape - max products: {$maxProducts}");
        
        try {
            require_once BASE_PATH . '/services/ShopifyScraperService.php';
            
            $shopifyScraper = new ShopifyScraperService();
            $products = $shopifyScraper->scrapeAllProducts($maxProducts);
            
            $created = 0;
            $updated = 0;
            $errors = 0;
            $batchSize = 50;
            $batch = [];
            
            foreach ($products as $product) {
                $batch[] = $product;
                
                // Procesar en lotes de 50
                if (count($batch) >= $batchSize) {
                    $result = $this->processBatch($batch);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $errors += $result['errors'];
                    $batch = [];
                    
                    // Pequeña pausa cada lote
                    usleep(100000); // 0.1 segundos
                }
            }
            
            // Procesar productos restantes
            if (!empty($batch)) {
                $result = $this->processBatch($batch);
                $created += $result['created'];
                $updated += $result['updated'];
                $errors += $result['errors'];
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Shopify scrape completed',
                'products_found' => count($products),
                'products_created' => $created,
                'products_updated' => $updated,
                'errors' => $errors
            ]);
            
            Logger::info("Shopify scrape completed - Found:  " . count($products) . ", Created: {$created}, Updated:  {$updated}");
            
        } catch (Exception $e) {
            Logger::error("Shopify scrape error: " .  $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => 'Scrape failed',
                'message' => $e->getMessage()
            ]);
        }
    }

    private function processBatch($batch) {
        $created = 0;
        $updated = 0;
        $errors = 0;
        
        foreach ($batch as $product) {
            try {
                // Reconectar antes de cada operación
                Database::getInstance()->getConnection();
                
                $result = $this->productModel->create($product);
                if ($result) {
                    if ($result['action'] === 'created') {
                        $created++;
                    } else {
                        $updated++;
                    }
                }
            } catch (Exception $e) {
                $errors++;
                Logger::error("Error saving product:  " . $e->getMessage());
            }
        }
        
        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors
        ];
    }
}
?>
