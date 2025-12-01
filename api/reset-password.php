<?php
header('Content-Type: application/json');

require_once '../sql/db.php';
require_once '../sql/create-tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (empty($email) || empty($password) || empty($passwordConfirm)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Alle Felder müssen ausgefüllt sein']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige E-Mail-Adresse']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwort muss mindestens 6 Zeichen lang sein']);
    exit;
}

if ($password !== $passwordConfirm) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwörter stimmen nicht überein']);
    exit;
}

$conn = getDBConnection();

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kein Konto mit dieser E-Mail-Adresse gefunden']);
        exit;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $updateStmt->execute([$hashedPassword, $email]);
    
    echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert']);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Ändern des Passworts: ' . $e->getMessage()]);
}
?>

