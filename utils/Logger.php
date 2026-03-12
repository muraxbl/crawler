<?php

class Logger {
    private static $logFile;
    
    public static function init($filename = 'app.log') {
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        self::$logFile = LOG_PATH . '/' . $filename;
    }
    
    public static function log($message, $level = 'INFO') {
        if (! self::$logFile) {
            self::init();
        }
        
        $timestamp = date('Y-m-d H:i: s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }
    
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    public static function warning($message) {
        self::log($message, 'WARNING');
    }
}