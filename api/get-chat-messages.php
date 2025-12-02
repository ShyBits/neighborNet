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
$chatId = intval($_GET['chat_id'] ?? 0);
$lastId = intval($_GET['last_id'] ?? 0); // Optional: only get messages after this ID

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
    
    // Get chat participants for encryption key derivation
    $participantIds = [];
    $participantsStmt = $conn->prepare("
        SELECT user_id FROM chat_participants 
        WHERE chat_id = ? 
        ORDER BY user_id ASC
    ");
    $participantsStmt->execute([$chatId]);
    $participants = $participantsStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($participants) {
        $participantIds = array_map('intval', $participants);
    }
    
    // Get messages for this chat
    // If lastId is provided, only get new messages after that ID
    if ($lastId > 0) {
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.sender_id,
                m.message,
                m.encrypted,
                m.file_path,
                m.file_type,
                m.created_at,
                u.username,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as name,
                u.avatar
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
            LIMIT 50
        ");
        $stmt->execute([$chatId, $lastId]);
    } else {
        // Get all messages (first load)
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.sender_id,
                m.message,
                m.encrypted,
                m.file_path,
                m.file_type,
                m.created_at,
                u.username,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as name,
                u.avatar
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            WHERE m.chat_id = ?
            ORDER BY m.created_at ASC
            LIMIT 100
        ");
        $stmt->execute([$chatId]);
    }
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format messages
    $formattedMessages = [];
    foreach ($messages as $msg) {
        // Ensure created_at is properly formatted (MySQL TIMESTAMP format)
        $createdAt = $msg['created_at'];
        if ($createdAt) {
            // Convert to ISO 8601 format if needed
            $timestamp = strtotime($createdAt);
            if ($timestamp) {
                $createdAt = date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        // Parse anfrage data if message type is anfrage_request
        $anfrageData = null;
        if ($msg['file_type'] === 'anfrage_request' && $msg['file_path']) {
            $anfrageData = json_decode($msg['file_path'], true);
            if ($anfrageData && isset($anfrageData['anfrage_id'])) {
                // Get anfrage status
                $anfrageStmt = $conn->prepare("SELECT id, status FROM anfragen WHERE id = ?");
                $anfrageStmt->execute([$anfrageData['anfrage_id']]);
                $anfrage = $anfrageStmt->fetch(PDO::FETCH_ASSOC);
                if ($anfrage) {
                    $anfrageData['status'] = $anfrage['status'];
                }
            }
        }
        
        $formattedMessages[] = [
            'id' => intval($msg['id']),
            'sender_id' => intval($msg['sender_id']),
            'message' => $msg['message'],
            'encrypted' => (bool)($msg['encrypted'] ?? false),
            'file_path' => $msg['file_path'] ?? null,
            'file_type' => $msg['file_type'] ?? null,
            'created_at' => $createdAt, // Real timestamp from database
            'username' => $msg['username'] ?? '',
            'name' => trim($msg['name']) ?: $msg['username'] ?? 'Unbekannt',
            'avatar' => $msg['avatar'] ?? null,
            'is_sent' => intval($msg['sender_id']) === $userId,
            'chat_id' => $chatId,
            'anfrage_data' => $anfrageData
        ];
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $formattedMessages,
        'current_user_id' => $userId,
        'participant_ids' => $participantIds
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

