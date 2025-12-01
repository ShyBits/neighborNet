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
$limit = intval($_GET['limit'] ?? 50);
$offset = intval($_GET['offset'] ?? 0);

$conn = getDBConnection();

try {
    // Get database driver for SQL compatibility
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    // Determine time-based status calculation based on database
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
        // SQLite or other
        $activityStatusSQL = "
            CASE 
                WHEN ua.last_activity IS NULL THEN 'offline'
                WHEN datetime(ua.last_activity) >= datetime('now', '-2 minutes') THEN 'online'
                WHEN datetime(ua.last_activity) >= datetime('now', '-15 minutes') THEN 'away'
                ELSE 'offline'
            END as status
        ";
    }
    
    // Get users that don't have any messages exchanged with current user yet
    // A user is considered "uncontacted" if:
    // 1. They don't have a chat with messages, OR
    // 2. They have a chat but no messages have been sent in that chat
    // Note: LIMIT and OFFSET must be integers, not bound parameters
    $limit = max(1, min(100, intval($limit))); // Ensure valid range
    $offset = max(0, intval($offset)); // Ensure non-negative
    
    // Get users who have NOT exchanged any messages with current user
    // A user is "uncontacted" if there are no messages between them and the current user
    $stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.username,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as name,
            u.avatar,
            ua.last_activity,
            {$activityStatusSQL}
        FROM users u
        LEFT JOIN user_activity ua ON ua.user_id = u.id
        WHERE u.id != ?
        AND NOT EXISTS (
            -- Exclude users who have exchanged at least one message with current user
            SELECT 1
            FROM messages m
            WHERE (
                (m.sender_id = ? AND m.receiver_id = u.id)
                OR 
                (m.sender_id = u.id AND m.receiver_id = ?)
            )
        )
        ORDER BY u.username ASC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
    ");
    
    $stmt->execute([$userId, $userId, $userId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM users u
        WHERE u.id != ?
        AND NOT EXISTS (
            -- Exclude users who have exchanged at least one message with current user
            SELECT 1
            FROM messages m
            WHERE (
                (m.sender_id = ? AND m.receiver_id = u.id)
                OR 
                (m.sender_id = u.id AND m.receiver_id = ?)
            )
        )
    ");
    $countStmt->execute([$userId, $userId, $userId]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Format contacts
    $contacts = [];
    foreach ($users as $user) {
        // Determine status: online (last 2 min), away (2-15 min), offline (>15 min or no activity)
        $status = $user['status'] ?? 'offline';
        if (!in_array($status, ['online', 'away', 'offline'])) {
            $status = 'offline';
        }
        
        $contacts[] = [
            'user_id' => intval($user['user_id']),
            'username' => $user['username'] ?? '',
            'name' => trim($user['name']) ?: $user['username'] ?? 'Unbekannt',
            'avatar' => $user['avatar'] ?? null,
            'status' => $status,
            'last_activity' => $user['last_activity'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'contacts' => $contacts,
        'total' => intval($total),
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

