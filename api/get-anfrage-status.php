<?php
header('Content-Type: application/json');

// Lade Session-Konfiguration
require_once '../config/config.php';
require_once '../sql/create-tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur GET erlaubt']);
    exit;
}

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$angebotId = intval($_GET['angebot_id'] ?? 0);
$contactId = intval($_GET['contact_id'] ?? 0);

if ($angebotId <= 0 || $contactId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

$conn = getDBConnection();
$currentUserId = intval($_SESSION['user_id']);

try {
    // Hole Anfrage-Status
    // an.user_id = Hilfesuchender (Ersteller der Hilfsanfrage)
    // a.user_id = Hilfsgeber (Person, die die Anfrage sendet)
    $stmt = $conn->prepare("
        SELECT 
            a.id as anfrage_id,
            a.status,
            a.confirmed_at,
            a.completed_by_helper,
            a.completed_by_requester,
            an.user_id as requester_id,
            a.user_id as helper_id
        FROM anfragen a
        INNER JOIN angebote an ON a.angebot_id = an.id
        WHERE a.angebot_id = ? 
        AND (
            (an.user_id = ? AND a.user_id = ?) OR 
            (an.user_id = ? AND a.user_id = ?)
        )
        LIMIT 1
    ");
    // Parameter: angebotId, currentUserId (als requester), contactId (als helper), contactId (als requester), currentUserId (als helper)
    $stmt->execute([$angebotId, $currentUserId, $contactId, $contactId, $currentUserId]);
    $anfrage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anfrage) {
        echo json_encode([
            'success' => true,
            'has_anfrage' => false
        ]);
        exit;
    }
    
    $requesterId = intval($anfrage['requester_id']); // Hilfesuchender (Ersteller der Hilfsanfrage)
    $helperId = intval($anfrage['helper_id']); // Hilfsgeber (Person, die die Anfrage sendet)
    $isRequester = ($currentUserId === $requesterId); // Ist der aktuelle Benutzer der Hilfesuchende?
    
    echo json_encode([
        'success' => true,
        'has_anfrage' => true,
        'anfrage_id' => intval($anfrage['anfrage_id']),
        'status' => $anfrage['status'],
        'confirmed_at' => $anfrage['confirmed_at'],
        'completed_by_helper' => $anfrage['completed_by_helper'],
        'completed_by_requester' => $anfrage['completed_by_requester'],
        'is_requester' => $isRequester,
        'is_helper' => !$isRequester
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

