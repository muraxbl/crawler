<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/services/ShopifyScraperService.php';

Logger::init('test_shopify. log');

echo "<h1>Test Shopify Scraper</h1><pre>";

try {
    $scraper = new ShopifyScraperService();
    
    echo "Fetching first 10 products...\n\n";
    $products = $scraper->scrapeAllProducts(10);
    
    echo "✓ Found " . count($products) . " products\n\n";
    
    foreach ($products as $i => $product) {
        echo ($i + 1) . ". {$product['name']}\n";
        echo "   URL: {$product['url']}\n";
        echo "   Price: {$product['price']}€\n";
        echo "   Reference: " . ($product['reference'] ?: 'N/A') . "\n\n";
    }
    
    echo "✓ Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>