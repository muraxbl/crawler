<?php

class ScraperService {
    private $db;
    private $throttler;
    private $userAgent;
    private $maxRetries;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfig();
        $this->throttler = new Throttler(
            $this->getConfigValue('scraper_delay_min_seconds', 3),
            $this->getConfigValue('scraper_delay_max_seconds', 8)
        );
    }
    
    private function loadConfig() {
        $stmt = $this->db->query("SELECT config_key, config_value FROM config");
        $this->config = [];
        while ($row = $stmt->fetch()) {
            $this->config[$row['config_key']] = $row['config_value'];
        }
        
        $this->userAgent = $this->config['user_agent'] ?? 'Mozilla/5.0';
        $this->maxRetries = (int)($this->config['max_retries'] ?? 3);
    }
    
    private function getConfigValue($key, $default = null) {
        return $this->config[$key] ??  $default;
    }
    
    public function fetchPage($url, $retryCount = 0) {
        $this->throttler->throttle();
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept:  text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Connection:  keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Cache-Control: max-age=0'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || $httpCode >= 400) {
            if ($retryCount < $this->maxRetries) {
                Logger::warning("Retry {$retryCount}/{$this->maxRetries} for URL: {$url}");
                sleep(pow(2, $retryCount)); // Exponential backoff
                return $this->fetchPage($url, $retryCount + 1);
            }
            
            Logger::error("Failed to fetch {$url}:  HTTP {$httpCode}, Error: {$error}");
            return false;
        }
        
        return $response;
    }
    
    public function parseProduct($html, $url) {
        if (!$html) {
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
            // Extraer número del precio (soporta formatos:  12.34€, 12,34 €, €12.34, etc.)
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
        
        while ($page <= $maxPages) {
            $catalogUrl = $baseUrl . "?page={$page}";
            Logger::info("Scraping catalog page {$page}:  {$catalogUrl}");
            
            $html = $this->fetchPage($catalogUrl);
            if (!$html) {
                break;
            }
            
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
                    Logger::info("Scraped product:  {$product['name']} - {$product['price']}€");
                }
            }
            
            $page++;
        }
        
        Logger:: info("Catalog scrape completed.  Total products:  " . count($products));
        return $products;
    }
    
    private function extractProductUrls($html, $baseUrl) {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        // Adaptar este selector a la estructura real de greenice.com
        $nodes = $xpath->query("//a[contains(@class, 'product-link')] | //a[contains(@href, '/product/')] | //article//a");
        
        $urls = [];
        foreach ($nodes as $node) {
            $href = $node->getAttribute('href');
            if ($href && ! in_array($href, $urls)) {
                // Convertir URL relativa a absoluta
                if (strpos($href, 'http') !== 0) {
                    $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                }
                
                // Filtrar solo URLs de productos
                if (strpos($href, 'greenice.com') !== false && ! preg_match('/\.(jpg|png|css|js)$/i', $href)) {
                    $urls[] = $href;
                }
            }
        }
        
        return array_unique($urls);
    }
}