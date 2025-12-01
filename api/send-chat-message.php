<?php
// Start output buffering immediately to catch any PHP warnings/errors
ob_start();

// Disable error display to prevent HTML output
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
// Keep error reporting on to catch warnings, but handle them gracefully
error_reporting(E_ALL);

// Increase PHP limits for large video uploads (must be set before any output)
// Note: post_max_size and upload_max_filesize can only be changed in php.ini or .htaccess
// These ini_set() calls may not work on all servers
@ini_set('upload_max_filesize', '100M');
@ini_set('post_max_size', '100M');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');
@ini_set('memory_limit', '256M');

// Set JSON header immediately to ensure JSON response even on errors
header('Content-Type: application/json; charset=utf-8');

// Custom error handler to return JSON instead of HTML
// This must be set BEFORE any code that might trigger warnings
$errorHandler = function($errno, $errstr, $errfile, $errline) {
    // Handle warnings about post_max_size being exceeded
    if (strpos($errstr, 'POST Content-Length') !== false || 
        strpos($errstr, 'exceeds the limit') !== false ||
        strpos($errstr, 'post_max_size') !== false ||
        strpos($errstr, 'Request Startup') !== false) {
        // Clear any output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        http_response_code(413);
        header('Content-Type: application/json; charset=utf-8');
        
        $contentLength = intval($_SERVER['CONTENT_LENGTH'] ?? 0);
        $contentLengthMB = $contentLength > 0 ? round($contentLength / 1024 / 1024, 2) : 0;
        $postMaxSize = ini_get('post_max_size');
        $uploadMaxSize = ini_get('upload_max_filesize');
        $phpIniPath = php_ini_loaded_file();
        $phpIniHint = $phpIniPath ? " ({$phpIniPath})" : "";
        
        echo json_encode([
            'success' => false, 
            'message' => "Video zu groß ({$contentLengthMB}MB). PHP-Limits: post_max_size={$postMaxSize}, upload_max_filesize={$uploadMaxSize}. Bitte .htaccess prüfen oder php.ini{$phpIniHint} anpassen und PHP-Server NEU STARTEN."
        ]);
        exit;
    }
    // Suppress other warnings to prevent HTML output
    // Return true to suppress the default PHP error handler
    return true;
};

set_error_handler($errorHandler, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);

// Lade Session-Konfiguration
try {
require_once '../config/config.php';
require_once '../sql/create-tables.php';
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Konfigurationsfehler: ' . $e->getMessage()]);
    exit;
}

// Clear any output that might have been generated
ob_clean();

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = intval($_SESSION['user_id']);

// Helper function to convert ini size to bytes
if (!function_exists('return_bytes')) {
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}

// Check if POST data was received (might be empty if post_max_size exceeded)
// This happens when the request body exceeds post_max_size - PHP empties $_POST and $_FILES
if (empty($_POST) && empty($_FILES) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postMaxSize = ini_get('post_max_size');
    $uploadMaxSize = ini_get('upload_max_filesize');
    $postMaxSizeBytes = return_bytes($postMaxSize);
    $contentLength = intval($_SERVER['CONTENT_LENGTH'] ?? 0);
    
    // Convert bytes to human readable
    $contentLengthMB = round($contentLength / 1024 / 1024, 2);
    $postMaxSizeMB = round($postMaxSizeBytes / 1024 / 1024, 2);
    
    if ($contentLength > $postMaxSizeBytes) {
        $phpIniPath = php_ini_loaded_file();
        $phpIniHint = $phpIniPath ? " (php.ini: {$phpIniPath})" : " (php.ini anpassen)";
        
        http_response_code(413);
        echo json_encode([
            'success' => false, 
            'message' => "Video zu groß ({$contentLengthMB}MB). PHP-Limit: {$postMaxSize} (post_max_size). Bitte .htaccess prüfen oder php.ini{$phpIniHint} anpassen: upload_max_filesize=100M, post_max_size=100M. Dann PHP-Server neu starten."
        ]);
        exit;
    }
    
    // If content length is reasonable but POST is empty, there might be another issue
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Keine Daten empfangen. Möglicherweise PHP-Limit überschritten oder Server-Konfigurationsproblem.'
    ]);
    exit;
}

$chatId = intval($_POST['chat_id'] ?? 0);
$textMessage = trim($_POST['message'] ?? ''); // Text message from user
$encryptedParam = isset($_POST['encrypted']) && $_POST['encrypted'] == '1';
$uploadedFiles = [];
$dbMessage = $textMessage; // Will be updated with file data if needed
$finalEncrypted = $encryptedParam;

// Check for already uploaded videos (from chunked upload)
$uploadedVideos = [];
if (!empty($_POST['uploaded_videos'])) {
    try {
        $uploadedVideos = json_decode($_POST['uploaded_videos'], true);
        if (is_array($uploadedVideos)) {
            // Add already uploaded videos to uploadedFiles
            foreach ($uploadedVideos as $video) {
                if (isset($video['file_path']) && isset($video['file_type'])) {
                    $uploadedFiles[] = [
                        'path' => $video['file_path'],
                        'type' => $video['file_type'],
                        'name' => $video['file_name'] ?? ''
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error parsing uploaded_videos: " . $e->getMessage());
    }
}

if ($chatId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Chat-ID']);
    exit;
}

// Check if we have files
$hasFiles = false;
if (isset($_FILES['files']) && is_array($_FILES['files']['error'])) {
    // Multiple files - check if at least one file uploaded successfully
    foreach ($_FILES['files']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $hasFiles = true;
            break;
        }
    }
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    // Single file (backward compatibility)
    $hasFiles = true;
}

// Handle file uploads
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];
$maxSize = 100 * 1024 * 1024; // 100MB - increased for large videos
$MAX_FILES_PER_MESSAGE = 10; // Maximum 10 files per message

// Create upload directory
$uploadDir = '../uploads/chat/' . $chatId . '/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle multiple files
$imageMaxSize = 10 * 1024 * 1024; // 10MB for images
$videoMaxSize = 100 * 1024 * 1024; // 100MB for videos - increased for large video files

// Count files being uploaded in this request
$fileCount = 0;
if (isset($_FILES['files']) && is_array($_FILES['files']['error'])) {
    $fileCount = count(array_filter($_FILES['files']['error'], function($error) {
        return $error === UPLOAD_ERR_OK;
    }));
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileCount = 1;
}

// Count already uploaded files from chunked upload (before processing new files)
$chunkedUploadCount = count($uploadedFiles);

// Also check if incoming message contains JSON with base64 images (for backward compatibility)
$incomingImageCount = 0;
$incomingMessageData = null;
if (!empty($_POST['message']) && ($_POST['message'][0] === '[' || $_POST['message'][0] === '{')) {
    try {
        $incomingMessageData = json_decode($_POST['message'], true);
        if (isset($incomingMessageData['images']) && is_array($incomingMessageData['images'])) {
            $incomingImageCount = count($incomingMessageData['images']);
        } elseif (is_array($incomingMessageData)) {
            $incomingImageCount = count($incomingMessageData);
        }
    } catch (Exception $e) {
        // Not JSON, ignore
    }
}

$totalFileCount = $fileCount + $incomingImageCount + $chunkedUploadCount;
if ($totalFileCount > $MAX_FILES_PER_MESSAGE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Maximal {$MAX_FILES_PER_MESSAGE} Dateien pro Nachricht erlaubt."]);
    exit;
}

// Also validate incoming JSON images count
if ($incomingImageCount > $MAX_FILES_PER_MESSAGE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => "Maximal {$MAX_FILES_PER_MESSAGE} Dateien pro Nachricht erlaubt."]);
    exit;
}

if (isset($_FILES['files']) && is_array($_FILES['files']['error'])) {
    foreach ($_FILES['files']['error'] as $key => $error) {
        // Skip files with upload errors (except OK)
        if ($error !== UPLOAD_ERR_OK) {
            // Log error for debugging but continue with other files
            if ($error !== UPLOAD_ERR_NO_FILE) {
                error_log("File upload error for key $key: $error");
            }
            continue;
        }
        
        $file = [
            'name' => $_FILES['files']['name'][$key],
            'type' => $_FILES['files']['type'][$key],
            'tmp_name' => $_FILES['files']['tmp_name'][$key],
            'size' => $_FILES['files']['size'][$key]
        ];
        
        // Check file type - allow any video/* type for flexibility
        $isAllowedType = in_array($file['type'], $allowedTypes) || strpos($file['type'], 'video/') === 0;
        if (!$isAllowedType) {
            error_log("Invalid file type: " . $file['type']);
            continue; // Skip invalid files
        }
        
        $isImage = strpos($file['type'], 'image/') === 0;
        $maxFileSize = $isImage ? $imageMaxSize : $videoMaxSize;
    
    // Check file size
        if ($file['size'] > $maxFileSize) {
            error_log("File too large: " . $file['name'] . " (" . $file['size'] . " bytes, max: $maxFileSize)");
            continue; // Skip oversized files
        }
        
        // Validate file exists and is readable
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("Invalid uploaded file: " . $file['name']);
            continue;
        }
        
        // Check if we've reached the maximum file limit
        // Note: $uploadedFiles may already contain chunked uploads, so we need to check total
        $currentTotal = count($uploadedFiles) + $incomingImageCount + (isset($_FILES['files']) ? count(array_filter($_FILES['files']['error'], function($e) { return $e === UPLOAD_ERR_OK; })) : 0);
        if ($currentTotal >= $MAX_FILES_PER_MESSAGE) {
            break; // Stop processing more files
        }
        
        // Store all files (images and videos) as file paths
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('chat_', true) . '_' . time() . '_' . $key . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $relativePath = 'uploads/chat/' . $chatId . '/' . $fileName;
            $uploadedFiles[] = [
                'path' => $relativePath,
                'type' => $isImage ? 'image' : 'video',
                'name' => $file['name']
            ];
        } else {
            error_log("Failed to move uploaded file: " . $file['name'] . " to " . $filePath . " (upload_dir: $uploadDir, exists: " . (file_exists($uploadDir) ? 'yes' : 'no') . ")");
            // Don't exit here, continue with other files
            // Only exit if no files were successfully uploaded
        }
    }
} elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    // Single file (backward compatibility)
    // Check if we've reached the maximum file limit
    $currentTotal = count($uploadedFiles) + $incomingImageCount + 1;
    if ($currentTotal > $MAX_FILES_PER_MESSAGE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Maximal {$MAX_FILES_PER_MESSAGE} Dateien pro Nachricht erlaubt."]);
        exit;
    }
    
    $file = $_FILES['file'];
    
    // Check file type - allow any video/* type for flexibility
    $isAllowedType = in_array($file['type'], $allowedTypes) || strpos($file['type'], 'video/') === 0;
    if ($isAllowedType) {
        $isImage = strpos($file['type'], 'image/') === 0;
        $maxFileSize = $isImage ? $imageMaxSize : $videoMaxSize;
        
        // Check file size
        if ($file['size'] <= $maxFileSize) {
            // Store all files (images and videos) as file paths
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('chat_', true) . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $relativePath = 'uploads/chat/' . $chatId . '/' . $fileName;
                $uploadedFiles[] = [
                    'path' => $relativePath,
                    'type' => $isImage ? 'image' : 'video',
                    'name' => $file['name']
                ];
            }
        }
    }
}

// Prepare message and file_path/file_type for database
$filePath = null;
$fileType = null;

// Check if incoming message is already JSON (for backward compatibility with old base64 images)
// Use the $incomingMessageData we already parsed earlier for validation
$incomingMessageIsJson = false;
if ($incomingMessageData && (is_array($incomingMessageData) || is_object($incomingMessageData))) {
    $incomingMessageIsJson = true;
}

// Collect all files from both sources (images from message, videos from uploaded_videos)
$fileData = [];
$hasTextInMessage = false;

// First, handle incoming base64 images from frontend (backward compatibility) and convert them to files
if ($incomingMessageIsJson && $incomingMessageData) {
    // Don't encrypt JSON data
    $finalEncrypted = 0;
    
    $incomingFiles = [];
    if (isset($incomingMessageData['text']) && isset($incomingMessageData['images'])) {
        // Object with text and images
        $incomingFiles = $incomingMessageData['images'];
        if (!empty($incomingMessageData['text'])) {
            $hasTextInMessage = true;
            $textMessage = $incomingMessageData['text']; // Extract text from JSON
        }
    } elseif (is_array($incomingMessageData)) {
        // Array of images
        $incomingFiles = $incomingMessageData;
    }
    
    // Convert base64 images to files
    foreach ($incomingFiles as $index => $file) {
        if (isset($file['data']) && strpos($file['data'], 'data:') === 0) {
            // This is a base64 image - convert to file
            $dataUri = $file['data'];
            $mimeType = $file['mime'] ?? 'image/jpeg';
            
            // Extract base64 data
            if (preg_match('/data:([^;]+);base64,(.+)/', $dataUri, $matches)) {
                $base64Data = $matches[2];
                $imageData = base64_decode($base64Data);
                
                if ($imageData !== false) {
                    // Determine file extension from MIME type
                    $extension = 'jpg';
                    if (strpos($mimeType, 'png') !== false) $extension = 'png';
                    elseif (strpos($mimeType, 'gif') !== false) $extension = 'gif';
                    elseif (strpos($mimeType, 'webp') !== false) $extension = 'webp';
                    
                    $fileName = uniqid('chat_', true) . '_' . time() . '_' . $index . '.' . $extension;
                    $filePath = $uploadDir . $fileName;
                    
                    // Save the image file
                    if (file_put_contents($filePath, $imageData) !== false) {
                        $relativePath = 'uploads/chat/' . $chatId . '/' . $fileName;
                        $fileData[] = [
                            'path' => $relativePath,
                            'type' => 'image',
                            'name' => $file['name'] ?? 'image.' . $extension
                        ];
                    }
                }
            }
        } elseif (isset($file['path'])) {
            // Already a file path, use as is
            $fileData[] = $file;
        }
    }
}

// Then, add files from uploaded_videos (chunked uploads and regular uploads)
foreach ($uploadedFiles as $file) {
    $fileData[] = $file;
}

// Now determine how to store all files - ALL files (images and videos) are stored as file paths
if (count($fileData) > 0) {
    // Filter out any files that don't have a path (shouldn't happen now, but for safety)
    $validFileData = array_filter($fileData, function($file) {
        return isset($file['path']);
    });
    
    if (count($validFileData) === 0) {
        // No valid files, treat as text only
        $dbMessage = $textMessage;
        $fileType = null;
        $filePath = null;
    } elseif (count($validFileData) === 1) {
        // Single file - store path directly
        $filePath = $validFileData[0]['path'];
        $fileType = $validFileData[0]['type'] ?? 'image';
        $dbMessage = !empty($textMessage) ? $textMessage : '';
    } else {
        // Multiple files - store as JSON in file_path
        $filePath = json_encode($validFileData);
        $fileType = 'multiple';
        $dbMessage = !empty($textMessage) ? $textMessage : '';
    }
} elseif (!empty($textMessage)) {
    // Only text message, no files
    $dbMessage = $textMessage;
    $fileType = null;
    $filePath = null;
}

// Final check: need either message or successfully uploaded files
$hasUploadedFiles = count($fileData) > 0;

if (empty($textMessage) && !$hasUploadedFiles) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Keine Nachricht oder Datei']);
    exit;
}

$conn = getDBConnection();

try {
    $conn->beginTransaction();
    
    // Verify user is participant in this chat
    $stmt = $conn->prepare("
        SELECT chat_id FROM chat_participants 
        WHERE chat_id = ? AND user_id = ?
    ");
    $stmt->execute([$chatId, $userId]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Nicht berechtigt']);
        exit;
    }
    
    // Get receiver ID (the other participant)
    $stmt = $conn->prepare("
        SELECT user_id FROM chat_participants 
        WHERE chat_id = ? AND user_id != ?
    ");
    $stmt->execute([$chatId, $userId]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receiver) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Chat-Teilnehmer nicht gefunden']);
        exit;
    }
    
    $receiverId = intval($receiver['user_id']);
    
    // DISABLED: File merging - each file upload creates a separate message
    // This allows users to click on files and navigate through them in a gallery view
    $shouldMerge = false;
    $lastMessageId = null;
    $lastMessageFiles = [];
    
    $messageId = null;
    
    $isMerged = false;
    
    // Merge disabled - always create new messages
    if (false && $shouldMerge && $lastMessageId) {
        // Merge files with last message
        // Use fileData which already has all files (converted from base64 if needed)
        $newFiles = $fileData;
        
        // Merge with existing files
        $mergedFiles = array_merge($lastMessageFiles, $newFiles);
        
        // Check if merged files exceed maximum
        if (count($mergedFiles) > $MAX_FILES_PER_MESSAGE) {
            // Don't merge, create new message instead
            $shouldMerge = false;
            $lastMessageId = null;
            $isMerged = false;
            // Continue to insert new message with only new files
            $mergedFiles = [];
        } else {
            // Determine how to store merged files - ALL as file paths (with backward compat for old base64)
            $mergedMessage = !empty($textMessage) ? $textMessage : '';
            $mergedFilePath = null;
            $mergedFileType = 'multiple';
            
            // Separate files with paths (new format) and files with data (old base64 format)
            $filesWithPaths = array_filter($mergedFiles, function($file) {
                return isset($file['path']);
            });
            $filesWithData = array_filter($mergedFiles, function($file) {
                return isset($file['data']) && !isset($file['path']);
            });
            
            $allFilesToStore = array_merge(array_values($filesWithPaths), array_values($filesWithData));
            
            if (count($allFilesToStore) === 0) {
                $mergedFilePath = null;
                $mergedFileType = null;
            } elseif (count($allFilesToStore) === 1) {
                // Single file
                if (isset($allFilesToStore[0]['path'])) {
                    // New format - file path
                    $mergedFilePath = $allFilesToStore[0]['path'];
                    $mergedFileType = $allFilesToStore[0]['type'] ?? 'image';
                } else {
                    // Old base64 format - store in message for backward compat
                    $mergedMessage = json_encode($allFilesToStore);
                    $mergedFileType = 'image';
                    $mergedFilePath = null;
                }
            } else {
                // Multiple files
                if (count($filesWithPaths) > 0) {
                    // Has new format files - store all in file_path
                    $mergedFilePath = json_encode($allFilesToStore);
                    $mergedFileType = 'multiple';
                } else {
                    // All old base64 - store in message for backward compat
                    $mergedMessage = json_encode($allFilesToStore);
                    $mergedFileType = 'multiple';
                    $mergedFilePath = null;
                }
            }
            
            // Update the last message with merged files
            $stmt = $conn->prepare("
                UPDATE messages 
                SET message = ?, file_path = ?, file_type = ?, encrypted = 0
                WHERE id = ?
            ");
            $stmt->execute([$mergedMessage, $mergedFilePath, $mergedFileType, $lastMessageId]);
            $messageId = $lastMessageId;
            $isMerged = true;
            $finalEncrypted = 0; // Don't encrypt JSON data
        }
    }
    
    if (!$isMerged) {
        // Insert new message (either no merge needed or merge would exceed limit)
        $finalEncrypted = $encryptedParam;
        // Don't encrypt if message is JSON
        if ($incomingMessageIsJson) {
            $finalEncrypted = 0;
        }
        
    $stmt = $conn->prepare("
        INSERT INTO messages (chat_id, sender_id, receiver_id, message, encrypted, file_path, file_type, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
        $stmt->execute([$chatId, $userId, $receiverId, $dbMessage, $finalEncrypted ? 1 : 0, $filePath, $fileType]);
    $messageId = $conn->lastInsertId();
    }
    
    
    // Get participant order to update correct unread count
    $stmt = $conn->prepare("
        SELECT user_id FROM chat_participants 
        WHERE chat_id = ? 
        ORDER BY user_id ASC
    ");
    $stmt->execute([$chatId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $isFirstUser = count($participants) > 0 && intval($participants[0]['user_id']) === $userId;
    $unreadField = $isFirstUser ? 'unread_count_user_2' : 'unread_count_user_1';
    
    // Update chat metadata
    // Only increment unread count if it's a new message, not a merged one
    if ($isMerged) {
        // Just update last_message_at and last_message_id, but don't increment unread count
        $stmt = $conn->prepare("
            UPDATE chat_metadata 
            SET last_message_id = ?, 
                last_message_at = NOW()
            WHERE chat_id = ?
        ");
        $stmt->execute([$messageId, $chatId]);
    } else {
        // New message - increment unread count
    $stmt = $conn->prepare("
        UPDATE chat_metadata 
        SET last_message_id = ?, 
            last_message_at = NOW(),
            {$unreadField} = {$unreadField} + 1
        WHERE chat_id = ?
    ");
    $stmt->execute([$messageId, $chatId]);
    }
    
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
    
    // Update chat updated_at
    $stmt = $conn->prepare("UPDATE chats SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$chatId]);
    
    // Get the sent message with user details
    $stmt = $conn->prepare("
        SELECT 
            m.id,
            m.sender_id,
            m.message,
            m.encrypted,
            m.file_path,
            m.file_type,
            m.created_at,
            u.username,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as name,
            u.avatar
        FROM messages m
        INNER JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $sentMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $conn->commit();
    
    // Format the message
    $formattedMessage = null;
    if ($sentMessage) {
        // Ensure created_at is properly formatted (real timestamp from database)
        $createdAt = $sentMessage['created_at'];
        if ($createdAt) {
            // Convert to ISO 8601 format if needed
            $timestamp = strtotime($createdAt);
            if ($timestamp) {
                $createdAt = date('Y-m-d H:i:s', $timestamp);
            }
        }
        
        $formattedMessage = [
            'id' => intval($sentMessage['id']),
            'sender_id' => intval($sentMessage['sender_id']),
            'message' => $sentMessage['message'],
            'encrypted' => (bool)($sentMessage['encrypted'] ?? false),
            'file_path' => $sentMessage['file_path'] ?? null,
            'file_type' => $sentMessage['file_type'] ?? null,
            'created_at' => $createdAt, // Real timestamp from database
            'username' => $sentMessage['username'] ?? '',
            'name' => trim($sentMessage['name']) ?: $sentMessage['username'] ?? 'Unbekannt',
            'avatar' => $sentMessage['avatar'] ?? null,
            'is_sent' => intval($sentMessage['sender_id']) === $userId,
            'chat_id' => $chatId
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message_id' => intval($messageId),
        'message' => 'Nachricht gesendet',
        'sent_message' => $formattedMessage
    ]);
    
} catch(PDOException $e) {
    ob_clean();
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
} catch(Exception $e) {
    ob_clean();
    if (isset($conn) && $conn->inTransaction()) {
    $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

// Restore error handler
restore_error_handler();

// End output buffering - only if we have content
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>

