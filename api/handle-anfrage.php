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

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$action = $_POST['action'] ?? '';
$anfrageId = intval($_POST['anfrage_id'] ?? 0);

if ($anfrageId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage-ID']);
    exit;
}

$conn = getDBConnection();

try {
    // Hole Anfrage mit angebot_id und requester_id
    $stmt = $conn->prepare("
        SELECT a.*, an.user_id as requester_id, a.user_id as helper_id
        FROM anfragen a
        INNER JOIN angebote an ON a.angebot_id = an.id
        WHERE a.id = ?
    ");
    $stmt->execute([$anfrageId]);
    $anfrage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$anfrage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Anfrage nicht gefunden']);
        exit;
    }
    
    $requesterId = intval($anfrage['requester_id']); // Person, die Hilfsanfrage erstellt hat (angebote.user_id)
    $helperId = intval($anfrage['helper_id']); // Hilfsgeber, der die Anfrage gesendet hat (anfragen.user_id)
    $currentUserId = intval($_SESSION['user_id']); // Aktueller Benutzer
    
    switch ($action) {
        case 'accept':
            // Hilfesuchender akzeptiert Anfrage des Hilfsgebers
            if ($currentUserId != $requesterId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            $conn->beginTransaction();
            
            try {
                // Update anfrage status
                $stmt = $conn->prepare("
                    UPDATE anfragen 
                    SET status = 'accepted' 
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$anfrageId]);
                
                // Create chat between requester and helper
                // Check if chat already exists
                $stmt = $conn->prepare("
                    SELECT c.id
                    FROM chats c
                    INNER JOIN chat_participants cp1 ON c.id = cp1.chat_id AND cp1.user_id = ?
                    INNER JOIN chat_participants cp2 ON c.id = cp2.chat_id AND cp2.user_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$requesterId, $helperId]);
                $existingChat = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $chatId = null;
                if ($existingChat) {
                    $chatId = intval($existingChat['id']);
                } else {
                    // Create new chat
                    $stmt = $conn->prepare("INSERT INTO chats () VALUES ()");
                    $stmt->execute();
                    $chatId = $conn->lastInsertId();
                    
                    // Add participants
                    $stmt = $conn->prepare("INSERT INTO chat_participants (chat_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$chatId, $requesterId]);
                    $stmt->execute([$chatId, $helperId]);
                    
                    // Create metadata
                    $stmt = $conn->prepare("
                        INSERT INTO chat_metadata (chat_id, unread_count_user_1, unread_count_user_2) 
                        VALUES (?, 0, 0)
                    ");
                    $stmt->execute([$chatId]);
                }
                
                // Link chat to anfrage if chat_requests table exists
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO chat_requests (anfrage_id, chat_id, requester_id, helper_id, status) 
                        VALUES (?, ?, ?, ?, 'accepted')
                        ON DUPLICATE KEY UPDATE 
                            chat_id = VALUES(chat_id),
                            status = 'accepted',
                            updated_at = NOW()
                    ");
                    $stmt->execute([$anfrageId, $chatId, $requesterId, $helperId]);
                } catch(PDOException $e) {
                    // Table might not exist yet, ignore
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Anfrage akzeptiert',
                    'chat_id' => $chatId
                ]);
            } catch(Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'reject':
            // Hilfesuchender lehnt Anfrage des Hilfsgebers ab
            if ($currentUserId != $requesterId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE anfragen 
                SET status = 'rejected' 
                WHERE id = ?
            ");
            $stmt->execute([$anfrageId]);
            
            echo json_encode(['success' => true, 'message' => 'Anfrage abgelehnt']);
            break;
            
        case 'confirm':
            // Hilfsgeber bestätigt, dass er die Aufgabe übernimmt
            if ($currentUserId != $helperId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE anfragen 
                SET status = 'confirmed', confirmed_at = NOW() 
                WHERE id = ? AND status = 'accepted'
            ");
            $stmt->execute([$anfrageId]);
            
            echo json_encode(['success' => true, 'message' => 'Anfrage bestätigt']);
            break;
            
        case 'cancel':
            // Hilfesuchender nimmt Anfrage zurück
            if ($currentUserId != $requesterId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE anfragen 
                SET status = 'cancelled' 
                WHERE id = ? AND status IN ('pending', 'accepted', 'confirmed')
            ");
            $stmt->execute([$anfrageId]);
            
            echo json_encode(['success' => true, 'message' => 'Anfrage zurückgenommen']);
            break;
            
        case 'completed_by_helper':
            // Hilfsgeber markiert Aufgabe als erledigt
            if ($currentUserId != $helperId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE anfragen 
                SET completed_by_helper = NOW() 
                WHERE id = ? AND status = 'confirmed'
            ");
            $stmt->execute([$anfrageId]);
            
            echo json_encode(['success' => true, 'message' => 'Als erledigt markiert']);
            break;
            
        case 'completed_by_requester':
            // Hilfesuchender bestätigt, dass Aufgabe erledigt wurde
            if ($currentUserId != $requesterId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            // Beginne Transaktion
            $conn->beginTransaction();
            
            try {
                // Markiere als erledigt
                $stmt = $conn->prepare("
                    UPDATE anfragen 
                    SET status = 'completed', completed_by_requester = NOW() 
                    WHERE id = ? AND completed_by_helper IS NOT NULL
                ");
                $stmt->execute([$anfrageId]);
                
                if ($stmt->rowCount() > 0) {
                    // Erhöhe erledigte Hilfen für den Hilfsgeber (helper_id)
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET completed_helps = completed_helps + 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$helperId]);
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Erledigt bestätigt']);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
            exit;
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}
?>

