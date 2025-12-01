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
$chatId = intval($_POST['chat_id'] ?? 0);

if ($chatId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Chat-ID']);
    exit;
}

$conn = getDBConnection();

try {
    // Verify user is participant in this chat
    $stmt = $conn->prepare("
        SELECT chat_id FROM chat_participants 
        WHERE chat_id = ? AND user_id = ?
    ");
    $stmt->execute([$chatId, $userId]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
        exit;
    }
    
    $conn->beginTransaction();
    
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages 
        SET read_at = NOW() 
        WHERE chat_id = ? 
        AND receiver_id = ? 
        AND read_at IS NULL
    ");
    $stmt->execute([$chatId, $userId]);
    
    // Update unread count in metadata
    $stmt = $conn->prepare("
        SELECT user_id FROM chat_participants 
        WHERE chat_id = ? 
        ORDER BY user_id ASC
    ");
    $stmt->execute([$chatId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($participants) === 2) {
        $isFirstUser = intval($participants[0]['user_id']) === $userId;
        $countField = $isFirstUser ? 'unread_count_user_1' : 'unread_count_user_2';
        
        $stmt = $conn->prepare("
            UPDATE chat_metadata 
            SET {$countField} = 0 
            WHERE chat_id = ?
        ");
        $stmt->execute([$chatId]);
    }
    
    // Update participant last_read
    $stmt = $conn->prepare("
        SELECT MAX(id) as last_message_id 
        FROM messages 
        WHERE chat_id = ?
    ");
    $stmt->execute([$chatId]);
    $lastMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastMessage && $lastMessage['last_message_id']) {
        $stmt = $conn->prepare("
            UPDATE chat_participants 
            SET last_read_message_id = ?, last_read_at = NOW() 
            WHERE chat_id = ? AND user_id = ?
        ");
        $stmt->execute([$lastMessage['last_message_id'], $chatId, $userId]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Als gelesen markiert'
    ]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

