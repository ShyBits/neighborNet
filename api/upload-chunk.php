<?php
// Start output buffering immediately
ob_start();

// Disable error display
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Try to increase PHP limits (may not work for upload_max_filesize and post_max_size)
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '10M');
@ini_set('max_execution_time', '300');
@ini_set('max_input_time', '300');
@ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');

// Lade Session-Konfiguration
require_once '../config/config.php';
require_once '../sql/create-tables.php';

if (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

$userId = intval($_SESSION['user_id']);
$chatId = intval($_POST['chat_id'] ?? 0);
$chunkIndex = intval($_POST['chunk_index'] ?? 0);
$totalChunks = intval($_POST['total_chunks'] ?? 1);
$fileName = $_POST['file_name'] ?? '';
$fileType = $_POST['file_type'] ?? '';
$fileSize = intval($_POST['file_size'] ?? 0);

if ($chatId <= 0 || empty($fileName)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Parameter']);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];
$isVideo = strpos($fileType, 'video/') === 0;
$isImage = strpos($fileType, 'image/') === 0;

if (!$isImage && !$isVideo) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiger Dateityp']);
    exit;
}

// Check if we have a chunk
if (!isset($_FILES['chunk'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Kein Chunk empfangen',
        'debug' => [
            'files_keys' => array_keys($_FILES),
            'post_keys' => array_keys($_POST),
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set'
        ]
    ]);
    exit;
}

if ($_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    http_response_code(400);
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'Die Datei überschreitet die upload_max_filesize Direktive',
        UPLOAD_ERR_FORM_SIZE => 'Die Datei überschreitet die MAX_FILE_SIZE Direktive',
        UPLOAD_ERR_PARTIAL => 'Die Datei wurde nur teilweise hochgeladen',
        UPLOAD_ERR_NO_FILE => 'Keine Datei wurde hochgeladen',
        UPLOAD_ERR_NO_TMP_DIR => 'Fehlender temporärer Ordner',
        UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben der Datei auf die Festplatte',
        UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt'
    ];
    $errorCode = $_FILES['chunk']['error'];
    $errorMsg = $errorMessages[$errorCode] ?? 'Unbekannter Upload-Fehler: ' . $errorCode;
    
    // Add helpful information for UPLOAD_ERR_INI_SIZE
    if ($errorCode === UPLOAD_ERR_INI_SIZE) {
        $chunkSize = $_FILES['chunk']['size'] ?? 0;
        $chunkSizeMB = round($chunkSize / 1024 / 1024, 2);
        $uploadMaxSize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $phpIniPath = php_ini_loaded_file();
        $errorMsg .= " (Chunk: {$chunkSizeMB}MB, PHP-Limit: upload_max_filesize={$uploadMaxSize}, post_max_size={$postMaxSize})";
        if ($phpIniPath) {
            $errorMsg .= " - php.ini: {$phpIniPath}";
        }
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $errorMsg, 
        'error_code' => $errorCode,
        'chunk_size' => $_FILES['chunk']['size'] ?? 0,
        'php_limits' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ]
    ]);
    exit;
}

// Create temporary directory for chunks
$tempDir = '../uploads/chat/temp/' . $chatId . '/' . md5($fileName . $fileSize) . '/';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Save chunk
$chunkFile = $tempDir . 'chunk_' . $chunkIndex;
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern des Chunks']);
    exit;
}

// Check if this is the last chunk
if ($chunkIndex === $totalChunks - 1) {
    // All chunks received, combine them
    $finalFile = '';
    $finalPath = '';
    
    // For both videos and images, save to final location
    if ($isVideo || $isImage) {
        $uploadDir = '../uploads/chat/' . $chatId . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $finalFileName = uniqid('chat_', true) . '_' . time() . '.' . $fileExtension;
        $finalPath = $uploadDir . $finalFileName;
        
        // Combine all chunks
        $finalHandle = fopen($finalPath, 'wb');
        if (!$finalHandle) {
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Fehler beim Erstellen der finalen Datei']);
            exit;
        }
        
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $tempDir . 'chunk_' . $i;
            if (!file_exists($chunkPath)) {
                fclose($finalHandle);
                @unlink($finalPath);
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => "Chunk {$i} fehlt"]);
                exit;
            }
            
            $chunkHandle = fopen($chunkPath, 'rb');
            if ($chunkHandle) {
                while (!feof($chunkHandle)) {
                    fwrite($finalHandle, fread($chunkHandle, 8192));
                }
                fclose($chunkHandle);
                @unlink($chunkPath); // Delete chunk after combining
            }
        }
        
        fclose($finalHandle);
        
        // Clean up temp directory
        @rmdir($tempDir);
        
        // Verify file size
        if (filesize($finalPath) !== $fileSize) {
            @unlink($finalPath);
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Dateigröße stimmt nicht überein']);
            exit;
        }
        
        $relativePath = 'uploads/chat/' . $chatId . '/' . $finalFileName;
        $fileTypeForResponse = $isVideo ? 'video' : 'image';
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Datei erfolgreich hochgeladen',
            'file_path' => $relativePath,
            'file_type' => $fileTypeForResponse,
            'file_name' => $fileName
        ]);
    } else {
        // Invalid file type
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ungültiger Dateityp für Chunked Upload']);
        exit;
    }
} else {
    // More chunks to come
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => "Chunk {$chunkIndex} von {$totalChunks} empfangen",
        'chunk_received' => $chunkIndex + 1,
        'total_chunks' => $totalChunks
    ]);
}

ob_end_flush();
?>

