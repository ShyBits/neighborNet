<?php
// Zeigt Daten einer bestimmten Tabelle

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

// Einfache Authentifizierung
// Setze $requireAuth auf false, um die Authentifizierung zu deaktivieren
$requireAuth = false; // Auf false für Entwicklung, true für Produktion
if ($requireAuth && (!isset($_SESSION['user_id']) || isset($_SESSION['is_guest']))) {
    header('Content-Type: text/html; charset=utf-8');
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Zugriff verweigert</title></head><body style="padding: 40px; text-align: center;"><h1>Zugriff verweigert</h1><p>Bitte <a href="../../index.php">einloggen</a> um auf die Datenbank-View zuzugreifen.</p></body></html>');
}

$conn = getDBConnection();
$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

$tableName = $_GET['table'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50; // Zeilen pro Seite
$offset = ($page - 1) * $perPage;

// Validierung: Nur erlaubte Tabellennamen (verhindert SQL-Injection)
if (empty($tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
    header('Content-Type: text/html; charset=utf-8');
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fehler</title></head><body style="padding: 40px; text-align: center;"><h1>Ungültiger Tabellenname</h1><p><a href="index.php">Zurück zu Tabellen</a></p></body></html>');
}

$error = null;
$columns = [];
$data = [];
$totalRows = 0;
$totalPages = 0;

try {
    // Hole Spalteninformationen
    if ($driver === 'mysql') {
        $stmt = $conn->query("DESCRIBE `{$tableName}`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($driver === 'pgsql') {
        $stmt = $conn->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = ?
            ORDER BY ordinal_position
        ");
        $stmt->execute([$tableName]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($driver === 'sqlite') {
        $stmt = $conn->query("PRAGMA table_info({$tableName})");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Hole Primary Key Spalte
    $primaryKeyColumn = getPrimaryKeyColumn($conn, $tableName, $columns, $driver);

    // Hole Gesamtanzahl der Zeilen
    // $tableName ist bereits validiert durch preg_match
    $stmt = $conn->query("SELECT COUNT(*) as count FROM `{$tableName}`");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRows = intval($result['count'] ?? 0);
    $totalPages = $totalRows > 0 ? ceil($totalRows / $perPage) : 0;
    
    // Stelle sicher, dass die Seite nicht größer als die maximale Anzahl von Seiten ist
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    // Hole Daten mit Pagination
    // Verwende direkte Werte für LIMIT/OFFSET, da diese nicht als Platzhalter funktionieren
    // $tableName ist bereits durch preg_match validiert
    $perPageInt = intval($perPage);
    $offsetInt = intval($offset);
    $stmt = $conn->query("SELECT * FROM `{$tableName}` LIMIT {$perPageInt} OFFSET {$offsetInt}");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Fehler: " . $e->getMessage();
}

// Funktion zum Formatieren von Werten
function formatValue($value, $column = null) {
    if ($value === null) {
        return '<span class="db-null">NULL</span>';
    }
    
    // Prüfe ob es ein LONGTEXT oder sehr langer Text ist
    if (is_string($value) && strlen($value) > 200) {
        $preview = htmlspecialchars(substr($value, 0, 200));
        $full = htmlspecialchars($value);
        return '<span class="db-long-text" title="' . $full . '">' . $preview . '...</span>';
    }
    
    // Prüfe ob es base64 Bilddaten sind
    if (is_string($value) && (strpos($value, 'data:image') === 0 || strpos($value, '[{"data":"data:image') === 0 || strpos($value, '{"data":"data:image') === 0)) {
        $charCount = strlen($value);
        $sizeKB = round($charCount / 1024, 2);
        return '<span class="db-image-data">📷 Bilddaten (' . number_format($charCount) . ' Zeichen, ' . $sizeKB . ' KB)</span>';
    }
    
    // Prüfe ob es JSON ist
    if (is_string($value) && ($value[0] === '[' || $value[0] === '{')) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return '<span class="db-json">JSON (' . count($decoded, COUNT_RECURSIVE) . ' Einträge)</span>';
        }
    }
    
    return htmlspecialchars($value);
}

// Funktion zum Ermitteln des Spaltennamens
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

// Funktion zum Ermitteln des Spaltentyps
function getColumnType($column, $driver) {
    if ($driver === 'mysql') {
        return $column['Type'] ?? '';
    } elseif ($driver === 'pgsql') {
        return $column['data_type'] ?? '';
    } elseif ($driver === 'sqlite') {
        return $column['type'] ?? '';
    }
    return '';
}

// Funktion zum Prüfen ob Spalte nullable ist
function isColumnNullable($column, $driver) {
    if ($driver === 'mysql') {
        return ($column['Null'] ?? '') === 'YES';
    } elseif ($driver === 'pgsql') {
        return ($column['is_nullable'] ?? '') === 'YES';
    } elseif ($driver === 'sqlite') {
        return ($column['notnull'] ?? 0) == 0;
    }
    return true;
}

// Funktion zum Ermitteln des Primary Keys
function getPrimaryKeyColumn($conn, $tableName, $columns, $driver) {
    try {
        if ($driver === 'mysql') {
            $stmt = $conn->query("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
            $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($keys)) {
                return $keys[0]['Column_name'] ?? 'id';
            }
            // Fallback: Suche in columns
            foreach ($columns as $column) {
                if (($column['Key'] ?? '') === 'PRI') {
                    return $column['Field'] ?? 'id';
                }
            }
        } elseif ($driver === 'pgsql') {
            $stmt = $conn->prepare("
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = ?::regclass AND i.indisprimary
                LIMIT 1
            ");
            $stmt->execute([$tableName]);
            $key = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($key && !empty($key['attname'])) {
                return $key['attname'];
            }
        } elseif ($driver === 'sqlite') {
            foreach ($columns as $column) {
                if (!empty($column['pk']) && $column['pk'] == 1) {
                    return $column['name'] ?? 'id';
                }
            }
        }
    } catch (PDOException $e) {
        // Ignoriere Fehler
    }
    
    // Fallback: erste Spalte oder 'id'
    if (!empty($columns)) {
        $firstCol = getColumnName($columns[0], $driver);
        if ($firstCol) {
            return $firstCol;
        }
    }
    return 'id';
}

// Hole Primary Key Spalte
$primaryKeyColumn = getPrimaryKeyColumn($conn, $tableName, $columns, $driver);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tableName); ?> - Datenbank-View</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="db-view-container">
        <header class="db-view-header">
            <div class="db-view-header-top">
                <a href="index.php" class="db-back-btn">← Zurück zu Tabellen</a>
                <h1><?php echo htmlspecialchars($tableName); ?></h1>
            </div>
            <div class="db-view-info">
                <span class="db-info-item">Zeilen: <strong><?php echo $totalRows; ?></strong></span>
                <span class="db-info-item">Spalten: <strong><?php echo count($columns); ?></strong></span>
                <?php if ($totalPages > 1): ?>
                    <span class="db-info-item">Seite: <strong><?php echo $page; ?> / <?php echo $totalPages; ?></strong></span>
                <?php endif; ?>
            </div>
        </header>

        <main class="db-view-main">
            <?php if ($error): ?>
                <div class="db-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (empty($columns)): ?>
                <div class="db-empty">Keine Spalten gefunden oder Tabelle existiert nicht.</div>
            <?php elseif (empty($data)): ?>
                <div class="db-empty">Diese Tabelle ist leer.</div>
            <?php else: ?>
                <div class="db-actions-bar">
                    <button class="db-btn db-btn-primary" onclick="showAddRowModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Neue Zeile hinzufügen
                    </button>
                </div>
                
                <div class="db-table-wrapper">
                    <table class="db-table">
                        <thead>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <th><?php echo htmlspecialchars(getColumnName($column, $driver)); ?></th>
                                <?php endforeach; ?>
                                <th class="db-actions-column">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): 
                                $rowId = $row[$primaryKeyColumn] ?? null;
                            ?>
                                <tr data-row-id="<?php echo htmlspecialchars($rowId); ?>">
                                    <?php foreach ($columns as $column): 
                                        $colName = getColumnName($column, $driver);
                                        $value = $row[$colName] ?? null;
                                    ?>
                                        <td data-column="<?php echo htmlspecialchars($colName); ?>" data-value="<?php echo htmlspecialchars($value ?? ''); ?>">
                                            <?php echo formatValue($value, $column); ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="db-actions-cell">
                                        <button class="db-btn db-btn-edit" onclick="editRow(<?php echo htmlspecialchars(json_encode($row)); ?>, '<?php echo htmlspecialchars($primaryKeyColumn); ?>')" title="Bearbeiten">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <button class="db-btn db-btn-delete" onclick="deleteRow('<?php echo htmlspecialchars($tableName); ?>', '<?php echo htmlspecialchars($rowId); ?>', '<?php echo htmlspecialchars($primaryKeyColumn); ?>')" title="Löschen">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="db-pagination">
                        <?php if ($page > 1): ?>
                            <a href="?table=<?php echo urlencode($tableName); ?>&page=<?php echo $page - 1; ?>" class="db-pagination-btn">← Vorherige</a>
                        <?php endif; ?>
                        
                        <span class="db-pagination-info">
                            Seite <?php echo $page; ?> von <?php echo $totalPages; ?>
                        </span>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?table=<?php echo urlencode($tableName); ?>&page=<?php echo $page + 1; ?>" class="db-pagination-btn">Nächste →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <?php 
    // Include SQL Executor Widget
    require_once __DIR__ . '/sql-executor-widget.php';
    ?>

    <!-- Edit/Add Modal -->
    <div id="dbEditModal" class="db-modal" style="display: none;">
        <div class="db-modal-content">
            <div class="db-modal-header">
                <h2 id="dbModalTitle">Zeile bearbeiten</h2>
                <button class="db-modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="dbEditForm" class="db-edit-form">
                <input type="hidden" id="dbEditId" name="id">
                <input type="hidden" id="dbEditIdColumn" name="id_column">
                <input type="hidden" name="table" value="<?php echo htmlspecialchars($tableName); ?>">
                <input type="hidden" name="action" id="dbEditAction" value="update">
                
                <div class="db-form-fields" id="dbFormFields">
                    <!-- Fields werden dynamisch eingefügt -->
                </div>
                
                <div class="db-modal-actions">
                    <button type="button" class="db-btn db-btn-secondary" onclick="closeEditModal()">Abbrechen</button>
                    <button type="submit" class="db-btn db-btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const tableName = <?php echo json_encode($tableName); ?>;
        const columns = <?php echo json_encode(array_map(function($col) use ($driver) {
            return [
                'name' => getColumnName($col, $driver),
                'type' => getColumnType($col, $driver),
                'nullable' => isColumnNullable($col, $driver)
            ];
        }, $columns)); ?>;
        const primaryKeyColumn = <?php echo json_encode($primaryKeyColumn); ?>;
        const driver = <?php echo json_encode($driver); ?>;

        function editRow(row, pkColumn) {
            document.getElementById('dbModalTitle').textContent = 'Zeile bearbeiten';
            document.getElementById('dbEditAction').value = 'update';
            document.getElementById('dbEditId').value = row[pkColumn] || '';
            document.getElementById('dbEditIdColumn').value = pkColumn;
            
            const formFields = document.getElementById('dbFormFields');
            formFields.innerHTML = '';
            
            columns.forEach(col => {
                if (col.name === pkColumn) {
                    // Primary Key nicht editierbar
                    const field = createFormField(col, row[col.name], true);
                    formFields.appendChild(field);
                } else {
                    const field = createFormField(col, row[col.name], false);
                    formFields.appendChild(field);
                }
            });
            
            document.getElementById('dbEditModal').style.display = 'flex';
        }

        function showAddRowModal() {
            document.getElementById('dbModalTitle').textContent = 'Neue Zeile hinzufügen';
            document.getElementById('dbEditAction').value = 'insert';
            document.getElementById('dbEditId').value = '';
            document.getElementById('dbEditIdColumn').value = primaryKeyColumn;
            
            const formFields = document.getElementById('dbFormFields');
            formFields.innerHTML = '';
            
            columns.forEach(col => {
                // Bei INSERT: Primary Key nur disabled wenn AUTO_INCREMENT (kann nicht sicher erkannt werden, daher optional)
                // Lassen wir den Benutzer entscheiden, ob er einen Wert eingibt
                const isAutoIncrement = col.name === primaryKeyColumn && (col.type.includes('auto_increment') || col.type.includes('serial'));
                const field = createFormField(col, '', isAutoIncrement);
                formFields.appendChild(field);
            });
            
            document.getElementById('dbEditModal').style.display = 'flex';
        }

        function createFormField(col, value, disabled) {
            const fieldWrapper = document.createElement('div');
            fieldWrapper.className = 'db-form-field';
            
            const label = document.createElement('label');
            label.textContent = col.name;
            label.setAttribute('for', 'field_' + col.name);
            if (!col.nullable && !disabled) {
                label.innerHTML += ' <span class="db-required">*</span>';
            }
            
            const input = createInputForType(col, value, disabled);
            input.id = 'field_' + col.name;
            input.name = 'data[' + col.name + ']';
            if (disabled) {
                input.disabled = true;
                input.style.backgroundColor = '#f5f5f5';
            }
            
            fieldWrapper.appendChild(label);
            fieldWrapper.appendChild(input);
            
            return fieldWrapper;
        }

        function createInputForType(col, value, disabled) {
            const type = col.type.toLowerCase();
            let input;
            
            if (type.includes('int') || type.includes('decimal') || type.includes('float') || type.includes('double')) {
                input = document.createElement('input');
                input.type = 'number';
                input.step = type.includes('decimal') || type.includes('float') ? '0.01' : '1';
            } else if (type.includes('date')) {
                input = document.createElement('input');
                input.type = 'date';
            } else if (type.includes('time')) {
                input = document.createElement('input');
                input.type = 'time';
            } else if (type.includes('timestamp') || type.includes('datetime')) {
                input = document.createElement('input');
                input.type = 'datetime-local';
            } else if (type.includes('text') || type.includes('longtext') || type.includes('mediumtext')) {
                input = document.createElement('textarea');
                input.rows = 4;
            } else if ((type.includes('tinyint') && (type.includes('1') || type === 'tinyint(1)')) || type === 'boolean' || type === 'bool') {
                // Boolean
                input = document.createElement('select');
                const optionNull = document.createElement('option');
                optionNull.value = '';
                optionNull.textContent = 'NULL';
                const option1 = document.createElement('option');
                option1.value = '0';
                option1.textContent = 'Nein (0)';
                const option2 = document.createElement('option');
                option2.value = '1';
                option2.textContent = 'Ja (1)';
                input.appendChild(optionNull);
                input.appendChild(option1);
                input.appendChild(option2);
            } else {
                input = document.createElement('input');
                input.type = 'text';
            }
            
            input.className = 'db-form-input';
            if (value !== null && value !== undefined) {
                if (input.type === 'datetime-local' && value) {
                    // Konvertiere MySQL datetime zu datetime-local Format
                    const date = new Date(value);
                    if (!isNaN(date.getTime())) {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        const hours = String(date.getHours()).padStart(2, '0');
                        const minutes = String(date.getMinutes()).padStart(2, '0');
                        input.value = `${year}-${month}-${day}T${hours}:${minutes}`;
                    }
                } else if (input.type === 'date' && value) {
                    const date = new Date(value);
                    if (!isNaN(date.getTime())) {
                        input.value = date.toISOString().split('T')[0];
                    }
                } else if (input.type === 'time' && value) {
                    const time = value.toString().substring(0, 5);
                    input.value = time;
                } else if (input.tagName === 'SELECT' && (input.querySelector('option[value="1"]') || input.querySelector('option[value="0"]'))) {
                    // Boolean select
                    if (value === null || value === undefined || value === '') {
                        input.value = '';
                    } else {
                        input.value = (value == 1 || value === '1' || value === true) ? '1' : '0';
                    }
                } else {
                    input.value = value !== null && value !== undefined ? value : '';
                }
            }
            
            return input;
        }

        function closeEditModal() {
            document.getElementById('dbEditModal').style.display = 'none';
            document.getElementById('dbEditForm').reset();
        }

        function deleteRow(table, id, idColumn) {
            if (!confirm('Möchten Sie diese Zeile wirklich löschen?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('table', table);
            formData.append('id', id);
            formData.append('id_column', idColumn);
            
            fetch('api-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Zeile erfolgreich gelöscht');
                    location.reload();
                } else {
                    alert('Fehler: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Löschen');
            });
        }

        document.getElementById('dbEditForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = document.getElementById('dbEditAction').value;
            formData.append('action', action);
            
            // Zeige Loading-State
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Speichere...';
            
            fetch('api-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(action === 'insert' ? 'Zeile erfolgreich hinzugefügt' : 'Zeile erfolgreich aktualisiert');
                    closeEditModal();
                    location.reload();
                } else {
                    alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Speichern: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });

        // Schließe Modal bei Klick außerhalb
        document.getElementById('dbEditModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>

