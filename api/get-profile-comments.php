<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$conn = getDBConnection();

try {
    $comments = [];
    
    echo json_encode([
        'success' => true,
        'data' => $comments
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden der Kommentare: ' . $e->getMessage()]);
}
?>






