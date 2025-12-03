<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$street = trim($_POST['street'] ?? '');
$houseNumber = trim($_POST['house_number'] ?? '');
$postcode = trim($_POST['postcode'] ?? '');
$city = trim($_POST['city'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bitte füllen Sie alle Pflichtfelder aus (Benutzername, E-Mail, Passwort)']);
    exit;
}

if (strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Benutzername muss mindestens 3 Zeichen lang sein']);
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
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, first_name, last_name, street, house_number, postcode, city) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hashedPassword, $firstName, $lastName, $street, $houseNumber, $postcode, $city]);
    
    $userId = $conn->lastInsertId();
    
    unset($_SESSION['is_guest']);
    
    $_SESSION['user_id'] = intval($userId);
    $_SESSION['user_name'] = $username;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_avatar'] = 'assets/images/profile-placeholder.svg';
    
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = intval($userId);
    $_SESSION['user_name'] = $username;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_avatar'] = 'assets/images/profile-placeholder.svg';
    unset($_SESSION['is_guest']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Registrierung erfolgreich',
        'user' => [
            'id' => $userId,
            'username' => $username,
            'email' => $email
        ]
    ]);
    
} catch(PDOException $e) {
    $errorCode = $e->getCode();
    
    if ($errorCode == 23000) {
        if (strpos($e->getMessage(), 'username') !== false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Benutzername bereits vergeben']);
        } elseif (strpos($e->getMessage(), 'email') !== false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'E-Mail bereits registriert']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Benutzer existiert bereits']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler bei der Registrierung: ' . $e->getMessage()]);
    }
}
?>

