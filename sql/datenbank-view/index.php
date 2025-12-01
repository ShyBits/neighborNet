<?php
// Datenbank-View Interface
// Zeigt alle Tabellen und ermöglicht Navigation zwischen ihnen

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../db.php';

// Stelle sicher, dass alle Tabellen vorhanden sind
if (file_exists(__DIR__ . '/../universal-schema.php')) {
    require_once __DIR__ . '/../universal-schema.php';
    if (function_exists('ensureAllTables')) {
        try {
            ensureAllTables();
        } catch(Exception $e) {
            // Ignoriere Fehler beim Schema-Check
        }
    }
}

// Session initialisieren und starten
initSession();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Einfache Authentifizierung - nur für eingeloggte User
// Optional: Kann entfernt werden für öffentlichen Zugang
// Setze $requireAuth auf false, um die Authentifizierung zu deaktivieren
$requireAuth = false; // Auf false für Entwicklung, true für Produktion
if ($requireAuth && (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest']))) {
    header('Content-Type: text/html; charset=utf-8');
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Zugriff verweigert</title></head><body style="padding: 40px; text-align: center;"><h1>Zugriff verweigert</h1><p>Bitte <a href="../../index.php">einloggen</a> um auf die Datenbank-View zuzugreifen.</p></body></html>');
}

$conn = getDBConnection();
$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

// Hole alle Tabellen aus der Datenbank
$tables = [];
try {
    if ($driver === 'mysql') {
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($driver === 'pgsql') {
        $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($driver === 'sqlite') {
        $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $error = "Fehler beim Abrufen der Tabellen: " . $e->getMessage();
}

// Hole Zeilenanzahl für jede Tabelle
$tableInfo = [];
foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM `{$table}`");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $tableInfo[$table] = [
            'count' => $result['count'] ?? 0,
            'name' => $table
        ];
    } catch (PDOException $e) {
        $tableInfo[$table] = [
            'count' => '?',
            'name' => $table,
            'error' => $e->getMessage()
        ];
    }
}

// Sortiere Tabellen alphabetisch
ksort($tableInfo);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank-View - NeighborNet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="db-view-container">
        <header class="db-view-header">
            <h1>📊 Datenbank-View</h1>
            <div class="db-view-info">
                <span class="db-info-item">Datenbank: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong></span>
                <span class="db-info-item">Host: <strong><?php echo htmlspecialchars(DB_HOST); ?></strong></span>
                <span class="db-info-item">Tabellen: <strong><?php echo count($tables); ?></strong></span>
            </div>
        </header>

        <main class="db-view-main">
            <?php if (isset($error)): ?>
                <div class="db-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="db-actions-bar">
                <button class="db-btn db-btn-primary" onclick="showCreateTableModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Neue Tabelle erstellen
                </button>
            </div>
            
            <div class="db-tables-grid">
                <?php foreach ($tableInfo as $table): ?>
                    <a href="view-table.php?table=<?php echo urlencode($table['name']); ?>" class="db-table-card">
                        <div class="db-table-card-header">
                            <h2><?php echo htmlspecialchars($table['name']); ?></h2>
                            <span class="db-table-count">
                                <?php 
                                if (isset($table['error'])) {
                                    echo '⚠️';
                                } else {
                                    echo $table['count'] . ' Zeilen';
                                }
                                ?>
                            </span>
                        </div>
                        <?php if (isset($table['error'])): ?>
                            <div class="db-table-error"><?php echo htmlspecialchars($table['error']); ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
    
    <?php 
    // Include SQL Executor Widget
    require_once __DIR__ . '/sql-executor-widget.php';
    ?>

    <!-- Create Table Modal -->
    <div id="dbCreateTableModal" class="db-modal" style="display: none;">
        <div class="db-modal-content" style="max-width: 900px;">
            <div class="db-modal-header">
                <h2>Neue Tabelle erstellen</h2>
                <button class="db-modal-close" onclick="closeCreateTableModal()">&times;</button>
            </div>
            <form id="dbCreateTableForm" class="db-edit-form">
                <div class="db-form-field">
                    <label for="tableName">Tabellenname <span class="db-required">*</span></label>
                    <input type="text" id="tableName" name="table_name" class="db-form-input" 
                           placeholder="z.B. meine_tabelle" pattern="[a-zA-Z0-9_]+" required>
                    <small style="color: var(--text-light); font-size: 12px;">Nur Buchstaben, Zahlen und Unterstriche erlaubt</small>
                </div>
                
                <div class="db-form-field">
                    <label>Spalten</label>
                    <div id="tableColumns" style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Columns will be added here -->
                    </div>
                    <button type="button" class="db-btn db-btn-secondary" onclick="addTableColumn()" style="margin-top: 12px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Spalte hinzufügen
                    </button>
                </div>
                
                <div class="db-modal-actions">
                    <button type="button" class="db-btn db-btn-secondary" onclick="closeCreateTableModal()">Abbrechen</button>
                    <button type="submit" class="db-btn db-btn-primary">Tabelle erstellen</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let columnCount = 0;
        
        const driver = <?php echo json_encode($driver); ?>;
        
        // Column types based on database driver
        let columnTypes = [];
        if (driver === 'mysql') {
            columnTypes = [
                { value: 'INT', label: 'INT' },
                { value: 'BIGINT', label: 'BIGINT' },
                { value: 'VARCHAR(255)', label: 'VARCHAR(255)' },
                { value: 'TEXT', label: 'TEXT' },
                { value: 'LONGTEXT', label: 'LONGTEXT' },
                { value: 'DATETIME', label: 'DATETIME' },
                { value: 'DATE', label: 'DATE' },
                { value: 'TIME', label: 'TIME' },
                { value: 'TIMESTAMP', label: 'TIMESTAMP' },
                { value: 'DECIMAL(10,2)', label: 'DECIMAL(10,2)' },
                { value: 'FLOAT', label: 'FLOAT' },
                { value: 'DOUBLE', label: 'DOUBLE' },
                { value: 'BOOLEAN', label: 'BOOLEAN' },
                { value: 'TINYINT(1)', label: 'TINYINT(1)' }
            ];
        } else if (driver === 'pgsql') {
            columnTypes = [
                { value: 'INTEGER', label: 'INTEGER' },
                { value: 'BIGINT', label: 'BIGINT' },
                { value: 'SERIAL', label: 'SERIAL' },
                { value: 'BIGSERIAL', label: 'BIGSERIAL' },
                { value: 'VARCHAR(255)', label: 'VARCHAR(255)' },
                { value: 'TEXT', label: 'TEXT' },
                { value: 'TIMESTAMP', label: 'TIMESTAMP' },
                { value: 'DATE', label: 'DATE' },
                { value: 'TIME', label: 'TIME' },
                { value: 'NUMERIC(10,2)', label: 'NUMERIC(10,2)' },
                { value: 'REAL', label: 'REAL' },
                { value: 'DOUBLE PRECISION', label: 'DOUBLE PRECISION' },
                { value: 'BOOLEAN', label: 'BOOLEAN' }
            ];
        } else { // SQLite
            columnTypes = [
                { value: 'INTEGER', label: 'INTEGER' },
                { value: 'TEXT', label: 'TEXT' },
                { value: 'REAL', label: 'REAL' },
                { value: 'BLOB', label: 'BLOB' },
                { value: 'NUMERIC', label: 'NUMERIC' }
            ];
        }
        
        function showCreateTableModal() {
            columnCount = 0;
            document.getElementById('tableColumns').innerHTML = '';
            addTableColumn(); // Add first column
            document.getElementById('dbCreateTableModal').style.display = 'flex';
        }
        
        function closeCreateTableModal() {
            document.getElementById('dbCreateTableModal').style.display = 'none';
            document.getElementById('dbCreateTableForm').reset();
        }
        
        function addTableColumn() {
            const container = document.getElementById('tableColumns');
            const columnId = 'column_' + columnCount++;
            
            const columnDiv = document.createElement('div');
            columnDiv.className = 'db-table-column-editor';
            columnDiv.style.cssText = 'display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 1fr auto; gap: 8px; align-items: end; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; background: #f8f9fa;';
            
            // Column Name
            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.placeholder = 'Spaltenname';
            nameInput.className = 'db-form-input';
            nameInput.name = 'columns[' + columnId + '][name]';
            nameInput.required = true;
            nameInput.pattern = '[a-zA-Z0-9_]+';
            
            // Column Type
            const typeSelect = document.createElement('select');
            typeSelect.className = 'db-form-input';
            typeSelect.name = 'columns[' + columnId + '][type]';
            typeSelect.required = true;
            columnTypes.forEach(type => {
                const option = document.createElement('option');
                option.value = type.value;
                option.textContent = type.label;
                typeSelect.appendChild(option);
            });
            
            // Nullable
            const nullableCheck = document.createElement('input');
            nullableCheck.type = 'checkbox';
            nullableCheck.name = 'columns[' + columnId + '][nullable]';
            nullableCheck.checked = true;
            nullableCheck.id = 'nullable_' + columnId;
            
            const nullableLabel = document.createElement('label');
            nullableLabel.htmlFor = 'nullable_' + columnId;
            nullableLabel.textContent = 'NULL';
            nullableLabel.style.cssText = 'font-size: 13px; cursor: pointer;';
            
            const nullableDiv = document.createElement('div');
            nullableDiv.style.cssText = 'display: flex; align-items: center; gap: 4px;';
            nullableDiv.appendChild(nullableCheck);
            nullableDiv.appendChild(nullableLabel);
            
            // Primary Key
            const pkCheck = document.createElement('input');
            pkCheck.type = 'checkbox';
            pkCheck.name = 'columns[' + columnId + '][primary_key]';
            pkCheck.id = 'pk_' + columnId;
            
            const pkLabel = document.createElement('label');
            pkLabel.htmlFor = 'pk_' + columnId;
            pkLabel.textContent = 'PK';
            pkLabel.style.cssText = 'font-size: 13px; cursor: pointer;';
            
            const pkDiv = document.createElement('div');
            pkDiv.style.cssText = 'display: flex; align-items: center; gap: 4px;';
            pkDiv.appendChild(pkCheck);
            pkDiv.appendChild(pkLabel);
            
            // Auto Increment
            const aiCheck = document.createElement('input');
            aiCheck.type = 'checkbox';
            aiCheck.name = 'columns[' + columnId + '][auto_increment]';
            aiCheck.id = 'ai_' + columnId;
            
            const aiLabel = document.createElement('label');
            aiLabel.htmlFor = 'ai_' + columnId;
            aiLabel.textContent = 'AI';
            aiLabel.style.cssText = 'font-size: 13px; cursor: pointer;';
            
            const aiDiv = document.createElement('div');
            aiDiv.style.cssText = 'display: flex; align-items: center; gap: 4px;';
            aiDiv.appendChild(aiCheck);
            aiDiv.appendChild(aiLabel);
            
            // Remove Button
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'db-btn db-btn-delete';
            removeBtn.style.cssText = 'padding: 6px 12px; font-size: 12px;';
            removeBtn.innerHTML = '×';
            removeBtn.onclick = function() {
                columnDiv.remove();
            };
            
            columnDiv.appendChild(nameInput);
            columnDiv.appendChild(typeSelect);
            columnDiv.appendChild(nullableDiv);
            columnDiv.appendChild(pkDiv);
            columnDiv.appendChild(aiDiv);
            columnDiv.appendChild(removeBtn);
            
            container.appendChild(columnDiv);
        }
        
        document.getElementById('dbCreateTableForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const tableName = formData.get('table_name');
            
            if (!tableName || !/^[a-zA-Z0-9_]+$/.test(tableName)) {
                alert('Ungültiger Tabellenname. Nur Buchstaben, Zahlen und Unterstriche erlaubt.');
                return;
            }
            
            // Collect columns
            const columns = [];
            const columnInputs = this.querySelectorAll('[name^="columns["]');
            const columnIds = new Set();
            
            columnInputs.forEach(input => {
                const match = input.name.match(/columns\[(.+?)\]\[(.+?)\]/);
                if (match) {
                    const columnId = match[1];
                    const field = match[2];
                    
                    if (!columnIds.has(columnId)) {
                        columnIds.add(columnId);
                        columns.push({
                            name: '',
                            type: '',
                            nullable: false,
                            primary_key: false,
                            auto_increment: false
                        });
                    }
                    
                    const index = Array.from(columnIds).indexOf(columnId);
                    const value = input.type === 'checkbox' ? input.checked : input.value;
                    
                    if (field === 'name') columns[index].name = value;
                    else if (field === 'type') columns[index].type = value;
                    else if (field === 'nullable') columns[index].nullable = value;
                    else if (field === 'primary_key') columns[index].primary_key = value;
                    else if (field === 'auto_increment') columns[index].auto_increment = value;
                }
            });
            
            // Filter out empty columns
            const validColumns = columns.filter(col => col.name && col.type);
            
            if (validColumns.length === 0) {
                alert('Mindestens eine Spalte ist erforderlich.');
                return;
            }
            
            // Build SQL - escape table name based on driver
            const tableNameEscaped = driver === 'pgsql' ? '"' + tableName + '"' : '`' + tableName + '`';
            let sql = 'CREATE TABLE ' + tableNameEscaped + ' (';
            const columnDefs = [];
            
            validColumns.forEach(col => {
                // Escape column name based on driver
                const colNameEscaped = driver === 'pgsql' ? '"' + col.name + '"' : '`' + col.name + '`';
                let def = colNameEscaped + ' ';
                
                // Handle auto increment based on driver
                if (col.auto_increment) {
                    if (driver === 'mysql') {
                        // MySQL: Use AUTO_INCREMENT
                        def += col.type + ' NOT NULL AUTO_INCREMENT';
                    } else if (driver === 'pgsql') {
                        // PostgreSQL: Use SERIAL or BIGSERIAL
                        if (col.type.includes('INT') || col.type === 'INTEGER') {
                            def += 'SERIAL';
                        } else if (col.type === 'BIGINT') {
                            def += 'BIGSERIAL';
                        } else {
                            def += col.type + ' NOT NULL';
                        }
                    } else if (driver === 'sqlite') {
                        // SQLite: Use INTEGER PRIMARY KEY AUTOINCREMENT
                        def += 'INTEGER';
                        if (!col.nullable) {
                            def += ' NOT NULL';
                        }
                        def += ' PRIMARY KEY AUTOINCREMENT';
                        columnDefs.push(def);
                        return; // Skip further processing for SQLite auto increment
                    }
                } else {
                    def += col.type;
                    if (!col.nullable) {
                        def += ' NOT NULL';
                    }
                }
                
                columnDefs.push(def);
            });
            
            sql += columnDefs.join(', ');
            
            // Add primary key (skip if SQLite auto increment already has it)
            const hasAutoIncrementPK = driver === 'sqlite' && validColumns.some(col => col.auto_increment && col.primary_key);
            const primaryKeys = validColumns.filter(col => col.primary_key && !(driver === 'sqlite' && col.auto_increment)).map(col => {
                return driver === 'pgsql' ? '"' + col.name + '"' : '`' + col.name + '`';
            });
            if (primaryKeys.length > 0) {
                sql += ', PRIMARY KEY (' + primaryKeys.join(', ') + ')';
            } else if (hasAutoIncrementPK) {
                // SQLite auto increment already includes PRIMARY KEY
            }
            
            // Add engine and charset for MySQL
            if (driver === 'mysql') {
                sql += ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
            } else if (driver === 'pgsql') {
                sql += ');';
            } else if (driver === 'sqlite') {
                sql += ');';
            } else {
                sql += ');';
            }
            
            console.log('Generated SQL:', sql);
            
            // Execute via API
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Erstelle...';
            
            console.log('Executing SQL:', sql);
            
            const executeFormData = new FormData();
            executeFormData.append('sql', sql);
            
            fetch('sql-executor.php', {
                method: 'POST',
                body: executeFormData
            })
            .then(response => response.text())
            .then(html => {
                console.log('Response received');
                // Check if there's an error in the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const errorDiv = doc.querySelector('.db-error');
                const successDiv = doc.querySelector('.sql-success');
                
                if (errorDiv) {
                    const errorText = errorDiv.textContent.trim();
                    alert('Fehler: ' + errorText);
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                } else if (successDiv) {
                    const successText = successDiv.textContent.trim();
                    alert(successText || 'Tabelle erfolgreich erstellt!');
                    closeCreateTableModal();
                    setTimeout(() => window.location.reload(), 500);
                } else if (html.includes('erfolgreich') || html.includes('success')) {
                    alert('Tabelle erfolgreich erstellt!');
                    closeCreateTableModal();
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    // Try to parse as JSON if it's an API response
                    try {
                        const json = JSON.parse(html);
                        if (json.success) {
                            alert('Tabelle erfolgreich erstellt!');
                            closeCreateTableModal();
                            setTimeout(() => window.location.reload(), 500);
                        } else {
                            alert('Fehler: ' + (json.message || 'Unbekannter Fehler'));
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        }
                    } catch (e) {
                        // Assume success if no error is visible
                        alert('Tabelle wurde möglicherweise erstellt. Seite wird neu geladen...');
                        setTimeout(() => window.location.reload(), 500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Erstellen der Tabelle: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
        
        // Close modal on outside click
        document.getElementById('dbCreateTableModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateTableModal();
            }
        });
    </script>
</body>
</html>

