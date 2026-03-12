<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS headers - IMPORTANTE para que el frontend pueda hacer peticiones
header('Access-Control-Allow-Origin:  *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';

// Incluir clases necesarias
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH .  '/controllers/ProductController.php';

Logger::init('api.log');

// Obtener endpoint
$endpoint = '';

if (isset($_GET['endpoint'])) {
    $endpoint = $_GET['endpoint'];
} elseif (isset($_SERVER['PATH_INFO'])) {
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
} else {
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = str_replace('/api/index.php', '', $path);
    $path = str_replace('/api', '', $path);
    $endpoint = trim($path, '/');
}

if (empty($endpoint)) {
    $endpoint = 'products';
}

Logger:: info("API Request: {$_SERVER['REQUEST_METHOD']} - Endpoint: {$endpoint}");

// Enrutamiento
if (strpos($endpoint, 'products') === 0 || $endpoint === 'products') {
    try {
        $_SERVER['REQUEST_URI'] = '/api/' . $endpoint;
        
        $controller = new ProductController();
        $controller->handleRequest();
    } catch (Exception $e) {
        Logger::error("Controller error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Internal server error',
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(404);
    echo json_encode([
        'error' => 'Endpoint not found',
        'endpoint' => $endpoint,
        'usage' => 'Use ?endpoint=products/stats',
        'examples' => [
            '/api/index.php?endpoint=products/stats',
            '/api/index.php?endpoint=products',
            '/api/index.php?endpoint=products/scrape-shopify',
        ]
    ]);
}
