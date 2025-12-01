<?php
// SQL Command Executor für Datenbank-View

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';

// Session initialisieren
try {
    initSession();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (Exception $e) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Authentifizierung
$requireAuth = false; // Auf false für Entwicklung, true für Produktion
if ($requireAuth && (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest']))) {
    header('Content-Type: text/html; charset=utf-8');
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Zugriff verweigert</title></head><body style="padding: 40px; text-align: center;"><h1>Zugriff verweigert</h1><p>Bitte <a href="../../index.php">einloggen</a> um auf die Datenbank-View zuzugreifen.</p></body></html>');
}

$conn = getDBConnection();
$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

$error = null;
$success = null;
$results = null;
$affectedRows = 0;
$executionTime = 0;

// Handle SQL execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    $sql = trim($_POST['sql']);
    
    if (!empty($sql)) {
        $startTime = microtime(true);
        
        try {
            // Sicherheitsprüfung: Erlaube nur bestimmte SQL-Befehle
            $sqlUpper = strtoupper(trim($sql));
            $firstWord = strtok($sqlUpper, " \n\r\t");
            
            // Erlaubte Befehle
            $allowedCommands = [
                'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP',
                'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'PRAGMA', 'USE'
            ];
            
            // Blockiere gefährliche Befehle
            $blockedCommands = [
                'DROP DATABASE', 'DROP SCHEMA', 'TRUNCATE', 'GRANT', 'REVOKE',
                'FLUSH', 'KILL', 'SHUTDOWN', 'RESTART'
            ];
            
            $isBlocked = false;
            foreach ($blockedCommands as $blocked) {
                if (stripos($sqlUpper, $blocked) !== false) {
                    $isBlocked = true;
                    break;
                }
            }
            
            if ($isBlocked) {
                throw new Exception('Dieser Befehl ist aus Sicherheitsgründen nicht erlaubt.');
            }
            
            if (!in_array($firstWord, $allowedCommands)) {
                throw new Exception('Nur SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, SHOW, DESCRIBE, EXPLAIN und PRAGMA Befehle sind erlaubt.');
            }
            
            // Führe SQL aus
            if (stripos($sqlUpper, 'SELECT') === 0 || stripos($sqlUpper, 'SHOW') === 0 || 
                stripos($sqlUpper, 'DESCRIBE') === 0 || stripos($sqlUpper, 'DESC') === 0 ||
                stripos($sqlUpper, 'EXPLAIN') === 0 || stripos($sqlUpper, 'PRAGMA') === 0) {
                // SELECT-ähnliche Befehle: Gib Ergebnisse zurück
                $stmt = $conn->query($sql);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $affectedRows = count($results);
                if (stripos($sqlUpper, 'SHOW') === 0) {
                    $success = "Befehl erfolgreich ausgeführt. " . count($results) . " Ergebnis(se).";
                }
            } else {
                // INSERT, UPDATE, DELETE, CREATE, ALTER, DROP
                $affectedRows = $conn->exec($sql);
                if (stripos($sqlUpper, 'CREATE') === 0) {
                    $success = "Tabelle erfolgreich erstellt.";
                } elseif (stripos($sqlUpper, 'ALTER') === 0) {
                    $success = "Tabelle erfolgreich geändert.";
                } elseif (stripos($sqlUpper, 'DROP') === 0) {
                    $success = "Tabelle erfolgreich gelöscht.";
                } elseif (stripos($sqlUpper, 'INSERT') === 0) {
                    $success = "Zeile(n) erfolgreich eingefügt. {$affectedRows} Zeile(n) betroffen.";
                } elseif (stripos($sqlUpper, 'UPDATE') === 0) {
                    $success = "Zeile(n) erfolgreich aktualisiert. {$affectedRows} Zeile(n) betroffen.";
                } elseif (stripos($sqlUpper, 'DELETE') === 0) {
                    $success = "Zeile(n) erfolgreich gelöscht. {$affectedRows} Zeile(n) betroffen.";
                } else {
                    $success = "Befehl erfolgreich ausgeführt. {$affectedRows} Zeile(n) betroffen.";
                }
            }
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
        } catch (PDOException $e) {
            $error = 'SQL Fehler: ' . $e->getMessage();
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
        } catch (Exception $e) {
            $error = $e->getMessage();
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
        }
    }
}

// Funktion zum Formatieren von Werten
function formatValue($value, $driver = 'mysql') {
    if ($value === null) {
        return '<span class="db-null">NULL</span>';
    }
    
    if (is_string($value) && strlen($value) > 200) {
        $preview = htmlspecialchars(substr($value, 0, 200));
        $full = htmlspecialchars($value);
        return '<span class="db-long-text" title="' . $full . '">' . $preview . '...</span>';
    }
    
    if (is_string($value) && (strpos($value, 'data:image') === 0 || strpos($value, '[{"data":"data:image') === 0)) {
        $charCount = strlen($value);
        $sizeKB = round($charCount / 1024, 2);
        return '<span class="db-image-data">📷 Bilddaten (' . number_format($charCount) . ' Zeichen, ' . $sizeKB . ' KB)</span>';
    }
    
    if (is_string($value) && ($value[0] === '[' || $value[0] === '{')) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return '<span class="db-json">JSON (' . count($decoded, COUNT_RECURSIVE) . ' Einträge)</span>';
        }
    }
    
    return htmlspecialchars($value);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Executor - Datenbank-View</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .sql-editor-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 24px;
        }
        
        .sql-editor-wrapper {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .sql-editor-label {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-color);
        }
        
        .sql-editor {
            width: 100%;
            min-height: 300px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
            background: #f8f9fa;
        }
        
        .sql-editor:focus {
            outline: none;
            border-color: var(--main-color);
            background: white;
        }
        
        .sql-executor-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        
        .sql-info {
            padding: 12px 16px;
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            border-radius: 4px;
            font-size: 13px;
            color: #1976D2;
        }
        
        .sql-success {
            padding: 12px 16px;
            background: #e8f5e9;
            border-left: 4px solid var(--success-color);
            border-radius: 4px;
            font-size: 13px;
            color: #2e7d32;
        }
        
        .sql-execution-info {
            padding: 8px 12px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 12px;
            color: var(--text-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .sql-quick-commands {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .sql-quick-btn {
            padding: 6px 12px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .sql-quick-btn:hover {
            background: var(--main-color);
            color: white;
            border-color: var(--main-color);
        }
    </style>
</head>
<body>
    <div class="db-view-container">
        <header class="db-view-header">
            <div class="db-view-header-top">
                <a href="index.php" class="db-back-btn">← Zurück zu Tabellen</a>
                <h1>SQL Executor</h1>
            </div>
            <div class="db-view-info">
                <span class="db-info-item">Datenbank: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong></span>
                <span class="db-info-item">Host: <strong><?php echo htmlspecialchars(DB_HOST); ?></strong></span>
            </div>
        </header>

        <main class="db-view-main">
            <?php if ($error): ?>
                <div class="db-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="sql-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="sql-info">
                <strong>Hinweis:</strong> Erlaubte Befehle: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, SHOW, DESCRIBE, EXPLAIN, PRAGMA. 
                Gefährliche Befehle wie DROP DATABASE, TRUNCATE, GRANT sind aus Sicherheitsgründen blockiert.
            </div>
            
            <form method="POST" class="sql-editor-container">
                <div class="sql-editor-wrapper">
                    <label class="sql-editor-label" for="sqlEditor">SQL Befehl eingeben:</label>
                    
                    <div class="sql-quick-commands">
                        <button type="button" class="sql-quick-btn" onclick="insertSQL('SHOW TABLES;')">SHOW TABLES</button>
                        <button type="button" class="sql-quick-btn" onclick="insertSQL('SELECT * FROM users LIMIT 10;')">SELECT Beispiel</button>
                        <button type="button" class="sql-quick-btn" onclick="insertSQL('CREATE TABLE test (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100));')">CREATE TABLE Beispiel</button>
                        <button type="button" class="sql-quick-btn" onclick="insertSQL('DESCRIBE users;')">DESCRIBE</button>
                    </div>
                    
                    <textarea 
                        id="sqlEditor" 
                        name="sql" 
                        class="sql-editor" 
                        placeholder="Geben Sie hier Ihren SQL-Befehl ein...&#10;&#10;Beispiele:&#10;SELECT * FROM users;&#10;CREATE TABLE test (id INT, name VARCHAR(100));&#10;SHOW TABLES;"
                        required
                    ><?php echo isset($_POST['sql']) ? htmlspecialchars($_POST['sql']) : ''; ?></textarea>
                </div>
                
                <div class="sql-executor-actions">
                    <button type="button" class="db-btn db-btn-secondary" onclick="clearEditor()">Löschen</button>
                    <button type="submit" class="db-btn db-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                        Ausführen
                    </button>
                </div>
            </form>
            
            <?php if ($executionTime > 0): ?>
                <div class="sql-execution-info">
                    <span>Ausführungszeit: <strong><?php echo $executionTime; ?>ms</strong></span>
                    <?php if ($results !== null): ?>
                        <span>Ergebnisse: <strong><?php echo count($results); ?> Zeilen</strong></span>
                    <?php elseif ($affectedRows > 0): ?>
                        <span>Betroffene Zeilen: <strong><?php echo $affectedRows; ?></strong></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($results !== null && !empty($results)): ?>
                <div class="db-table-wrapper">
                    <table class="db-table">
                        <thead>
                            <tr>
                                <?php if (!empty($results)): ?>
                                    <?php foreach (array_keys($results[0]) as $column): ?>
                                        <th><?php echo htmlspecialchars($column); ?></th>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?php echo formatValue($value, $driver); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($results !== null && empty($results)): ?>
                <div class="db-empty">Keine Ergebnisse gefunden.</div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function insertSQL(sql) {
            const editor = document.getElementById('sqlEditor');
            editor.value = sql;
            editor.focus();
        }
        
        function clearEditor() {
            if (confirm('Möchten Sie den Editor wirklich löschen?')) {
                document.getElementById('sqlEditor').value = '';
            }
        }
        
        // Auto-resize textarea
        const editor = document.getElementById('sqlEditor');
        editor.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Set initial height
        editor.style.height = '300px';
    </script>
</body>
</html>

