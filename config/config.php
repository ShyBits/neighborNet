<?php
function initSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1']) 
               || strpos($httpHost, '.local') !== false
               || strpos($httpHost, '192.168.') !== false
               || strpos($httpHost, '10.') !== false;
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
               || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', '0');
    
    session_name('NEIGHBORNET_SESSION');
    
    $domain = '';
    
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(0, '/', $domain, $isHttps, true);
    }
    
    session_start();
}

// Session initialisieren
initSession();

require_once __DIR__ . '/../sql/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !isset($_SESSION['is_guest']);
}

function isGuest() {
    return isset($_SESSION['is_guest']);
}

function canCreateContent() {
    return isset($_SESSION['user_id']) && !isset($_SESSION['is_guest']);
}

function getUserName() {
    if (isset($_SESSION['is_guest'])) {
        return 'Gast';
    }
    return $_SESSION['user_name'] ?? '';
}

function getUserAvatar() {
    return $_SESSION['user_avatar'] ?? 'assets/images/profile-placeholder.svg';
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>

