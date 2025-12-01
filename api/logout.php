<?php
// Lade Session-Konfiguration
require_once '../config/config.php';

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Erfolgreich abgemeldet']);
} else {
    // Relativer Pfad zum Root
    header('Location: ../index.php');
    exit;
}
?>

