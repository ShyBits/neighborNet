<?php
header('Content-Type: application/json');

// Lade Session-Konfiguration
require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$requestedUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($requestedUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Benutzer-ID']);
    exit;
}

$conn = getDBConnection();

try {
    // Hole Benutzerdaten
    $userStmt = $conn->prepare("SELECT id, username, avatar, first_name, last_name, city, created_at FROM users WHERE id = ?");
    $userStmt->execute([$requestedUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Benutzer nicht gefunden']);
        exit;
    }
    
    // Hole Statistiken
    $laufendeAnfragenStmt = $conn->prepare("SELECT COUNT(*) FROM anfragen WHERE user_id = ?");
    $laufendeAnfragenStmt->execute([$requestedUserId]);
    $laufendeAnfragenCount = $laufendeAnfragenStmt->fetchColumn();
    
    $amHelfenStmt = $conn->prepare("SELECT COUNT(DISTINCT a.id) FROM angebote a INNER JOIN anfragen anf ON a.id = anf.angebot_id WHERE a.user_id = ?");
    $amHelfenStmt->execute([$requestedUserId]);
    $amHelfenCount = $amHelfenStmt->fetchColumn();
    
    $geholfenStmt = $conn->prepare("SELECT COUNT(*) FROM anfragen WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $geholfenStmt->execute([$requestedUserId]);
    $geholfenCount = $geholfenStmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => intval($user['id']),
            'username' => $user['username'],
            'avatar' => $user['avatar'] ?: 'assets/images/profile-placeholder.svg',
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'city' => $user['city'],
            'created_at' => $user['created_at'],
            'stats' => [
                'laufende_anfragen' => intval($laufendeAnfragenCount),
                'am_helfen' => intval($amHelfenCount),
                'geholfen' => intval($geholfenCount)
            ]
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

