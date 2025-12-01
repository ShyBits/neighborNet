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
$targetUserId = intval($_POST['user_id'] ?? 0);

if ($targetUserId <= 0 || $targetUserId == $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Benutzer-ID']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();
    
    // Check if chat already exists
    $stmt = $conn->prepare("
        SELECT c.id
        FROM chats c
        INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
        INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id = ?
        LIMIT 1
    ");
    
    $stmt->execute([$userId, $targetUserId]);
    $existingChat = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingChat) {
        // Chat already exists - get participant IDs
        $chatId = intval($existingChat['id']);
        $stmt = $conn->prepare("
            SELECT user_id FROM chat_participants 
            WHERE chat_id = ? 
            ORDER BY user_id ASC
        ");
        $stmt->execute([$chatId]);
        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $participantIds = array_map('intval', $participants);
        
        $conn->commit();
        echo json_encode([
            'success' => true,
            'chat_id' => $chatId,
            'message' => 'Chat existiert bereits',
            'participant_ids' => $participantIds,
            'current_user_id' => $userId
        ]);
        exit;
    }
    
    // Create new chat
    $stmt = $conn->prepare("INSERT INTO chats () VALUES ()");
    $stmt->execute();
    $chatId = $conn->lastInsertId();
    
    // Add participants
    $stmt = $conn->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?)");
    $stmt->execute([$chatId, $userId]);
    $stmt->execute([$chatId, $targetUserId]);
    
    // Create metadata
    $stmt = $conn->prepare("
        INSERT INTO chat_metadata (chat_id, unread_count_user_1, unread_count_user_2) 
        VALUES (?, 0, 0)
    ");
    $stmt->execute([$chatId]);
    
    $conn->commit();
    
    // Get participant IDs for encryption
    $stmt = $conn->prepare("
        SELECT user_id FROM chat_participants 
        WHERE chat_id = ? 
        ORDER BY user_id ASC
    ");
    $stmt->execute([$chatId]);
    $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $participantIds = array_map('intval', $participants);
    
    echo json_encode([
        'success' => true,
        'chat_id' => intval($chatId),
        'message' => 'Chat erfolgreich erstellt',
        'participant_ids' => $participantIds,
        'current_user_id' => $userId
    ]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

