<?php
header('Content-Type: application/json');

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
    if (strpos($emailOrUsername, '@') !== false) {
        $stmt = $conn->prepare("SELECT id, username, email, password, avatar FROM users WHERE email = ?");
    } else {
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
    
    unset($_SESSION['is_guest']);
    
    $_SESSION['user_id'] = intval($user['id']);
    $_SESSION['user_name'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? 'assets/images/profile-placeholder.svg';
    
    session_regenerate_id(true);
    
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

