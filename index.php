<?php
// index.php en la raíz del proyecto

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remover query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Eliminar barras al inicio/final
$path = trim($path, '/');

// Si es una petición a la API
if (strpos($path, 'api/') === 0) {
    require_once __DIR__ . '/api/index. php';
    exit;
}

// Si es la raíz o vacío, mostrar interfaz web
if ($path === '' || $path === 'index.php') {
    readfile(__DIR__ . '/public/index.html');
    exit;
}

// Si es un archivo estático en public/
if (strpos($path, 'public/') === 0) {
    $file = __DIR__ . '/' .  $path;
    if (file_exists($file) && is_file($file)) {
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
        ];
        
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' .  $mimeType);
        readfile($file);
        exit;
    }
}

// 404
http_response_code(404);
echo json_encode(['error' => 'Not found', 'path' => $path]);