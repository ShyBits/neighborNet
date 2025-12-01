<?php
header('Content-Type: application/json');

// Lade Session-Konfiguration
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

$_SESSION['is_guest'] = true;
$_SESSION['user_id'] = 'guest_' . uniqid();
$_SESSION['user_name'] = 'Gast';
$_SESSION['user_avatar'] = 'assets/images/profile-placeholder.svg';

echo json_encode([
    'success' => true,
    'message' => 'Als Gast angemeldet',
    'is_guest' => true
]);
?>

