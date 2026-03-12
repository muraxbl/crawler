<?php

class SessionManager {
    private $cookieFile;
    private $sessionId;
    
    public function __construct() {
        if (!is_dir(CACHE_PATH)) {
            mkdir(CACHE_PATH, 0755, true);
        }
        
        $this->sessionId = md5(date('Y-m-d') . 'greenice_session');
        $this->cookieFile = CACHE_PATH .  "/cookies_{$this->sessionId}.txt";
    }
    
    public function getCookieFile() {
        return $this->cookieFile;
    }
    
    public function clearCookies() {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }
    
    // Rotar sesión diariamente
    public function rotateIfNeeded() {
        if (file_exists($this->cookieFile)) {
            $fileAge = time() - filemtime($this->cookieFile);
            // Rotar después de 24 horas
            if ($fileAge > 86400) {
                $this->clearCookies();
                Logger::info("Session cookies rotated");
            }
        }
    }
}