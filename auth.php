<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && ! empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function login($username, $password) {
    try {
        $db = Database:: getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Actualizar último login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            Logger::info("User logged in:  {$username}");
            
            return true;
        }
        
        Logger::warning("Failed login attempt:  {$username}");
        return false;
        
    } catch (Exception $e) {
        Logger::error("Login error: " . $e->getMessage());
        return false;
    }
}

function logout() {
    session_destroy();
    header('Location: /login.php');
    exit;
}
?>

