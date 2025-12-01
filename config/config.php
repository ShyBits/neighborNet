<?php
/**
 * Session-Konfiguration für lokale und Online-Umgebungen
 * Wichtig: Muss VOR session_start() aufgerufen werden
 */
function initSession() {
    // Prüfe ob Session bereits gestartet wurde
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    // Erkenne ob wir lokal oder online sind
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = in_array($httpHost, ['localhost', '127.0.0.1', '::1']) 
               || strpos($httpHost, '.local') !== false
               || strpos($httpHost, '192.168.') !== false
               || strpos($httpHost, '10.') !== false;
    
    // HTTPS-Erkennung verbessert für verschiedene Server-Konfigurationen
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
               || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
               || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    // Session-Einstellungen für bessere Kompatibilität
    // Diese müssen VOR session_set_cookie_params() und session_start() gesetzt werden
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', '0');
    
    // Session-Name setzen VOR session_set_cookie_params
    session_name('NEIGHBORNET_SESSION');
    
    // Domain für Cookies: Leer lassen = aktuelle Domain
    // Dies funktioniert sowohl lokal als auch online
    // WICHTIG: Nicht mit führendem Punkt setzen, da dies Probleme verursachen kann
    $domain = '';
    
    // Session-Cookie-Parameter setzen
    // WICHTIG: domain muss explizit gesetzt werden oder leer bleiben für aktuelle Domain
    // lifetime: 0 = bis Browser geschlossen wird
    // path: / = für gesamte Domain
    // domain: leer = aktuelle Domain (funktioniert lokal und online)
    // secure: nur bei HTTPS
    // httponly: true = nicht per JavaScript zugänglich (Sicherheit)
    // samesite: Lax = CSRF-Schutz, funktioniert lokal und online
    
    // Verwende Array-Format für PHP 7.3+, fallback auf alte Syntax
    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        $cookieParams = [
            'lifetime' => 0, // Session-Cookie bis Browser schließt
            'path' => '/',
            'domain' => $domain, // Leer = aktuelle Domain (funktioniert lokal und online)
            'secure' => $isHttps, // Nur bei HTTPS
            'httponly' => true, // Nicht per JavaScript zugänglich
            'samesite' => 'Lax' // CSRF-Schutz, funktioniert lokal und online
        ];
        session_set_cookie_params($cookieParams);
    } else {
        // Fallback für ältere PHP-Versionen
        // Parameter: lifetime, path, domain, secure, httponly
        session_set_cookie_params(0, '/', $domain, $isHttps, true);
    }
    
    // Session starten
    // Die Prüfung ob Session bereits aktiv ist wurde bereits am Anfang der Funktion gemacht
    session_start();
}

// Session initialisieren
initSession();

require_once __DIR__ . '/../sql/db.php';

/**
 * Login-Status wird in PHP Sessions gespeichert ($_SESSION)
 * 
 * Session-Variablen:
 * - $_SESSION['user_id'] - Benutzer-ID (gesetzt bei Login/Registrierung)
 * - $_SESSION['user_name'] - Benutzername
 * - $_SESSION['user_email'] - E-Mail-Adresse
 * - $_SESSION['user_avatar'] - Profilbild-Pfad
 * - $_SESSION['is_guest'] - Flag für Gast-Benutzer (optional)
 * 
 * Gesetzt in:
 * - api/login.php (Zeilen 51-54) - bei erfolgreichem Login
 * - api/register.php (Zeilen 66-69) - bei Registrierung
 * - api/guest-login.php (Zeilen 12-15) - bei Gast-Login
 * 
 * Gelöscht in:
 * - api/logout.php - Session wird komplett zerstört
 */

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
    // Avatar-Pfad ist relativ zum Root
    return $_SESSION['user_avatar'] ?? 'assets/images/profile-placeholder.svg';
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}
?>

