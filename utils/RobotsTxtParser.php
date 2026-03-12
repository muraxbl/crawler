<?php

class RobotsTxtParser {
    private $rules = [];
    private $crawlDelay = 0;
    private $domain;
    
    public function __construct($domain) {
        $this->domain = $domain;
        $this->parse();
    }
    
    private function parse() {
        $robotsUrl = rtrim($this->domain, '/') . '/robots.txt';
        
        $ch = curl_init($robotsUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'PriceMonitorBot/1.0',
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$content) {
            Logger::warning("Could not fetch robots.txt from {$robotsUrl}");
            return;
        }
        
        $lines = explode("\n", $content);
        $currentUserAgent = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || substr($line, 0, 1) === '#') {
                continue;
            }
            
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $currentUserAgent = trim($matches[1]);
            } elseif (preg_match('/^Disallow:\s*(.+)$/i', $line, $matches) && $currentUserAgent === '*') {
                $this->rules[] = trim($matches[1]);
            } elseif (preg_match('/^Crawl-delay:\s*(\d+)$/i', $line, $matches) && $currentUserAgent === '*') {
                $this->crawlDelay = (int)$matches[1];
            }
        }
        
        Logger::info("Parsed robots.txt: " . count($this->rules) . " rules, crawl-delay: {$this->crawlDelay}s");
    }
    
    public function isAllowed($path) {
        foreach ($this->rules as $rule) {
            if ($rule === '/' || strpos($path, $rule) === 0) {
                return false;
            }
        }
        return true;
    }
    
    public function getCrawlDelay() {
        return $this->crawlDelay;
    }
}