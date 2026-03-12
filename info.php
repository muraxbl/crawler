<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Autoload Test</h1><pre>";

// Test 1: Load config
echo "1. Loading config.. .\n";
require_once __DIR__ . '/config/config.php';
echo "   ✓ Config loaded\n";
echo "   BASE_PATH:  " . BASE_PATH . "\n\n";

// Test 2: Database class
echo "2. Testing Database class...\n";
try {
    $db = Database::getInstance();
    echo "   ✓ Database class loaded\n";
    $conn = $db->getConnection();
    echo "   ✓ Database connected\n\n";
} catch (Exception $e) {
    echo "   ✗ Database error: " . $e->getMessage() . "\n\n";
}

// Test 3: Product model
echo "3. Testing Product model...\n";
try {
    $product = new Product();
    echo "   ✓ Product class loaded\n";
    $stats = $product->getStats();
    echo "   ✓ Stats retrieved:  " . json_encode($stats) . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Product error:   " . $e->getMessage() . "\n\n";
}

// Test 4: ProductController
echo "4. Testing ProductController...\n";
try {
    $controller = new ProductController();
    echo "   ✓ ProductController class loaded\n\n";
} catch (Exception $e) {
    echo "   ✗ Controller error: " . $e->getMessage() . "\n\n";
}

// Test 5: Check file existence
echo "5. Checking files...\n";
$files = [
    'controllers/ProductController.php',
    'models/Product.php',
    'utils/Logger.php',
    'utils/RateLimiter.php',
    'services/ScraperService.php'
];

foreach ($files as $file) {
    $fullPath = BASE_PATH . '/' . $file;
    $exists = file_exists($fullPath);
    echo "   " . ($exists ? '✓' : '✗') . " {$file} " . ($exists ? '' : '(NOT FOUND)') . "\n";
}

echo "\n</pre>";