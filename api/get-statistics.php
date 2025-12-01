<?php
header('Content-Type: application/json');

require_once '../sql/db.php';
require_once '../sql/create-tables.php';

try {
    $conn = getDBConnection();
    
    $totalUsers = $conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    
    $totalOffers = $conn->query("SELECT COUNT(*) as count FROM angebote")->fetch()['count'];
    
    $totalMessages = $conn->query("SELECT COUNT(*) as count FROM messages")->fetch()['count'];
    
    $usersLastMonth = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['count'];
    
    $offersLastMonth = $conn->query("SELECT COUNT(*) as count FROM angebote WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch()['count'];
    
    $userGrowth = $totalUsers > 0 ? round(($usersLastMonth / max($totalUsers - $usersLastMonth, 1)) * 100, 1) : 0;
    
    $offerGrowth = $totalOffers > 0 ? round(($offersLastMonth / max($totalOffers - $offersLastMonth, 1)) * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_users' => intval($totalUsers),
            'total_offers' => intval($totalOffers),
            'total_messages' => intval($totalMessages),
            'user_growth' => floatval($userGrowth),
            'offer_growth' => floatval($offerGrowth),
            'users_last_month' => intval($usersLastMonth),
            'offers_last_month' => intval($offersLastMonth)
        ]
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Laden der Statistiken: ' . $e->getMessage()
    ]);
}
?>

