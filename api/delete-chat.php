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
    
    // Remove user from chat participants (soft delete - only remove participant, don't delete chat)
    $stmt = $conn->prepare("DELETE FROM chat_participants WHERE chat_id = ? AND user_id = ?");
    $stmt->execute([$chatId, $userId]);
    
    // Also remove from favorites if favorited
    $stmt = $conn->prepare("DELETE FROM chat_favorites WHERE user_id = ? AND contact_user_id = (
        SELECT user_id FROM chat_participants WHERE chat_id = ? AND user_id != ? LIMIT 1
    )");
    // Get the other participant's user_id first
    $stmt2 = $conn->prepare("SELECT user_id FROM chat_participants WHERE chat_id = ? AND user_id != ? LIMIT 1");
    $stmt2->execute([$chatId, $userId]);
    $otherUserId = $stmt2->fetchColumn();
    
    if ($otherUserId) {
        $stmt3 = $conn->prepare("DELETE FROM chat_favorites WHERE user_id = ? AND contact_user_id = ?");
        $stmt3->execute([$userId, $otherUserId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Chat gelöscht'
    ]);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

