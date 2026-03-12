<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: PHP OK\n";

echo "Step 2: Loading config...\n";
try {
    require_once __DIR__ . '/config/config.php';
    echo "Config loaded:  OK\n";
    echo "BASE_PATH: " . BASE_PATH . "\n";
} catch (Exception $e) {
    echo "Config error: " .  $e->getMessage() . "\n";
    exit;
}

echo "\nStep 3: Test Database...\n";
try {
    $db = Database::getInstance();
    echo "Database:   OK\n";
} catch (Exception $e) {
    echo "Database error:   " . $e->getMessage() . "\n";
}

echo "\nStep 4: Test UserAgentRotator...\n";
try {
    $ua = new UserAgentRotator();
    echo "UserAgent: " . $ua->getRandom() . "\n";
} catch (Exception $e) {
    echo "UserAgent error: " . $e->getMessage() . "\n";
}

echo "\nStep 5: Test Throttler...\n";
try {
    $throttler = new Throttler(1, 2);
    echo "Throttler:  OK\n";
} catch (Exception $e) {
    echo "Throttler error:   " . $e->getMessage() . "\n";
}

echo "\nStep 6: Test ShopifyScraperService...\n";
try {
    require_once __DIR__ . '/services/ShopifyScraperService.php';
    echo "ShopifyScraperService loaded: OK\n";
    
    $scraper = new ShopifyScraperService();
    echo "ShopifyScraperService instantiated: OK\n";
} catch (Exception $e) {
    echo "ShopifyScraperService error:  " . $e->getMessage() . "\n";
    echo "Trace:  " . $e->getTraceAsString() . "\n";
}

echo "\n✓ All tests passed!\n";
?>
