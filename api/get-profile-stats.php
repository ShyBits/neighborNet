<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$conn = getDBConnection();

try {
    $laufendeAnfragen = $conn->prepare("SELECT COUNT(*) FROM anfragen WHERE user_id = ?");
    $laufendeAnfragen->execute([$userId]);
    $laufendeAnfragenCount = $laufendeAnfragen->fetchColumn();
    
    $amHelfen = $conn->prepare("SELECT COUNT(DISTINCT a.id) FROM angebote a INNER JOIN anfragen anf ON a.id = anf.angebot_id WHERE a.user_id = ?");
    $amHelfen->execute([$userId]);
    $amHelfenCount = $amHelfen->fetchColumn();
    
    $geholfen = $conn->prepare("SELECT COUNT(*) FROM anfragen WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $geholfen->execute([$userId]);
    $geholfenCount = $geholfen->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'laufende_anfragen' => intval($laufendeAnfragenCount),
            'am_helfen' => intval($amHelfenCount),
            'geholfen' => intval($geholfenCount)
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden der Statistiken: ' . $e->getMessage()]);
}
?>






