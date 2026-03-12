<?php

class ProxyManager {
    private $proxies = [];
    private $useProxy = false;
    
    public function __construct() {
        // Por ahora sin proxies
        $this->useProxy = false;
    }
    
    public function getNext() {
        return null;
    }
    
    public function isEnabled() {
        return false;
    }
    
    public function markAsFailed($proxyUrl) {
        // No-op
    }
}
