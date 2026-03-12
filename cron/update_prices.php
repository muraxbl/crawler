<?php

require_once dirname(__DIR__) . '/config/config.php';

Logger::init('cron_update_prices.log');
Logger::info("========== Starting daily price update ==========");

$productModel = new Product();
$scraperService = new ScraperService();
$notificationService = new NotificationService();

// Obtener todos los productos activos
$products = $productModel->getAll('active');
Logger::info("Found " . count($products) . " products to update");

$updated = 0;
$changed = 0;
$errors = 0;

foreach ($products as $product) {
    try {
        Logger::info("Updating product ID {$product['id']}:  {$product['name']}");
        
        $html = $scraperService->fetchPage($product['url']);
        $scrapedProduct = $scraperService->parseProduct($html, $product['url']);
        
        if (!$scrapedProduct || $scrapedProduct['price'] === null) {
            Logger::warning("Could not scrape product ID {$product['id']}");
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
        
    } catch (Exception $e) {
        Logger::error("Error updating product ID {$product['id']}: " . $e->getMessage());
        $errors++;
    }
}

Logger::info("========== Daily price update completed ==========");
Logger::info("Total updated: {$updated}, Changed:  {$changed}, Errors: {$errors}");

// Limpiar logs antiguos (más de 30 días)
$logFiles = glob(LOG_PATH . '/*.log');
foreach ($logFiles as $logFile) {
    if (filemtime($logFile) < strtotime('-30 days')) {
        unlink($logFile);
    }
}