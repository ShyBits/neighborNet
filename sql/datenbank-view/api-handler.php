<?php
// API Handler für Datenbank-Operationen (Update, Delete, Insert)

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';

// Session initialisieren
try {
    initSession();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    // Ignoriere Session-Fehler für Datenbank-View
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Authentifizierung - Optional, kann deaktiviert werden
$requireAuth = false; // Auf false setzen für Entwicklung, true für Produktion
if ($requireAuth && (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest']))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Zugriff verweigert']);
    exit;
}

header('Content-Type: application/json');

$conn = getDBConnection();
$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Validierung: Nur erlaubte Tabellennamen
$tableName = $_POST['table'] ?? $_GET['table'] ?? '';
if (empty($tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
    echo json_encode(['success' => false, 'message' => 'Ungültiger Tabellenname']);
    exit;
}

try {
    switch ($action) {
        case 'delete':
            handleDelete($conn, $tableName, $driver);
            break;
        case 'update':
            handleUpdate($conn, $tableName, $driver);
            break;
        case 'insert':
            handleInsert($conn, $tableName, $driver);
            break;
        case 'get_row':
            handleGetRow($conn, $tableName, $driver);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
}

function handleDelete($conn, $tableName, $driver) {
    $id = $_POST['id'] ?? $_GET['id'] ?? null;
    $idColumn = $_POST['id_column'] ?? 'id';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID fehlt']);
        return;
    }
    
    // Validierung
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $idColumn)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger Spaltenname']);
        return;
    }
    
    // Hole Primary Key Spalte
    $primaryKey = getPrimaryKey($conn, $tableName, $driver);
    if (!$primaryKey) {
        $primaryKey = $idColumn; // Fallback
    }
    
    $stmt = $conn->prepare("DELETE FROM `{$tableName}` WHERE `{$primaryKey}` = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Zeile gelöscht', 'deleted' => $stmt->rowCount()]);
}

function handleUpdate($conn, $tableName, $driver) {
    $id = $_POST['id'] ?? null;
    
    // Parse data array from POST
    // FormData sendet data[column_name] als separate Keys
    $data = [];
    foreach ($_POST as $key => $value) {
        if (preg_match('/^data\[(.+)\]$/', $key, $matches)) {
            $columnName = $matches[1];
            // Validierung des Spaltennamens
            if (preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
                $data[$columnName] = $value === '' ? null : $value;
            }
        }
    }
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID fehlt']);
        return;
    }
    
    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Keine Daten zum Aktualisieren']);
        return;
    }
    
    // Hole Primary Key
    $primaryKey = getPrimaryKey($conn, $tableName, $driver);
    if (!$primaryKey) {
        echo json_encode(['success' => false, 'message' => 'Kein Primary Key gefunden']);
        return;
    }
    
    // Hole Spalteninformationen
    $columns = getTableColumns($conn, $tableName, $driver);
    $allowedColumns = array_map(function($col) use ($driver) {
        return getColumnName($col, $driver);
    }, $columns);
    
    // Validiere und bereite Update-Daten vor
    $updateFields = [];
    $updateValues = [];
    
    foreach ($data as $column => $value) {
        // Sicherheit: Nur erlaubte Spalten
        if (!in_array($column, $allowedColumns) || $column === $primaryKey) {
            continue; // Überspringe Primary Key und ungültige Spalten
        }
        
        // Validierung des Spaltennamens
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            continue;
        }
        
        $updateFields[] = "`{$column}` = ?";
        // Behandle leere Strings als NULL für nullable Spalten
        $updateValues[] = ($value === '' || $value === null) ? null : $value;
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'message' => 'Keine Daten zum Aktualisieren']);
        return;
    }
    
    // Füge ID am Ende hinzu
    $updateValues[] = $id;
    
    $sql = "UPDATE `{$tableName}` SET " . implode(', ', $updateFields) . " WHERE `{$primaryKey}` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($updateValues);
    
    echo json_encode(['success' => true, 'message' => 'Zeile aktualisiert', 'affected' => $stmt->rowCount()]);
}

function handleInsert($conn, $tableName, $driver) {
    // Parse data array from POST
    // FormData sendet data[column_name] als separate Keys
    $data = [];
    foreach ($_POST as $key => $value) {
        if (preg_match('/^data\[(.+)\]$/', $key, $matches)) {
            $columnName = $matches[1];
            // Validierung des Spaltennamens
            if (preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
                $data[$columnName] = $value === '' ? null : $value;
            }
        }
    }
    
    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'Keine Daten zum Einfügen']);
        return;
    }
    
    // Hole Spalteninformationen
    $columns = getTableColumns($conn, $tableName, $driver);
    $allowedColumns = array_map(function($col) use ($driver) {
        return getColumnName($col, $driver);
    }, $columns);
    
    // Validiere und bereite Insert-Daten vor
    $insertFields = [];
    $insertValues = [];
    $placeholders = [];
    
    foreach ($data as $column => $value) {
        // Sicherheit: Nur erlaubte Spalten
        if (!in_array($column, $allowedColumns)) {
            continue;
        }
        
        // Validierung des Spaltennamens
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            continue;
        }
        
        $insertFields[] = "`{$column}`";
        $placeholders[] = "?";
        // Behandle leere Strings als NULL
        $insertValues[] = ($value === '' || $value === null) ? null : $value;
    }
    
    if (empty($insertFields)) {
        echo json_encode(['success' => false, 'message' => 'Keine Daten zum Einfügen']);
        return;
    }
    
    $sql = "INSERT INTO `{$tableName}` (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->execute($insertValues);
    
    $newId = $conn->lastInsertId();
    
    echo json_encode(['success' => true, 'message' => 'Zeile eingefügt', 'id' => $newId]);
}

function handleGetRow($conn, $tableName, $driver) {
    $id = $_GET['id'] ?? null;
    $idColumn = $_GET['id_column'] ?? 'id';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID fehlt']);
        return;
    }
    
    // Hole Primary Key
    $primaryKey = getPrimaryKey($conn, $tableName, $driver);
    if (!$primaryKey) {
        $primaryKey = $idColumn;
    }
    
    $stmt = $conn->prepare("SELECT * FROM `{$tableName}` WHERE `{$primaryKey}` = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Zeile nicht gefunden']);
    }
}

function getPrimaryKey($conn, $tableName, $driver) {
    try {
        if ($driver === 'mysql') {
            $stmt = $conn->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return !empty($keys) ? $keys[0]['Column_name'] : 'id';
        } elseif ($driver === 'pgsql') {
            $stmt = $conn->prepare("
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = ?::regclass AND i.indisprimary
            ");
            $stmt->execute([$tableName]);
            $key = $stmt->fetch(PDO::FETCH_ASSOC);
            return $key ? $key['attname'] : 'id';
        } elseif ($driver === 'sqlite') {
            $stmt = $conn->query("PRAGMA table_info({$tableName})");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if (!empty($col['pk'])) {
                    return $col['name'];
                }
            }
            return 'id';
        }
    } catch (PDOException $e) {
        return 'id'; // Fallback
    }
    return 'id';
}

function getTableColumns($conn, $tableName, $driver) {
    if ($driver === 'mysql') {
        $stmt = $conn->query("DESCRIBE `{$tableName}`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($driver === 'pgsql') {
        $stmt = $conn->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($driver === 'sqlite') {
        $stmt = $conn->query("PRAGMA table_info({$tableName})");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return [];
}

function getColumnName($column, $driver) {
    if ($driver === 'mysql') {
        return $column['Field'] ?? '';
    } elseif ($driver === 'pgsql') {
        return $column['column_name'] ?? '';
    } elseif ($driver === 'sqlite') {
        return $column['name'] ?? '';
    }
    return '';
}
?>

