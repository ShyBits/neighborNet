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
$query = trim($_GET['q'] ?? '');

if (empty($query) || strlen($query) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Suchanfrage zu kurz']);
    exit;
}

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
    
    // Search for users that don't have a chat with current user yet
    $searchTerm = '%' . $query . '%';
    
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
        AND (
            u.username LIKE ? 
            OR u.first_name LIKE ? 
            OR u.last_name LIKE ?
            OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?
        )
        AND u.id NOT IN (
            SELECT DISTINCT cp2.user_id
            FROM chats c
            INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
            INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id != ?
        )
        ORDER BY u.username ASC
        LIMIT 20
    ");
    
    $stmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $userId, $userId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        'contacts' => $contacts
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

