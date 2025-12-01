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

$emailOrUsername = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($emailOrUsername) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-Mail/Benutzername und Passwort sind erforderlich']);
    exit;
}

$conn = getDBConnection();

try {
    // Check if input is an email (contains @) or username
    if (strpos($emailOrUsername, '@') !== false) {
        // It's an email
        $stmt = $conn->prepare("SELECT id, username, email, password, avatar FROM users WHERE email = ?");
    } else {
        // It's a username
        $stmt = $conn->prepare("SELECT id, username, email, password, avatar FROM users WHERE username = ?");
    }
    
    $stmt->execute([$emailOrUsername]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail/Benutzername oder Passwort']);
        exit;
    }
    
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail/Benutzername oder Passwort']);
        exit;
    }
    
    // Sicherstellen dass is_guest NICHT gesetzt ist
    unset($_SESSION['is_guest']);
    
    // Session-Variablen setzen
    $_SESSION['user_id'] = intval($user['id']); // Explizit als Integer
    $_SESSION['user_name'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? 'assets/images/profile-placeholder.svg';
    
    // WICHTIG: Session-ID regenerieren für Sicherheit
    // Dies verhindert Session-Fixation-Angriffe
    // Muss NACH dem Setzen der Variablen aufgerufen werden
    // Die Session-Variablen bleiben erhalten
    session_regenerate_id(true);
    
    // Sicherstellen dass Session-Variablen nach Regenerierung noch vorhanden sind
    // session_regenerate_id() sollte die Variablen behalten, aber zur Sicherheit
    $_SESSION['user_id'] = intval($user['id']);
    $_SESSION['user_name'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? 'assets/images/profile-placeholder.svg';
    unset($_SESSION['is_guest']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Erfolgreich angemeldet',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Anmelden: ' . $e->getMessage()]);
}
?>

