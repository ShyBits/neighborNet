<?php
header('Content-Type: application/json');

// Lade Session-Konfiguration
require_once '../config/config.php';
require_once '../sql/create-tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$angebotId = intval($_POST['angebot_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($angebotId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Angebots-ID']);
    exit;
}

$conn = getDBConnection();

try {
    $stmt = $conn->prepare("INSERT INTO anfragen (angebot_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$angebotId, $_SESSION['user_id'], $message]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Anfrage erfolgreich erstellt',
        'anfrage_id' => $conn->lastInsertId()
    ]);
    
} catch(PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Anfrage bereits vorhanden']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
}
?>

