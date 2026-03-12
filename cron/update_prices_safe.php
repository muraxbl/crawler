<?php

require_once dirname(__DIR__) . '/config/config.php';

Logger::init('cron_update_prices. log');
Logger::info("========== Starting daily price update ==========");

$productModel = new Product();
$scraperService = new ScraperService();
$notificationService = new NotificationService();
$healthMonitor = new ScraperHealthMonitor();

// Verificar límite diario de peticiones
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT config_value FROM config WHERE config_key = 'max_daily_requests'");
$maxDailyRequests = (int)($stmt->fetch()['config_value'] ??  500);

// Contar peticiones de hoy
$stmt = $db->query("
    SELECT COUNT(*) as count FROM products 
    WHERE DATE(last_scraped_at) = CURDATE()
");
$todayRequests = (int)$stmt->fetch()['count'];

if ($todayRequests >= $maxDailyRequests) {
    Logger::warning("Daily request limit reached ({$todayRequests}/{$maxDailyRequests}). Skipping update.");
    exit(0);
}

// Verificar salud del scraper
$recentHealth = $healthMonitor->getRecentHealth(1);
if (!empty($recentHealth) && $recentHealth[0]['status'] === 'critical') {
    Logger::warning("Scraper health is critical.  Waiting before continuing.");
    sleep(3600); // Esperar 1 hora
}

// Obtener productos a actualizar (priorizar los más antiguos)
$limit = min(100, $maxDailyRequests - $todayRequests);
$products = $productModel->getAll('active', $limit);
Logger::info("Found " . count($products) . " products to update (limit: {$limit})");

$updated = 0;
$changed = 0;
$errors = 0;
$blocked = 0;

// Distribuir peticiones a lo largo de varias horas
$totalProducts = count($products);
$hoursToSpread = 4; // Distribuir en 4 horas
$delayBetweenProducts = $totalProducts > 0 ? ($hoursToSpread * 3600) / $totalProducts : 0;

foreach ($products as $index => $product) {
    try {
        Logger::info("Updating product ID {$product['id']}: {$product['name']} [{$index}/{$totalProducts}]");
        
        $html = $scraperService->fetchPage($product['url']);
        
        if (!$html) {
            Logger::warning("Could not scrape product ID {$product['id']}");
            $errors++;
            continue;
        }
        
        $scrapedProduct = $scraperService->parseProduct($html, $product['url']);
        
        if (! $scrapedProduct || $scrapedProduct['price'] === null) {
            Logger:: warning("Could not parse product ID {$product['id']}");
            $errors++;
            continue;
        }
        
        $result = $productModel->updatePrice($product['id'], $scrapedProduct['price']);
        $updated++;
        
        if ($result['changed']) {
            $changed++;
            Logger::info("Price changed for product ID {$product['id']}: {$result['old_price']}€ -> {$result['new_price']}€");
            
            // Enviar notificación
            $notificationService->sendPriceChangeNotification($product['id'], $result['history_id']);
        }
        
        // Delay adaptativo entre productos
        if ($index < $totalProducts - 1) {
            $sleep = max(10, (int)$delayBetweenProducts);
            Logger::info("Sleeping {$sleep}s before next product");
            sleep($sleep);
        }
        
    } catch (Exception $e) {
        Logger::error("Error updating product ID {$product['id']}: " . $e->getMessage());
        $errors++;
    }
    
    // Cada 20 productos, pausa más larga
    if (($index + 1) % 20 === 0) {
        $longPause = rand(120, 300); // 2-5 minutos
        Logger::info("Taking long break:  {$longPause}s after " . ($index + 1) . " products");
        sleep($longPause);
    }
}

// Registrar métricas de salud
$healthMonitor->recordMetrics($updated, $errors, $blocked, 0);

Logger::info("========== Daily price update completed ==========");
Logger::info("Total updated:  {$updated}, Changed: {$changed}, Errors:  {$errors}, Blocked: {$blocked}");

// Limpiar logs antiguos (más de 30 días)
$logFiles = glob(LOG_PATH . '/*.log');
foreach ($logFiles as $logFile) {
    if (filemtime($logFile) < strtotime('-30 days')) {
        unlink($logFile);
    }
}