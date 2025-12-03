<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$chatId = intval($_GET['chat_id'] ?? 0);
$lastId = intval($_GET['last_id'] ?? 0);

if ($chatId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Chat-ID']);
    exit;
}

$conn = getDBConnection();

try {
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
    
    $formattedMessages = [];
    foreach ($messages as $msg) {
        $createdAt = $msg['created_at'];
        if ($createdAt) {
            $timestamp = strtotime($createdAt);
            if ($timestamp) {
                $createdAt = date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        $anfrageData = null;
        if ($msg['file_type'] === 'anfrage_request' && $msg['file_path']) {
            $anfrageData = json_decode($msg['file_path'], true);
            if ($anfrageData && isset($anfrageData['anfrage_id'])) {
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

