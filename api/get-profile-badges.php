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

$userId = intval($_SESSION['user_id']);
$conn = getDBConnection();

try {
    $badges = [];
    
    $laufendeAnfragen = $conn->prepare("SELECT COUNT(*) FROM anfragen WHERE user_id = ?");
    $laufendeAnfragen->execute([$userId]);
    $laufendeAnfragenCount = $laufendeAnfragen->fetchColumn();
    
    if ($laufendeAnfragenCount >= 1) {
        $badges[] = ['name' => 'Erste Anfrage', 'icon' => '🎯'];
    }
    
    if ($laufendeAnfragenCount >= 5) {
        $badges[] = ['name' => 'Aktiver Helfer', 'icon' => '⭐'];
    }
    
    if ($laufendeAnfragenCount >= 10) {
        $badges[] = ['name' => 'Super Helfer', 'icon' => '🏆'];
    }
    
    $angeboteCount = $conn->prepare("SELECT COUNT(*) FROM angebote WHERE user_id = ?");
    $angeboteCount->execute([$userId]);
    $angeboteCountValue = $angeboteCount->fetchColumn();
    
    if ($angeboteCountValue >= 1) {
        $badges[] = ['name' => 'Erstes Angebot', 'icon' => '📝'];
    }
    
    if ($angeboteCountValue >= 5) {
        $badges[] = ['name' => 'Angebots-Meister', 'icon' => '💼'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $badges
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden der Badges: ' . $e->getMessage()]);
}
?>






