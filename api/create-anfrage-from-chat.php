<?php
header('Content-Type: application/json');

// Lade Session-Konfiguration
require_once '../config/config.php';
require_once '../sql/create-tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$chatId = intval($_POST['chat_id'] ?? 0);
$angebotId = intval($_POST['angebot_id'] ?? 0);
$receiverId = intval($_POST['receiver_id'] ?? 0);
$helperId = intval($_SESSION['user_id']);

if ($chatId <= 0 || $angebotId <= 0 || $receiverId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();
    
    // Prüfe ob bereits eine pending Anfrage für dieses Angebot und diesen Chat existiert
    $stmt = $conn->prepare("
        SELECT a.id, a.status
        FROM anfragen a
        INNER JOIN angebote an ON a.angebot_id = an.id
        WHERE a.angebot_id = ? 
        AND a.user_id = ?
        AND an.user_id = ?
        AND a.status IN ('pending', 'accepted')
        LIMIT 1
    ");
    $stmt->execute([$angebotId, $helperId, $receiverId]);
    $existingAnfrage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAnfrage) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Sie haben bereits eine Anfrage für dieses Angebot gesendet',
            'anfrage_id' => intval($existingAnfrage['id']),
            'status' => $existingAnfrage['status']
        ]);
        exit;
    }
    
    // Hole Angebot-Informationen
    $stmt = $conn->prepare("SELECT id, title, user_id FROM angebote WHERE id = ?");
    $stmt->execute([$angebotId]);
    $angebot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$angebot) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Angebot nicht gefunden']);
        exit;
    }
    
    // Erstelle Anfrage
    $stmt = $conn->prepare("INSERT INTO anfragen (angebot_id, user_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$angebotId, $helperId]);
    $anfrageId = $conn->lastInsertId();
    
    // Erstelle spezielle Chat-Nachricht mit Buttons
    $messageData = json_encode([
        'type' => 'anfrage_request',
        'anfrage_id' => $anfrageId,
        'angebot_id' => $angebotId,
        'angebot_title' => $angebot['title'],
        'helper_id' => $helperId,
        'requester_id' => $receiverId
    ]);
    
    $messageText = "Ich biete Ihnen meine Hilfe bei Ihrer Anfrage \"" . htmlspecialchars($angebot['title'], ENT_QUOTES, 'UTF-8') . "\" an.";
    
    // Speichere Nachricht mit speziellem Format
    $stmt = $conn->prepare("
        INSERT INTO messages (chat_id, sender_id, receiver_id, message, encrypted, file_path, file_type) 
        VALUES (?, ?, ?, ?, 0, ?, 'anfrage_request')
    ");
    $stmt->execute([$chatId, $helperId, $receiverId, $messageText, $messageData]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Anfrage erfolgreich erstellt',
        'anfrage_id' => $anfrageId,
        'message_id' => $conn->lastInsertId()
    ]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

