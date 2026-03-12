<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Manual Shopify API Test</h1><pre>";

$url = 'https://greenice.com/products.json?limit=10';
echo "Testing URL: {$url}\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Error: {$error}\n";
echo "Response length: " . strlen($response) . " bytes\n\n";

if ($response && $httpCode == 200) {
    echo "✓ Connection successful!\n\n";
    
    $json = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✓ JSON decoded successfully\n";
        
        if (isset($json['products'])) {
            echo "✓ Found " . count($json['products']) . " products\n\n";
            
            foreach ($json['products'] as $i => $product) {
                if ($i >= 5) break;
                
                echo ($i + 1) . ". {$product['title']}\n";
                echo "   Handle: {$product['handle']}\n";
                echo "   ID: {$product['id']}\n";
                
                if (isset($product['variants'][0]['price'])) {
                    echo "   Price: {$product['variants'][0]['price']}€\n";
                }
                
                echo "\n";
            }
        } else {
            echo "✗ No 'products' key in JSON\n";
            echo "Keys found: " . implode(', ', array_keys($json)) . "\n";
        }
    } else {
        echo "✗ JSON decode error: " . json_last_error_msg() . "\n";
        echo "First 500 chars:\n" . substr($response, 0, 500) . "\n";
    }
} else {
    echo "✗ Request failed\n";
}

echo "</pre>";
?>
