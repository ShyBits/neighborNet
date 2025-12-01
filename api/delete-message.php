<?php
header('Content-Type: application/json');

// Lade Session-Konfiguration
require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$messageId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

if (!$messageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Nachrichten-ID']);
    exit;
}

$conn = getDBConnection();

try {
    // Prüfe ob die Nachricht dem Benutzer gehört und ob er sie gesendet hat
    $stmt = $conn->prepare("
        SELECT m.id, m.chat_id, m.sender_id, cp.user_id
        FROM messages m
        INNER JOIN chat_participants cp ON m.chat_id = cp.chat_id
        WHERE m.id = ? AND cp.user_id = ? AND m.sender_id = ?
    ");
    $stmt->execute([$messageId, $userId, $userId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Nachricht nicht gefunden oder Sie haben keine Berechtigung']);
        exit;
    }
    
    // Lösche die Nachricht
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    
    // Lösche auch zugehörige Dateien (optional - falls Dateien separat gespeichert werden)
    // Hier könnten Sie auch Dateien vom Server löschen
    
    echo json_encode([
        'success' => true,
        'message' => 'Nachricht gelöscht'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

