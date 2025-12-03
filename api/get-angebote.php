<?php
header('Content-Type: application/json');

require_once '../config/config.php';
require_once '../sql/create-tables.php';

$conn = getDBConnection();
$currentUserId = isset($_SESSION['user_id']) && !isset($_SESSION['is_guest']) ? intval($_SESSION['user_id']) : null;

try {
    $conn->exec("SET SESSION group_concat_max_len = 10000");
    
    $sql = "
        SELECT 
            a.*,
            u.id as user_id,
            u.username as author,
            SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT ai.image_path ORDER BY ai.id SEPARATOR ','), ',', 5) as images,
            COUNT(DISTINCT anf_all.id) as anfragen_count";
    
    if ($currentUserId) {
        $sql .= ",
            MAX(CASE WHEN anf_user.user_id = ? THEN anf_user.id END) as user_anfrage_id,
            (a.user_id = ?) as is_owner";
    }
    
    $sql .= "
        FROM angebote a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN angebote_images ai ON a.id = ai.angebot_id
        LEFT JOIN anfragen anf_all ON a.id = anf_all.angebot_id";
    
    if ($currentUserId) {
        $sql .= "
        LEFT JOIN anfragen anf_user ON a.id = anf_user.angebot_id AND anf_user.user_id = ?";
    }
    
    $sql .= "
        GROUP BY a.id
        ORDER BY a.created_at DESC
        LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    
    if ($currentUserId) {
        $stmt->execute([$currentUserId, $currentUserId, $currentUserId]);
    } else {
        $stmt->execute();
    }
    
    $angebote = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($angebote as &$angebot) {
        $angebot['images'] = $angebot['images'] ? explode(',', $angebot['images']) : [];
        $angebot['anfragen_count'] = intval($angebot['anfragen_count']);
        if ($currentUserId) {
            $angebot['is_owner'] = isset($angebot['is_owner']) && intval($angebot['is_owner']) === 1;
            if ($angebot['user_anfrage_id']) {
                $angebot['has_user_anfrage'] = true;
                $angebot['user_anfrage_id'] = intval($angebot['user_anfrage_id']);
            } else {
                $angebot['has_user_anfrage'] = false;
                $angebot['user_anfrage_id'] = null;
            }
        } else {
            $angebot['is_owner'] = false;
            $angebot['has_user_anfrage'] = false;
            $angebot['user_anfrage_id'] = null;
        }
    }
    unset($angebot);
    
    echo json_encode(['success' => true, 'data' => $angebote], JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden: ' . $e->getMessage()]);
}
?>

