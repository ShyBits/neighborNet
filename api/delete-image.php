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

$imagePath = $_POST['image_path'] ?? '';
$angebotId = intval($_POST['angebot_id'] ?? 0);

if (empty($imagePath) || $angebotId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();
    
    $stmt = $conn->prepare("SELECT a.user_id FROM angebote a WHERE a.id = ?");
    $stmt->execute([$angebotId]);
    $angebot = $stmt->fetch();
    
    if (!$angebot) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Angebot nicht gefunden']);
        exit;
    }
    
    if (intval($angebot['user_id']) !== intval($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
        exit;
    }
    
    $deleteStmt = $conn->prepare("DELETE FROM angebote_images WHERE angebot_id = ? AND image_path = ?");
    $deleteStmt->execute([$angebotId, $imagePath]);
    
    if ($deleteStmt->rowCount() > 0) {
        $filePath = '../' . $imagePath;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Bild erfolgreich gelöscht']);
    } else {
        $conn->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bild nicht gefunden']);
    }
    
} catch(PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

