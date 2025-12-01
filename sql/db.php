<?php
// Datenbankverbindung

define('DB_HOST', '192.168.178.200');
define('DB_PORT', '3306');
define('DB_NAME', 'itab');
define('DB_USER', 'root');
define('DB_PASS', 'itab123');

function getDBConnection() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch(PDOException $e) {
        die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
    }
}

function initDatabase() {
    try {
        $conn = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn->exec("USE " . DB_NAME);
        
        return true;
    } catch(PDOException $e) {
        die("Datenbankinitialisierung fehlgeschlagen: " . $e->getMessage());
    }
}
?>

