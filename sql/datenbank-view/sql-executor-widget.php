<?php
// SQL Executor Widget - Kann in andere Seiten eingebunden werden
// Erwartet: $conn und $driver müssen bereits definiert sein

if (!isset($conn)) {
    // Fallback: Erstelle eigene Verbindung falls nicht vorhanden
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../db.php';
    $conn = getDBConnection();
}

if (!isset($driver)) {
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
}

$error = null;
$success = null;
$results = null;
$affectedRows = 0;
$executionTime = 0;
$executedSql = '';

// Handle SQL execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql']) && isset($_POST['sql_executor_widget'])) {
    $sql = trim($_POST['sql']);
    $executedSql = $sql;
    
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
function formatValueSql($value, $driver = 'mysql') {
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

<!-- SQL Executor Widget -->
<div class="sql-executor-widget">
    <div class="sql-executor-header" onclick="toggleSqlExecutor()">
        <h3>SQL Executor</h3>
        <svg class="sql-executor-toggle" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </div>
    
    <div class="sql-executor-content" id="sqlExecutorContent" style="display: none;">
        <?php if ($error): ?>
            <div class="db-error" style="margin-bottom: 12px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="sql-success" style="margin-bottom: 12px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="sql-info" style="margin-bottom: 12px;">
            <strong>Hinweis:</strong> Erlaubte Befehle: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, SHOW, DESCRIBE, EXPLAIN, PRAGMA. 
            Gefährliche Befehle wie DROP DATABASE, TRUNCATE, GRANT sind blockiert.
        </div>
        
        <form method="POST" class="sql-editor-container" id="sqlExecutorForm">
            <input type="hidden" name="sql_executor_widget" value="1">
            
            <div class="sql-editor-wrapper">
                <div class="sql-quick-commands">
                    <button type="button" class="sql-quick-btn" onclick="insertSqlCommand('SHOW TABLES;')">SHOW TABLES</button>
                    <button type="button" class="sql-quick-btn" onclick="insertSqlCommand('SELECT * FROM users LIMIT 10;')">SELECT Beispiel</button>
                    <button type="button" class="sql-quick-btn" onclick="insertSqlCommand('CREATE TABLE test (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100));')">CREATE TABLE Beispiel</button>
                    <button type="button" class="sql-quick-btn" onclick="insertSqlCommand('DESCRIBE users;')">DESCRIBE</button>
                </div>
                
                <textarea 
                    id="sqlEditorWidget" 
                    name="sql" 
                    class="sql-editor" 
                    placeholder="Geben Sie hier Ihren SQL-Befehl ein...&#10;&#10;Beispiele:&#10;SELECT * FROM users;&#10;CREATE TABLE test (id INT, name VARCHAR(100));&#10;SHOW TABLES;"
                ><?php echo htmlspecialchars($executedSql); ?></textarea>
            </div>
            
            <div class="sql-executor-actions">
                <button type="button" class="db-btn db-btn-secondary" onclick="clearSqlEditor()">Löschen</button>
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
            <div class="db-table-wrapper" style="max-height: 400px; overflow-y: auto;">
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
                                    <td><?php echo formatValueSql($value, $driver); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($results !== null && empty($results)): ?>
            <div class="db-empty">Keine Ergebnisse gefunden.</div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleSqlExecutor() {
        const content = document.getElementById('sqlExecutorContent');
        const toggle = document.querySelector('.sql-executor-toggle');
        const widget = document.querySelector('.sql-executor-widget');
        const isVisible = content.style.display !== 'none';
        
        if (isVisible) {
            content.style.display = 'none';
            toggle.style.transform = 'rotate(0deg)';
            if (widget) {
                widget.style.maxHeight = '60px';
                widget.style.minHeight = '60px';
            }
        } else {
            content.style.display = 'flex';
            toggle.style.transform = 'rotate(180deg)';
            if (widget) {
                widget.style.maxHeight = '80vh';
                widget.style.minHeight = '60px';
            }
            // Auto-resize textarea
            setTimeout(function() {
                const editor = document.getElementById('sqlEditorWidget');
                if (editor) {
                    editor.style.height = 'auto';
                    editor.style.height = Math.max(150, editor.scrollHeight) + 'px';
                }
            }, 100);
        }
    }
    
    function insertSqlCommand(sql) {
        const editor = document.getElementById('sqlEditorWidget');
        if (editor) {
            editor.value = sql;
            editor.focus();
            // Auto-resize
            editor.style.height = 'auto';
            editor.style.height = (editor.scrollHeight) + 'px';
        }
    }
    
    function clearSqlEditor() {
        const editor = document.getElementById('sqlEditorWidget');
        if (editor && confirm('Möchten Sie den Editor wirklich löschen?')) {
            editor.value = '';
            editor.style.height = '150px';
        }
    }
    
    // Auto-resize textarea
    const editor = document.getElementById('sqlEditorWidget');
    if (editor) {
        editor.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Set initial height
        editor.style.height = '150px';
    }
    
    // Auto-open if there are results or errors
    <?php if ($error || $success || $results !== null): ?>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            toggleSqlExecutor();
        }, 100);
    });
    <?php endif; ?>
    
    // Handle form submission - keep widget open
    const sqlForm = document.getElementById('sqlExecutorForm');
    if (sqlForm) {
        sqlForm.addEventListener('submit', function(e) {
            // Ensure widget is open when submitting
            const content = document.getElementById('sqlExecutorContent');
            if (content && content.style.display === 'none') {
                toggleSqlExecutor();
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg> Ausführe...';
            }
        });
    }
</script>

