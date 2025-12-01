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
$conn = getDBConnection();

try {
    // Update or insert user activity
    // Use INSERT ... ON DUPLICATE KEY UPDATE for MySQL
    // For other databases, use UPSERT syntax
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'mysql') {
        $stmt = $conn->prepare("
            INSERT INTO user_activity (user_id, last_activity) 
            VALUES (?, NOW())
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
    } elseif ($driver === 'pgsql') {
        $stmt = $conn->prepare("
            INSERT INTO user_activity (user_id, last_activity) 
            VALUES (?, NOW())
            ON CONFLICT (user_id) 
            DO UPDATE SET last_activity = NOW()
        ");
    } else {
        // SQLite or other - use REPLACE or DELETE + INSERT
        $stmt = $conn->prepare("
            INSERT OR REPLACE INTO user_activity (user_id, last_activity) 
            VALUES (?, datetime('now'))
        ");
    }
    
    $stmt->execute([$userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Aktivität aktualisiert'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

