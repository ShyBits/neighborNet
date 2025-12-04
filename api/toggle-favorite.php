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
$contactUserId = isset($_POST['contact_user_id']) ? intval($_POST['contact_user_id']) : null;

if (!$contactUserId || $contactUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Kontakt-ID']);
    exit;
}

if ($userId === $contactUserId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kann sich nicht selbst favorisieren']);
    exit;
}

// Ensure all database tables exist (including chat_favorites)
if (function_exists('ensureDatabaseTables')) {
    ensureDatabaseTables();
} elseif (function_exists('ensureAllTables')) {
    ensureAllTables();
} elseif (function_exists('createTables')) {
    createTables();
}

$conn = getDBConnection();

// Sicherstelle explizit, dass chat_favorites Tabelle existiert
// Verwende datenbankunabhängige Methode
try {
    // Prüfe ob Tabelle existiert
    $tableExists = false;
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'mysql') {
        $stmt = $conn->query("SHOW TABLES LIKE 'chat_favorites'");
        $tableExists = $stmt->rowCount() > 0;
    } elseif ($driver === 'pgsql') {
        $stmt = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'chat_favorites')");
        $tableExists = $stmt->fetchColumn();
    } elseif ($driver === 'sqlite') {
        $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='chat_favorites'");
        $tableExists = $stmt->rowCount() > 0;
    }
    
    if (!$tableExists) {
        // Erstelle Tabelle datenbankunabhängig
        if ($driver === 'mysql') {
            $conn->exec("CREATE TABLE IF NOT EXISTS `chat_favorites` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `contact_user_id` INT NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_favorite` (`user_id`, `contact_user_id`),
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_contact_user_id` (`contact_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } elseif ($driver === 'pgsql') {
            $conn->exec("CREATE TABLE IF NOT EXISTS chat_favorites (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                contact_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (user_id, contact_user_id)
            )");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON chat_favorites(user_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_contact_user_id ON chat_favorites(contact_user_id)");
        } elseif ($driver === 'sqlite') {
            $conn->exec("CREATE TABLE IF NOT EXISTS chat_favorites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                contact_user_id INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, contact_user_id)
            )");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON chat_favorites(user_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_contact_user_id ON chat_favorites(contact_user_id)");
        }
    }
} catch(PDOException $e) {
    // Tabelle existiert bereits oder Fehler - loggen aber fortfahren
    error_log("Chat favorites table check: " . $e->getMessage());
}

try {
    // Prüfe ob bereits favorisiert
    $checkStmt = $conn->prepare("
        SELECT id FROM chat_favorites 
        WHERE user_id = ? AND contact_user_id = ?
    ");
    $checkStmt->execute([$userId, $contactUserId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Entferne aus Favoriten
        $deleteStmt = $conn->prepare("
            DELETE FROM chat_favorites 
            WHERE user_id = ? AND contact_user_id = ?
        ");
        $deleteStmt->execute([$userId, $contactUserId]);
        
        echo json_encode([
            'success' => true,
            'is_favorite' => false,
            'message' => 'Aus Favoriten entfernt'
        ]);
    } else {
        // Füge zu Favoriten hinzu
        $insertStmt = $conn->prepare("
            INSERT INTO chat_favorites (user_id, contact_user_id) 
            VALUES (?, ?)
        ");
        $insertStmt->execute([$userId, $contactUserId]);
        
        echo json_encode([
            'success' => true,
            'is_favorite' => true,
            'message' => 'Zu Favoriten hinzugefügt'
        ]);
    }
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

