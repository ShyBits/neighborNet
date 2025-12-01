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

$anfrageId = intval($_POST['anfrage_id'] ?? 0);

if ($anfrageId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage-ID']);
    exit;
}

$conn = getDBConnection();

try {
    $stmt = $conn->prepare("DELETE FROM anfragen WHERE id = ? AND user_id = ?");
    $stmt->execute([$anfrageId, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Anfrage erfolgreich gelöscht'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Anfrage nicht gefunden']);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

