<?php
// Diese Datei wird von angebote-karte.php eingebunden (vor header.php)
// Sicherstellen dass Session verfügbar ist
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/config.php';
}

// Prüfe ob Karten-Einwilligung vorhanden ist (nur für PHP-Seite, JavaScript setzt Cookie)
$hasConsent = isset($_COOKIE['map_consent']) && $_COOKIE['map_consent'] === 'accepted';
?>

