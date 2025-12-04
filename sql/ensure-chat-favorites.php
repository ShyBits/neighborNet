<?php
/**
 * Sicherstellt, dass die chat_favorites Tabelle existiert
 * Kann direkt aufgerufen werden, um die Tabelle zu erstellen
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/create-tables.php';

// Lade universelles Schema falls verfügbar
if (file_exists(__DIR__ . '/universal-schema.php')) {
    require_once __DIR__ . '/universal-schema.php';
}

$conn = getDBConnection();
$dbInfo = getDBSpecificSQL($conn);
$q = $dbInfo['quote_char'];
$idColumn = $dbInfo['auto_increment'];
$primaryKeySuffix = ($dbInfo['driver'] === 'sqlite') ? '' : ' PRIMARY KEY';

try {
    // Prüfe ob Tabelle existiert
    $tableExists = false;
    if ($dbInfo['driver'] === 'mysql') {
        $stmt = $conn->query("SHOW TABLES LIKE 'chat_favorites'");
        $tableExists = $stmt->rowCount() > 0;
    } elseif ($dbInfo['driver'] === 'pgsql') {
        $stmt = $conn->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'chat_favorites')");
        $tableExists = $stmt->fetchColumn();
    } elseif ($dbInfo['driver'] === 'sqlite') {
        $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='chat_favorites'");
        $tableExists = $stmt->rowCount() > 0;
    }
    
    if (!$tableExists) {
        // Erstelle Tabelle
        $sql = "CREATE TABLE IF NOT EXISTS {$q}chat_favorites{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}user_id{$q} INT NOT NULL,
            {$q}contact_user_id{$q} INT NOT NULL,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}" .
            ($dbInfo['driver'] === 'mysql' ? ",
            UNIQUE KEY {$q}unique_favorite{$q} ({$q}user_id{$q}, {$q}contact_user_id{$q}),
            INDEX {$q}idx_user_id{$q} ({$q}user_id{$q}),
            INDEX {$q}idx_contact_user_id{$q} ({$q}contact_user_id{$q})" : "") . "
        ) {$dbInfo['engine']} {$dbInfo['charset']}";
        
        $conn->exec($sql);
        echo "Tabelle chat_favorites wurde erstellt.\n";
        
        // Erstelle Indizes für nicht-MySQL Datenbanken
        if ($dbInfo['driver'] !== 'mysql') {
            try {
                if ($dbInfo['driver'] === 'pgsql') {
                    $conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_favorite ON chat_favorites(user_id, contact_user_id)");
                    $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON chat_favorites(user_id)");
                    $conn->exec("CREATE INDEX IF NOT EXISTS idx_contact_user_id ON chat_favorites(contact_user_id)");
                    echo "Indizes für chat_favorites wurden erstellt.\n";
                } elseif ($dbInfo['driver'] === 'sqlite') {
                    $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='unique_favorite'");
                    if ($stmt->rowCount() === 0) {
                        $conn->exec("CREATE UNIQUE INDEX unique_favorite ON chat_favorites(user_id, contact_user_id)");
                    }
                    $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_user_id'");
                    if ($stmt->rowCount() === 0) {
                        $conn->exec("CREATE INDEX idx_user_id ON chat_favorites(user_id)");
                    }
                    $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_contact_user_id'");
                    if ($stmt->rowCount() === 0) {
                        $conn->exec("CREATE INDEX idx_contact_user_id ON chat_favorites(contact_user_id)");
                    }
                    echo "Indizes für chat_favorites wurden erstellt.\n";
                }
            } catch(PDOException $e) {
                echo "Warnung beim Erstellen der Indizes: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "Tabelle chat_favorites existiert bereits.\n";
        
        // Prüfe ob Indizes existieren (für nicht-MySQL)
        if ($dbInfo['driver'] !== 'mysql') {
            $indexesOk = true;
            if ($dbInfo['driver'] === 'pgsql') {
                $stmt = $conn->query("SELECT indexname FROM pg_indexes WHERE tablename = 'chat_favorites' AND indexname = 'unique_favorite'");
                if ($stmt->rowCount() === 0) {
                    $indexesOk = false;
                }
            } elseif ($dbInfo['driver'] === 'sqlite') {
                $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='unique_favorite'");
                if ($stmt->rowCount() === 0) {
                    $indexesOk = false;
                }
            }
            
            if (!$indexesOk) {
                echo "Indizes fehlen, erstelle sie...\n";
                if ($dbInfo['driver'] === 'pgsql') {
                    $conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_favorite ON chat_favorites(user_id, contact_user_id)");
                    $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON chat_favorites(user_id)");
                    $conn->exec("CREATE INDEX IF NOT EXISTS idx_contact_user_id ON chat_favorites(contact_user_id)");
                } elseif ($dbInfo['driver'] === 'sqlite') {
                    $conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_favorite ON chat_favorites(user_id, contact_user_id)");
                    $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON chat_favorites(user_id)");
                    $conn->exec("CREATE INDEX IF NOT EXISTS idx_contact_user_id ON chat_favorites(contact_user_id)");
                }
                echo "Indizes wurden erstellt.\n";
            } else {
                echo "Indizes sind vorhanden.\n";
            }
        }
    }
    
    // Prüfe ob Spalten korrekt sind
    $columnsOk = true;
    if ($dbInfo['driver'] === 'mysql') {
        $stmt = $conn->query("SHOW COLUMNS FROM chat_favorites");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['id', 'user_id', 'contact_user_id', 'created_at'];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $columns)) {
                $columnsOk = false;
                break;
            }
        }
    }
    
    if ($columnsOk) {
        echo "Alle Spalten sind vorhanden.\n";
    } else {
        echo "Warnung: Einige Spalten fehlen möglicherweise.\n";
    }
    
    echo "\nTabelle chat_favorites ist bereit!\n";
    
} catch(PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
    exit(1);
}
?>

