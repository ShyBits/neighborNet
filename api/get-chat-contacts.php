<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

// Ensure tableExists function is available
if (file_exists('../sql/universal-schema.php')) {
    require_once '../sql/universal-schema.php';
}

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$limit = intval($_GET['limit'] ?? 50);
$offset = intval($_GET['offset'] ?? 0);

// Ensure all tables are created (including chat_favorites)
if (function_exists('createTables')) {
    createTables();
} elseif (function_exists('ensureAllTables')) {
    ensureAllTables();
}

$conn = getDBConnection();

if (function_exists('columnExists')) {
    if (!columnExists($conn, 'messages', 'message')) {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            $messageType = ($driver === 'mysql') ? 'LONGTEXT' : 'TEXT';
            $conn->exec("ALTER TABLE `messages` ADD COLUMN `message` {$messageType} NULL");
            $stmt = $conn->query("SELECT COUNT(*) as count FROM `messages`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['count'] == 0) {
                if ($driver === 'mysql') {
                    $conn->exec("ALTER TABLE `messages` MODIFY COLUMN `message` {$messageType} NOT NULL");
                } elseif ($driver === 'pgsql') {
                    $conn->exec("ALTER TABLE `messages` ALTER COLUMN `message` SET NOT NULL");
                }
            }
        } catch(PDOException $e) {
            error_log("Fehler beim Hinzufügen der message-Spalte: " . $e->getMessage());
        }
    }
}

try {
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'mysql') {
        $activityStatusSQL = "
            CASE 
                WHEN ua.last_activity IS NULL THEN 'offline'
                WHEN ua.last_activity >= DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN 'online'
                WHEN ua.last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 'away'
                ELSE 'offline'
            END as status
        ";
    } elseif ($driver === 'pgsql') {
        $activityStatusSQL = "
            CASE 
                WHEN ua.last_activity IS NULL THEN 'offline'
                WHEN ua.last_activity >= NOW() - INTERVAL '2 minutes' THEN 'online'
                WHEN ua.last_activity >= NOW() - INTERVAL '15 minutes' THEN 'away'
                ELSE 'offline'
            END as status
        ";
    } else {
        $activityStatusSQL = "
            CASE 
                WHEN ua.last_activity IS NULL THEN 'offline'
                WHEN datetime(ua.last_activity) >= datetime('now', '-2 minutes') THEN 'online'
                WHEN datetime(ua.last_activity) >= datetime('now', '-15 minutes') THEN 'away'
                ELSE 'offline'
            END as status
        ";
    }
    
    if ($driver === 'mysql') {
        $concatFunction = "CONCAT";
        $trimFunction = "TRIM";
    } elseif ($driver === 'pgsql') {
        $concatFunction = "CONCAT";
        $trimFunction = "TRIM";
    } else {
        $concatFunction = "(";
        $trimFunction = "TRIM";
    }
    
    if ($driver === 'sqlite') {
        $nameSQL = "TRIM(COALESCE(u2.first_name, '') || ' ' || COALESCE(u2.last_name, '')) as name";
    } else {
        $nameSQL = "TRIM(CONCAT(COALESCE(u2.first_name, ''), ' ', COALESCE(u2.last_name, ''))) as name";
    }
    
    // Check if chat_archived table exists
    $archivedTableExists = false;
    if (function_exists('tableExists')) {
        $archivedTableExists = tableExists($conn, 'chat_archived');
    } else {
        // If tableExists function doesn't exist, assume table exists after ensureAllTables()
        // This is safe since ensureAllTables() should have created it
        try {
            $testStmt = $conn->query("SELECT 1 FROM chat_archived LIMIT 1");
            $archivedTableExists = true;
        } catch(PDOException $e) {
            $archivedTableExists = false;
        }
    }
    
    if ($archivedTableExists) {
        $stmt = $conn->prepare("
            SELECT 
                c.id as chat_id,
                cp2.user_id as user_id,
                u2.username as username,
                {$nameSQL},
                u2.avatar as avatar,
                cm.last_message_at,
                cm.last_message_id,
                cm.unread_count_user_1,
                cm.unread_count_user_2,
                CASE 
                    WHEN (SELECT MIN(user_id) FROM chat_participants WHERE chat_id = c.id) = ? 
                    THEN cm.unread_count_user_1
                    ELSE cm.unread_count_user_2
                END as unread_count,
                m.message as last_message,
                m.sender_id as last_message_sender_id,
                m.encrypted as last_message_encrypted,
                m.file_path as last_message_file_path,
                m.file_type as last_message_file_type,
                ua.last_activity,
                {$activityStatusSQL},
                CASE WHEN cf.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
            FROM chats c
            INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
            INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id != ?
            LEFT JOIN users u2 ON cp2.user_id = u2.id
            LEFT JOIN chat_metadata cm ON c.id = cm.chat_id
            LEFT JOIN messages m ON (cm.last_message_id = m.id AND m.chat_id = c.id)
            LEFT JOIN user_activity ua ON ua.user_id = cp2.user_id
            LEFT JOIN chat_favorites cf ON cf.user_id = ? AND cf.contact_user_id = cp2.user_id
            LEFT JOIN chat_archived ca ON ca.user_id = ? AND ca.chat_id = c.id
            WHERE ca.id IS NULL
            ORDER BY COALESCE(cm.last_message_at, c.created_at) DESC
            LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                c.id as chat_id,
                cp2.user_id as user_id,
                u2.username as username,
                {$nameSQL},
                u2.avatar as avatar,
                cm.last_message_at,
                cm.last_message_id,
                cm.unread_count_user_1,
                cm.unread_count_user_2,
                CASE 
                    WHEN (SELECT MIN(user_id) FROM chat_participants WHERE chat_id = c.id) = ? 
                    THEN cm.unread_count_user_1
                    ELSE cm.unread_count_user_2
                END as unread_count,
                m.message as last_message,
                m.sender_id as last_message_sender_id,
                m.encrypted as last_message_encrypted,
                m.file_path as last_message_file_path,
                m.file_type as last_message_file_type,
                ua.last_activity,
                {$activityStatusSQL},
                CASE WHEN cf.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
            FROM chats c
            INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
            INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id != ?
            LEFT JOIN users u2 ON cp2.user_id = u2.id
            LEFT JOIN chat_metadata cm ON c.id = cm.chat_id
            LEFT JOIN messages m ON (cm.last_message_id = m.id AND m.chat_id = c.id)
            LEFT JOIN user_activity ua ON ua.user_id = cp2.user_id
            LEFT JOIN chat_favorites cf ON cf.user_id = ? AND cf.contact_user_id = cp2.user_id
            ORDER BY COALESCE(cm.last_message_at, c.created_at) DESC
            LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
        ");
        $stmt->execute([$userId, $userId, $userId, $userId]);
    }
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($archivedTableExists) {
        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT c.id) as total
            FROM chats c
            INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
            INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id != ?
            LEFT JOIN chat_archived ca ON ca.user_id = ? AND ca.chat_id = c.id
            WHERE ca.id IS NULL
        ");
        $countStmt->execute([$userId, $userId, $userId]);
    } else {
        $countStmt = $conn->prepare("
            SELECT COUNT(DISTINCT c.id) as total
            FROM chats c
            INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
            INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id != ?
        ");
        $countStmt->execute([$userId, $userId]);
    }
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $contacts = [];
    foreach ($chats as $chat) {
        $chatId = intval($chat['chat_id']);
        $lastMessageSenderId = isset($chat['last_message_sender_id']) ? intval($chat['last_message_sender_id']) : null;
        $isLastMessageFromCurrentUser = $lastMessageSenderId === $userId;
        
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
        
        $status = $chat['status'] ?? 'offline';
        if (!in_array($status, ['online', 'away', 'offline'])) {
            $status = 'offline';
        }
        
        $contacts[] = [
            'chat_id' => $chatId,
            'user_id' => intval($chat['user_id']),
            'username' => $chat['username'] ?? '',
            'name' => trim($chat['name']) ?: $chat['username'] ?? 'Unbekannt',
            'avatar' => $chat['avatar'] ?? null,
            'last_message' => $chat['last_message'] ?? null,
            'last_message_at' => $chat['last_message_at'] ?? null,
            'last_message_sender_id' => $lastMessageSenderId,
            'last_message_encrypted' => false, // Encryption removed
            'last_message_file_path' => $chat['last_message_file_path'] ?? null,
            'last_message_file_type' => $chat['last_message_file_type'] ?? null,
            'is_last_message_from_me' => $isLastMessageFromCurrentUser,
            'unread_count' => intval($chat['unread_count'] ?? 0),
            'status' => $status,
            'last_activity' => $chat['last_activity'] ?? null,
            'participant_ids' => $participantIds,
            'is_favorite' => isset($chat['is_favorite']) ? (bool)$chat['is_favorite'] : false
        ];
    }
    
    echo json_encode([
        'success' => true,
        'contacts' => $contacts,
        'current_user_id' => $userId,
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

