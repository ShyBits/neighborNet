<?php
header('Content-Type: application/json');

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
    
    // Prüfe ob bereits eine aktive Anfrage existiert (nicht rejected, cancelled, completed)
    // Wenn rejected/cancelled/completed, darf wieder eine neue Anfrage gesendet werden
    $stmt = $conn->prepare("
        SELECT a.id, a.status
        FROM anfragen a
        INNER JOIN angebote an ON a.angebot_id = an.id
        WHERE a.angebot_id = ? 
        AND a.user_id = ?
        AND an.user_id = ?
        AND a.status IN ('pending', 'accepted', 'confirmed')
        LIMIT 1
    ");
    $stmt->execute([$angebotId, $helperId, $receiverId]);
    $existingAnfrage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAnfrage) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Sie haben bereits eine aktive Anfrage für dieses Angebot gesendet',
            'anfrage_id' => intval($existingAnfrage['id']),
            'status' => $existingAnfrage['status']
        ]);
        exit;
    }
    
    // Hole Angebot mit allen wichtigen Infos
    $stmt = $conn->prepare("
        SELECT id, title, user_id, category, start_date, start_time, end_time, address, 
               COALESCE(required_persons, 1) as required_persons 
        FROM angebote 
        WHERE id = ?
    ");
    $stmt->execute([$angebotId]);
    $angebot = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$angebot) {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Angebot nicht gefunden']);
        exit;
    }
    
    $requiredPersons = intval($angebot['required_persons'] ?? 1);
    
    // Prüfe wie viele Anfragen bereits angenommen wurden (accepted oder confirmed)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as accepted_count
        FROM anfragen
        WHERE angebot_id = ?
        AND status IN ('accepted', 'confirmed')
    ");
    $stmt->execute([$angebotId]);
    $acceptedCount = intval($stmt->fetch(PDO::FETCH_ASSOC)['accepted_count'] ?? 0);
    
    if ($acceptedCount >= $requiredPersons) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Die maximale Anzahl von {$requiredPersons} Helfer" . ($requiredPersons > 1 ? "n" : "") . " wurde bereits erreicht."
        ]);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO anfragen (angebot_id, user_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$angebotId, $helperId]);
    $anfrageId = $conn->lastInsertId();
    
    // Formatiere Datum und Zeit
    $startDate = $angebot['start_date'];
    $startTime = $angebot['start_time'] ? substr($angebot['start_time'], 0, 5) : '';
    $endTime = $angebot['end_time'] ? substr($angebot['end_time'], 0, 5) : '';
    
    $dateObj = DateTime::createFromFormat('Y-m-d', $startDate);
    $formattedDate = $dateObj ? $dateObj->format('d.m.Y') : $startDate;
    
    $timeStr = '';
    if ($startTime && $endTime) {
        $timeStr = $startTime . ' - ' . $endTime . ' Uhr';
    } elseif ($startTime) {
        $timeStr = $startTime . ' Uhr';
    }
    
    // Kategorie-Farben
    $categoryColors = [
        'gartenarbeit' => '#4CAF50',
        'haushalt' => '#2196F3',
        'umzug' => '#FF9800',
        'reparatur' => '#9C27B0',
        'betreuung' => '#E91E63',
        'einkauf' => '#00BCD4',
        'sonstiges' => '#607D8B'
    ];
    
    $categoryColor = $categoryColors[$angebot['category']] ?? '#607D8B';
    
    // Erstelle kompakte Nachricht mit den wichtigsten Infos
    $messageParts = [];
    $messageParts[] = htmlspecialchars($angebot['title'], ENT_QUOTES, 'UTF-8');
    if ($formattedDate) {
        $messageParts[] = $formattedDate;
    }
    if ($timeStr) {
        $messageParts[] = $timeStr;
    }
    if ($angebot['address']) {
        $addressParts = explode(',', $angebot['address']);
        $messageParts[] = trim($addressParts[0]); // Nur Straße, nicht PLZ/Stadt
    }
    
    $messageText = implode(' • ', $messageParts);
    
    // Erstelle spezielle Chat-Nachricht mit Buttons
    $messageData = json_encode([
        'type' => 'anfrage_request',
        'anfrage_id' => $anfrageId,
        'angebot_id' => $angebotId,
        'angebot_title' => $angebot['title'],
        'category' => $angebot['category'],
        'category_color' => $categoryColor,
        'helper_id' => $helperId,
        'requester_id' => $receiverId
    ]);
    
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

