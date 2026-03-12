<?php
require_once __DIR__ . '/config/config.php';

// Simular la request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/products/scrape-shopify';

Logger::init('test_routing. log');

echo "<h1>Test Routing</h1><pre>";

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "Original path: {$path}\n";

$path = str_replace('/api/index.php', '', $path);
echo "After removing /api/index.php: {$path}\n";

$path = str_replace('/api', '', $path);
echo "After removing /api: {$path}\n";

$path = trim($path, '/');
echo "After trim: {$path}\n\n";

$pathParts = array_filter(explode('/', $path));
$pathParts = array_values($pathParts);

echo "Path parts:\n";
print_r($pathParts);

echo "\nChecking conditions:\n";
echo "isset(\$pathParts[0]): " . (isset($pathParts[0]) ? 'true' : 'false') . "\n";
echo "\$pathParts[0] value: " . ($pathParts[0] ?? 'NOT SET') . "\n";
echo "\$pathParts[0] === 'products': " . (isset($pathParts[0]) && $pathParts[0] === 'products' ? 'true' : 'false') . "\n";

echo "\nisset(\$pathParts[1]): " . (isset($pathParts[1]) ? 'true' : 'false') . "\n";
echo "\$pathParts[1] value: " . ($pathParts[1] ?? 'NOT SET') . "\n";
echo "\$pathParts[1] === 'scrape-shopify': " . (isset($pathParts[1]) && $pathParts[1] === 'scrape-shopify' ?  'true' : 'false') . "\n";

echo "\nFinal condition result: ";
if (isset($pathParts[0]) && $pathParts[0] === 'products' && 
    isset($pathParts[1]) && $pathParts[1] === 'scrape-shopify') {
    echo "✓ MATCH - would call startShopifyScrape()\n";
} else {
    echo "✗ NO MATCH - would NOT call startShopifyScrape()\n";
}

echo "</pre>";
