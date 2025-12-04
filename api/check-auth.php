<?php
header('Content-Type: application/json');

require_once '../config/config.php';

// Prüfe ob Benutzer angemeldet ist
$isLoggedIn = isset($_SESSION['user_id']) && !isset($_SESSION['is_guest']);
$isGuest = isset($_SESSION['is_guest']);

echo json_encode([
    'is_logged_in' => $isLoggedIn,
    'is_guest' => $isGuest,
    'user_id' => $isLoggedIn ? intval($_SESSION['user_id']) : null
]);
?>

