<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// Ensure all database tables exist
if (function_exists('ensureDatabaseTables')) {
    ensureDatabaseTables();
} elseif (function_exists('ensureAllTables')) {
    ensureAllTables();
} elseif (function_exists('createTables')) {
    createTables();
}

$userId = intval($_SESSION['user_id']);
$conn = getDBConnection();

try {
    // LAUFENDE ANFRAGEN: Anfragen, die der Benutzer erstellt hat (als requester) und die noch aktiv sind
    // angebote.user_id = requester_id (Person, die Hilfe sucht)
    // anfragen.user_id = helper_id (Person, die Hilfe anbietet)
    $laufendeAnfragen = $conn->prepare("
        SELECT COUNT(*) 
        FROM anfragen a
        INNER JOIN angebote an ON a.angebot_id = an.id
        WHERE an.user_id = ? 
        AND a.status IN ('pending', 'accepted', 'confirmed')
    ");
    $laufendeAnfragen->execute([$userId]);
    $laufendeAnfragenCount = $laufendeAnfragen->fetchColumn();
    
    // AM HELFEN: Anfragen, bei denen der Benutzer der Helfer ist und die bestätigt wurden
    $amHelfen = $conn->prepare("
        SELECT COUNT(*) 
        FROM anfragen 
        WHERE user_id = ? 
        AND status = 'confirmed'
        AND completed_by_requester IS NULL
    ");
    $amHelfen->execute([$userId]);
    $amHelfenCount = $amHelfen->fetchColumn();
    
    // GEHOLFEN: Anfragen, bei denen der Benutzer der Helfer ist und die abgeschlossen wurden
    $geholfen = $conn->prepare("
        SELECT COUNT(*) 
        FROM anfragen 
        WHERE user_id = ? 
        AND completed_by_requester IS NOT NULL
        AND status = 'completed'
    ");
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






