<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nur angemeldete Benutzer können Angebote erstellen']);
    exit;
}

$conn = getDBConnection();

$userId = $_SESSION['user_id'] ?? null;

if ($userId) {
    $userCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $userCheckStmt->execute([$userId]);
    if (!$userCheckStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Benutzer existiert nicht in der Datenbank']);
        exit;
    }
}

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? '';
$startDate = $_POST['startDate'] ?? '';
$endDate = $_POST['endDate'] ?? '';
$startTime = $_POST['startTime'] ?? '';
$endTime = $_POST['endTime'] ?? '';
$address = $_POST['address'] ?? '';
$lat = $_POST['lat'] ?? '';
$lng = $_POST['lng'] ?? '';
$requiredPersons = isset($_POST['required_persons']) ? intval($_POST['required_persons']) : 1;

if ($requiredPersons < 1 || $requiredPersons > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Die Anzahl der benötigten Personen muss zwischen 1 und 100 liegen']);
    exit;
}

if (empty($title) || empty($description) || empty($category) || empty($startDate) || empty($endDate) || empty($startTime) || empty($endTime) || empty($address) || empty($lat) || empty($lng)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Alle Felder müssen ausgefüllt sein']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Datumsformat']);
    exit;
}

$startDateObj = DateTime::createFromFormat('Y-m-d', $startDate);
$endDateObj = DateTime::createFromFormat('Y-m-d', $endDate);

if (!$startDateObj || !$endDateObj) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Datum']);
    exit;
}

if ($endDateObj < $startDateObj) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Enddatum darf nicht vor dem Startdatum liegen']);
    exit;
}

if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiges Zeitformat']);
    exit;
}

try {
    $conn->beginTransaction();
    
    $columnCheck = $conn->query("SHOW COLUMNS FROM `angebote` LIKE 'required_persons'");
    $hasRequiredPersons = $columnCheck->rowCount() > 0;
    
    if ($hasRequiredPersons) {
        $stmt = $conn->prepare("INSERT INTO angebote (user_id, title, description, category, start_date, end_date, start_time, end_time, address, lat, lng, required_persons) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $description, $category, $startDate, $endDate, $startTime, $endTime, $address, $lat, $lng, $requiredPersons]);
    } else {
        $stmt = $conn->prepare("INSERT INTO angebote (user_id, title, description, category, start_date, end_date, start_time, end_time, address, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $title, $description, $category, $startDate, $endDate, $startTime, $endTime, $address, $lat, $lng]);
    }
    
    $angebotId = $conn->lastInsertId();
    
    $uploadDir = '../uploads/angebote/' . $angebotId . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxFileSize = 5 * 1024 * 1024;
    
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $files = $_FILES['files'];
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount && $i < 5; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $files['tmp_name'][$i];
                $originalName = $files['name'][$i];
                $fileSize = $files['size'][$i];
                $fileType = $files['type'][$i];
                
                if ($fileSize > $maxFileSize) {
                    continue;
                }
                
                if (!in_array($fileType, $allowedTypes)) {
                    continue;
                }
                
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions)) {
                    continue;
                }
                
                $imageInfo = @getimagesize($tmpName);
                if ($imageInfo === false) {
                    continue;
                }
                
                $safeFileName = uniqid() . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $safeFileName;
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $relativePath = 'uploads/angebote/' . $angebotId . '/' . $safeFileName;
                    $imageStmt = $conn->prepare("INSERT INTO angebote_images (angebot_id, image_path) VALUES (?, ?)");
                    $imageStmt->execute([$angebotId, $relativePath]);
                }
            }
        }
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Angebot erfolgreich hinzugefügt', 'id' => $angebotId]);
    
} catch(PDOException $e) {
    $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $e->getMessage()]);
}
?>

