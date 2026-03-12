<?php

class ScraperService {
    private $db;
    private $throttler;
    private $userAgentRotator;
    private $proxyManager;
    private $sessionManager;
    private $maxRetries;
    private $requestCount = 0;
    private $sessionRotationThreshold = 50;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfig();
        
        $this->throttler = new Throttler(
            $this->getConfigValue('scraper_delay_min_seconds', 5),  // Aumentado a 5 segundos
            $this->getConfigValue('scraper_delay_max_seconds', 12)  // Aumentado a 12 segundos
        );
        
        $this->userAgentRotator = new UserAgentRotator();
        $this->proxyManager = new ProxyManager();
        $this->sessionManager = new SessionManager();
        $this->maxRetries = (int)($this->config['max_retries'] ?? 3);
    }
    
    private function loadConfig() {
        $stmt = $this->db->query("SELECT config_key, config_value FROM config");
        $this->config = [];
        while ($row = $stmt->fetch()) {
            $this->config[$row['config_key']] = $row['config_value'];
        }
    }
    
    private function getConfigValue($key, $default = null) {
        return $this->config[$key] ??  $default;
    }
    
    public function fetchPage($url, $retryCount = 0) {
        // Throttling adaptativo
        $this->throttler->throttle();
        
        // Rotar sesión si es necesario
        $this->sessionManager->rotateIfNeeded();
        
        // Cada 50 peticiones, pausa más larga (comportamiento humano)
        $this->requestCount++;
        if ($this->requestCount % $this->sessionRotationThreshold === 0) {
            $pauseTime = rand(30, 60);
            Logger::info("Taking extended break for {$pauseTime} seconds after {$this->requestCount} requests");
            sleep($pauseTime);
        }
        
        $ch = curl_init();
        
        // User agent rotatorio
        $userAgent = $this->userAgentRotator->getRandom();
        
        // Proxy rotatorio (si está habilitado)
        $proxy = $this->proxyManager->getNext();
        
        // Headers realistas que simulan un navegador
        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding:  gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
            'DNT: 1', // Do Not Track
        ];
        
        // Agregar Referer aleatorio (simula navegación interna)
        if (rand(0, 100) > 30) { // 70% del tiempo incluir referer
            $referers = [
                'https://www.google.com/',
                'https://www.google.es/search?q=greenice',
                'https://greenice.com/',
                'https://greenice.com/categorias',
            ];
            $headers[] = 'Referer:  ' . $referers[array_rand($referers)];
        }
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '', // Habilita gzip/deflate
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_COOKIEFILE => $this->sessionManager->getCookieFile(),
            CURLOPT_COOKIEJAR => $this->sessionManager->getCookieFile(),
            // Simular comportamiento de navegador real
            CURLOPT_AUTOREFERER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        ];
        
        // Configurar proxy si está disponible
        if ($proxy) {
            $curlOptions[CURLOPT_PROXY] = $proxy['proxy_url'];
            $curlOptions[CURLOPT_PROXYTYPE] = $this->getProxyType($proxy['proxy_type']);
            Logger::info("Using proxy: {$proxy['proxy_url']} for {$url}");
        }
        
        curl_setopt_array($ch, $curlOptions);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        // Manejo de errores y reintentos
        if ($response === false || $httpCode >= 400) {
            // Si falla con proxy, marcarlo y reintentar sin proxy
            if ($proxy && $retryCount < $this->maxRetries) {
                $this->proxyManager->markAsFailed($proxy['proxy_url']);
                Logger::warning("Proxy failed, retrying without proxy");
                sleep(2);
                return $this->fetchPage($url, $retryCount + 1);
            }
            
            // Si es 429 (Too Many Requests) o 403 (Forbidden), espera más tiempo
            if (in_array($httpCode, [429, 403]) && $retryCount < $this->maxRetries) {
                $backoffTime = pow(2, $retryCount) * 10; // Backoff exponencial más agresivo
                Logger::warning("Rate limited (HTTP {$httpCode}), waiting {$backoffTime}s before retry {$retryCount}/{$this->maxRetries}");
                sleep($backoffTime);
                return $this->fetchPage($url, $retryCount + 1);
            }
            
            // Reintentos normales
            if ($retryCount < $this->maxRetries) {
                $waitTime = pow(2, $retryCount) * 3;
                Logger::warning("Retry {$retryCount}/{$this->maxRetries} for URL:  {$url} (HTTP {$httpCode})");
                sleep($waitTime);
                return $this->fetchPage($url, $retryCount + 1);
            }
            
            Logger::error("Failed to fetch {$url}:  HTTP {$httpCode}, Error: {$error}");
            return false;
        }
        
        // Detectar bloqueos por contenido (página de CAPTCHA, etc.)
        if ($this->detectBlock($response)) {
            Logger:: warning("Block detected in response from {$url}");
            
            if ($retryCount < $this->maxRetries) {
                // Pausa larga si detectamos bloqueo
                $pauseTime = rand(60, 120);
                Logger::info("Waiting {$pauseTime}s after block detection");
                sleep($pauseTime);
                
                // Limpiar cookies y cambiar de sesión
                $this->sessionManager->clearCookies();
                
                return $this->fetchPage($url, $retryCount + 1);
            }
            
            return false;
        }
        
        Logger::info("Successfully fetched:  {$url} (HTTP {$httpCode}, " . strlen($response) . " bytes)");
        return $response;
    }
    
    private function getProxyType($type) {
        $types = [
            'http' => CURLPROXY_HTTP,
            'https' => CURLPROXY_HTTPS,
            'socks4' => CURLPROXY_SOCKS4,
            'socks5' => CURLPROXY_SOCKS5,
        ];
        return $types[$type] ?? CURLPROXY_HTTP;
    }
    
    private function detectBlock($html) {
        if (empty($html)) {
            return false;
        }
        
        // Patrones comunes de bloqueo
        $blockPatterns = [
            '/captcha/i',
            '/cloudflare/i',
            '/access denied/i',
            '/blocked/i',
            '/firewall/i',
            '/bot.*detected/i',
            '/security check/i',
            '/unusual.*traffic/i',
            '/verificaci[oó]n/i',
        ];
        
        foreach ($blockPatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }
        
        // Si la respuesta es demasiado pequeña (probablemente no es la página real)
        if (strlen($html) < 1000) {
            return true;
        }
        
        return false;
    }
    
    public function parseProduct($html, $url) {
        if (! $html) {
            return null;
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        // Adaptar estos selectores a la estructura real de greenice. com
        $product = [
            'url' => $url,
            'name' => $this->extractText($xpath, "//h1[@class='product-title'] | //h1[contains(@class, 'product-name')] | //h1"),
            'reference' => $this->extractText($xpath, "//*[contains(@class, 'reference')] | //*[contains(@class, 'sku')] | //*[contains(text(), 'Ref')]"),
            'price' => $this->extractPrice($xpath, "//*[contains(@class, 'price')] | //*[@itemprop='price'] | //*[contains(@class, 'product-price')]")
        ];
        
        // Limpiar referencia
        if ($product['reference']) {
            $product['reference'] = preg_replace('/^(Ref\. ? :|SKU:|Referencia:)\s*/i', '', $product['reference']);
            $product['reference'] = trim($product['reference']);
        }
        
        // Validar que tengamos datos mínimos
        if (empty($product['name']) || $product['price'] === null) {
            Logger::warning("Incomplete product data for URL: {$url}");
            return null;
        }
        
        return $product;
    }
    
    private function extractText($xpath, $query) {
        $nodes = $xpath->query($query);
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }
    
    private function extractPrice($xpath, $query) {
        $nodes = $xpath->query($query);
        if ($nodes->length > 0) {
            $priceText = $nodes->item(0)->textContent;
            preg_match('/(\d+[.,]\d+|\d+)/', str_replace([' ', '€', '$'], '', $priceText), $matches);
            if (isset($matches[0])) {
                return (float)str_replace(',', '.', $matches[0]);
            }
        }
        return null;
    }
    
    public function scrapeProductCatalog($baseUrl = 'https://greenice.com/', $maxPages = 50) {
        Logger::info("Starting catalog scrape from:  {$baseUrl}");
        
        $products = [];
        $page = 1;
        $consecutiveFailures = 0;
        $maxConsecutiveFailures = 3;
        
        while ($page <= $maxPages) {
            $catalogUrl = $baseUrl . "?page={$page}";
            Logger::info("Scraping catalog page {$page}:  {$catalogUrl}");
            
            $html = $this->fetchPage($catalogUrl);
            if (!$html) {
                $consecutiveFailures++;
                if ($consecutiveFailures >= $maxConsecutiveFailures) {
                    Logger::error("Too many consecutive failures, stopping scrape");
                    break;
                }
                $page++;
                continue;
            }
            
            $consecutiveFailures = 0; // Reset counter on success
            
            $productUrls = $this->extractProductUrls($html, $baseUrl);
            
            if (empty($productUrls)) {
                Logger::info("No more products found on page {$page}");
                break;
            }
            
            foreach ($productUrls as $productUrl) {
                $productHtml = $this->fetchPage($productUrl);
                $product = $this->parseProduct($productHtml, $productUrl);
                
                if ($product) {
                    $products[] = $product;
                    Logger::info("Scraped product: {$product['name']} - {$product['price']}€");
                }
            }
            
            $page++;
            
            // Pausa más larga entre páginas del catálogo
            $pauseBetweenPages = rand(10, 20);
            Logger::info("Waiting {$pauseBetweenPages}s before next catalog page");
            sleep($pauseBetweenPages);
        }
        
        Logger::info("Catalog scrape completed. Total products: " . count($products));
        return $products;
    }
    
    private function extractProductUrls($html, $baseUrl) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $nodes = $xpath->query("//a[contains(@class, 'product-link')] | //a[contains(@href, '/product/')] | //article//a");
        
        $urls = [];
        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            if ($href && ! in_array($href, $urls)) {
                if (strpos($href, 'http') !== 0) {
                    $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                }
                
                if (strpos($href, 'greenice.com') !== false && ! preg_match('/\.(jpg|png|css|js)$/i', $href)) {
                    $urls[] = $href;
                }
            }
        }
        
        return array_unique($urls);
    }
    
    public function getRequestCount() {
        return $this->requestCount;
    }
}