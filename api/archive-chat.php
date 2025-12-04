<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

// Ensure all database tables exist (including chat_archived)
if (function_exists('ensureDatabaseTables')) {
    ensureDatabaseTables();
} elseif (function_exists('ensureAllTables')) {
    ensureAllTables();
} elseif (function_exists('createTables')) {
    createTables();
}

$userId = intval($_SESSION['user_id']);
$chatId = isset($_POST['chat_id']) ? intval($_POST['chat_id']) : null;

if (!$chatId || $chatId <= 0) {
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
    
    // Check if already archived
    $stmt = $conn->prepare("SELECT id FROM chat_archived WHERE user_id = ? AND chat_id = ?");
    $stmt->execute([$userId, $chatId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Already archived - unarchive
        $stmt = $conn->prepare("DELETE FROM chat_archived WHERE user_id = ? AND chat_id = ?");
        $stmt->execute([$userId, $chatId]);
        
        echo json_encode([
            'success' => true,
            'archived' => false,
            'message' => 'Chat wiederhergestellt'
        ]);
    } else {
        // Archive
        $stmt = $conn->prepare("INSERT INTO chat_archived (user_id, chat_id) VALUES (?, ?)");
        $stmt->execute([$userId, $chatId]);
        
        echo json_encode([
            'success' => true,
            'archived' => true,
            'message' => 'Chat archiviert'
        ]);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

