<?php

class Throttler {
    private $minDelay;
    private $maxDelay;
    private $lastRequestTime = 0;
    
    public function __construct($minSeconds = 3, $maxSeconds = 8) {
        $this->minDelay = $minSeconds;
        $this->maxDelay = $maxSeconds;
    }
    
    public function throttle() {
        $currentTime = microtime(true);
        
        if ($this->lastRequestTime > 0) {
            $elapsed = $currentTime - $this->lastRequestTime;
            $requiredDelay = $this->getRandomDelay();
            
            if ($elapsed < $requiredDelay) {
                $sleepTime = ($requiredDelay - $elapsed) * 1000000; // microsegundos
                usleep((int)$sleepTime);
            }
        }
        
        $this->lastRequestTime = microtime(true);
    }
    
    private function getRandomDelay() {
        return mt_rand($this->minDelay * 100, $this->maxDelay * 100) / 100;
    }
    
    public function setDelays($min, $max) {
        $this->minDelay = $min;
        $this->maxDelay = $max;
    }
}