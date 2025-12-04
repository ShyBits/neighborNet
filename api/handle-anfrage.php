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
                // Hole required_persons vom Angebot
                $stmt = $conn->prepare("
                    SELECT COALESCE(an.required_persons, 1) as required_persons
                    FROM anfragen a
                    INNER JOIN angebote an ON a.angebot_id = an.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$anfrageId]);
                $angebotInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$angebotInfo) {
                    $conn->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Angebot nicht gefunden']);
                    exit;
                }
                
                $requiredPersons = intval($angebotInfo['required_persons'] ?? 1);
                
                // Prüfe wie viele Anfragen bereits angenommen wurden (accepted oder confirmed)
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as accepted_count
                    FROM anfragen
                    WHERE angebot_id = (
                        SELECT angebot_id FROM anfragen WHERE id = ?
                    )
                    AND status IN ('accepted', 'confirmed')
                    AND id != ?
                ");
                $stmt->execute([$anfrageId, $anfrageId]);
                $acceptedCount = intval($stmt->fetch(PDO::FETCH_ASSOC)['accepted_count'] ?? 0);
                
                if ($acceptedCount >= $requiredPersons) {
                    $conn->rollBack();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false, 
                        'message' => "Die maximale Anzahl von {$requiredPersons} Helfer" . ($requiredPersons > 1 ? "n" : "") . " wurde bereits erreicht."
                    ]);
                    exit;
                }
                
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
                
                // Get angebot title for the confirmation message
                $stmt = $conn->prepare("SELECT title FROM angebote WHERE id = ?");
                $stmt->execute([$anfrage['angebot_id']]);
                $angebot = $stmt->fetch(PDO::FETCH_ASSOC);
                $angebotTitle = $angebot ? $angebot['title'] : 'Ihre Anfrage';
                
                // Send confirmation message to helper
                $confirmationMessageData = json_encode([
                    'type' => 'anfrage_accepted',
                    'anfrage_id' => $anfrageId,
                    'angebot_id' => $anfrage['angebot_id'],
                    'angebot_title' => $angebotTitle,
                    'helper_id' => $helperId,
                    'requester_id' => $requesterId
                ]);
                
                $confirmationMessageText = "Ihre Hilfe wurde für die Anfrage \"" . htmlspecialchars($angebotTitle, ENT_QUOTES, 'UTF-8') . "\" angenommen. Bitte bestätigen Sie, dass Sie die Aufgabe übernehmen möchten.";
                
                $stmt = $conn->prepare("
                    INSERT INTO messages (chat_id, sender_id, receiver_id, message, encrypted, file_path, file_type) 
                    VALUES (?, ?, ?, ?, 0, ?, 'anfrage_accepted')
                ");
                $stmt->execute([$chatId, $requesterId, $helperId, $confirmationMessageText, $confirmationMessageData]);
                
                // Update chat metadata
                $messageId = $conn->lastInsertId();
                $stmt = $conn->prepare("
                    SELECT user_id FROM chat_participants 
                    WHERE chat_id = ? 
                    ORDER BY user_id ASC
                ");
                $stmt->execute([$chatId]);
                $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $isFirstUser = count($participants) > 0 && intval($participants[0]) === $helperId;
                $unreadField = $isFirstUser ? 'unread_count_user_2' : 'unread_count_user_1';
                
                $stmt = $conn->prepare("
                    UPDATE chat_metadata 
                    SET last_message_id = ?, 
                        last_message_at = NOW(),
                        {$unreadField} = {$unreadField} + 1
                    WHERE chat_id = ?
                ");
                $stmt->execute([$messageId, $chatId]);
                
                // If metadata doesn't exist, create it
                if ($stmt->rowCount() === 0) {
                    $unread1 = $isFirstUser ? 0 : 1;
                    $unread2 = $isFirstUser ? 1 : 0;
                    
                    $stmt = $conn->prepare("
                        INSERT INTO chat_metadata (chat_id, last_message_id, last_message_at, unread_count_user_1, unread_count_user_2) 
                        VALUES (?, ?, NOW(), ?, ?)
                    ");
                    $stmt->execute([$chatId, $messageId, $unread1, $unread2]);
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
            
            $conn->beginTransaction();
            
            try {
                // Get angebot title and chat_id
                $stmt = $conn->prepare("
                    SELECT a.angebot_id, an.title, cr.chat_id
                    FROM anfragen a
                    INNER JOIN angebote an ON a.angebot_id = an.id
                    LEFT JOIN chat_requests cr ON cr.anfrage_id = a.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$anfrageId]);
                $anfrageInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$anfrageInfo) {
                    $conn->rollBack();
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Anfrage nicht gefunden']);
                    exit;
                }
                
                // Update anfrage status
                $stmt = $conn->prepare("
                    UPDATE anfragen 
                    SET status = 'confirmed', confirmed_at = NOW() 
                    WHERE id = ? AND status = 'accepted'
                ");
                $stmt->execute([$anfrageId]);
                
                if ($stmt->rowCount() === 0) {
                    $conn->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Anfrage kann nicht bestätigt werden']);
                    exit;
                }
                
                $conn->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Anfrage bestätigt',
                    'anfrage_id' => $anfrageId,
                    'angebot_title' => $anfrageInfo['title'],
                    'chat_id' => $anfrageInfo['chat_id']
                ]);
            } catch(Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'cancel':
            // Hilfesuchender nimmt Anfrage zurück oder Hilfsgeber bricht ab
            // Prüfe ob es der Hilfesuchende oder der Hilfsgeber ist
            $isRequester = ($currentUserId == $requesterId);
            $isHelper = ($currentUserId == $helperId);
            
            if (!$isRequester && !$isHelper) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
                exit;
            }
            
            // Wenn es der Hilfsgeber ist und Status confirmed ist, dann ist es "abbrechen"
            // Wenn es der Hilfesuchende ist, dann ist es "zurücknehmen"
            $stmt = $conn->prepare("SELECT status FROM anfragen WHERE id = ?");
            $stmt->execute([$anfrageId]);
            $currentStatus = $stmt->fetchColumn();
            
            if ($isHelper && $currentStatus === 'confirmed') {
                // Hilfsgeber bricht ab - setze Status zurück auf accepted
                $stmt = $conn->prepare("
                    UPDATE anfragen 
                    SET status = 'accepted', confirmed_at = NULL
                    WHERE id = ? AND status = 'confirmed'
                ");
                $stmt->execute([$anfrageId]);
                echo json_encode(['success' => true, 'message' => 'Anfrage abgebrochen']);
            } else if ($isRequester) {
                // Hilfesuchender nimmt Anfrage zurück
                $stmt = $conn->prepare("
                    UPDATE anfragen 
                    SET status = 'cancelled' 
                    WHERE id = ? AND status IN ('pending', 'accepted', 'confirmed')
                ");
                $stmt->execute([$anfrageId]);
                echo json_encode(['success' => true, 'message' => 'Anfrage zurückgenommen']);
            } else {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
            }
            break;
            
        case 'erledigt':
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

