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
$conn = getDBConnection();

try {
    // Get total unread count for all chats
    // This query correctly calculates unread count based on user position in chat
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE 
                WHEN (SELECT MIN(user_id) FROM chat_participants WHERE chat_id = c.id) = ? 
                THEN COALESCE(cm.unread_count_user_1, 0)
                ELSE COALESCE(cm.unread_count_user_2, 0)
            END
        ), 0) as total_unread
        FROM chats c
        INNER JOIN chat_participants cp ON c.id = cp.chat_id AND cp.user_id = ?
        LEFT JOIN chat_metadata cm ON c.id = cm.chat_id
    ");
    
    $stmt->execute([$userId, $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalUnread = intval($result['total_unread'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'total_unread' => $totalUnread
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

