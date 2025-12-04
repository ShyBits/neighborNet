<?php
// Erstellt alle Tabellen, falls sie nicht existieren
// Datenbankunabhängige Version die mit verschiedenen DB-Engines funktioniert

require_once __DIR__ . '/db.php';

// Lade universelles Schema falls verfügbar (muss VOR den Funktionen geladen werden)
// Dies stellt sicher, dass die Funktionen aus universal-schema.php zuerst definiert werden
if (file_exists(__DIR__ . '/universal-schema.php')) {
    require_once __DIR__ . '/universal-schema.php';
}

/**
 * Zentrale Funktion zur Sicherstellung, dass alle Tabellen vorhanden sind
 * Diese Funktion sollte von allen APIs aufgerufen werden
 */
if (!function_exists('ensureDatabaseTables')) {
    function ensureDatabaseTables() {
        // Verwende ensureAllTables wenn verfügbar (aus universal-schema.php)
        if (function_exists('ensureAllTables')) {
            ensureAllTables();
        } elseif (function_exists('createTables')) {
            // Fallback auf createTables
            createTables();
        } else {
            // Letzter Fallback: Versuche ensure-schema.php zu laden
            if (file_exists(__DIR__ . '/ensure-schema.php')) {
                require_once __DIR__ . '/ensure-schema.php';
                if (function_exists('ensureDatabaseSchema')) {
                    ensureDatabaseSchema();
                }
            }
        }
    }
}

/**
 * Erkennt die Datenbank-Engine und gibt datenbank-spezifische SQL-Syntax zurück
 * Wird nur definiert, falls nicht bereits in universal-schema.php definiert
 */
if (!function_exists('getDBSpecificSQL')) {
function getDBSpecificSQL($conn) {
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    $dbInfo = [
        'driver' => $driver,
        'auto_increment' => 'AUTO_INCREMENT',
        'engine' => '',
        'charset' => '',
        'timestamp_update' => 'ON UPDATE CURRENT_TIMESTAMP',
        'supports_foreign_keys' => true,
        'enum_type' => 'VARCHAR'
    ];
    
    switch ($driver) {
        case 'mysql':
            $dbInfo['auto_increment'] = 'INT AUTO_INCREMENT';
            $dbInfo['engine'] = 'ENGINE=InnoDB';
            $dbInfo['charset'] = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $dbInfo['timestamp_update'] = 'ON UPDATE CURRENT_TIMESTAMP';
            $dbInfo['enum_type'] = "VARCHAR(20)"; // Verwende VARCHAR statt ENUM für Kompatibilität
            $dbInfo['boolean_type'] = 'TINYINT(1)';
            break;
        case 'pgsql':
            $dbInfo['auto_increment'] = 'SERIAL'; // SERIAL ist ein eigener Datentyp in PostgreSQL
            $dbInfo['engine'] = '';
            $dbInfo['charset'] = '';
            $dbInfo['timestamp_update'] = ''; // PostgreSQL verwendet Triggers
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'BOOLEAN';
            break;
        case 'sqlite':
            $dbInfo['auto_increment'] = 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $dbInfo['engine'] = '';
            $dbInfo['charset'] = '';
            $dbInfo['timestamp_update'] = '';
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'INTEGER';
            $dbInfo['supports_foreign_keys'] = false; // SQLite hat eingeschränkte FK-Unterstützung
            break;
        default:
            // Fallback für unbekannte DBs
            $dbInfo['auto_increment'] = 'INT AUTO_INCREMENT';
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'TINYINT(1)';
    }
    
    return $dbInfo;
}
} // end if !function_exists('getDBSpecificSQL')

/**
 * Prüft ob eine Spalte in einer Tabelle existiert
 * Wird nur definiert, falls nicht bereits in universal-schema.php definiert
 */
if (!function_exists('columnExists')) {
function columnExists($conn, $table, $column) {
    try {
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'mysql') {
            $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
            return $stmt->rowCount() > 0;
        } elseif ($driver === 'pgsql') {
            $stmt = $conn->prepare("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = ? AND column_name = ?
            ");
            $stmt->execute([$table, $column]);
            return $stmt->rowCount() > 0;
        } elseif ($driver === 'sqlite') {
            $stmt = $conn->query("PRAGMA table_info({$table})");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        } else {
            // Fallback: Versuche SELECT mit der Spalte
            try {
                $conn->query("SELECT `{$column}` FROM `{$table}` LIMIT 1");
                return true;
            } catch(PDOException $e) {
                return false;
            }
        }
    } catch(PDOException $e) {
        return false;
    }
}
} // end if !function_exists('columnExists')

/**
 * Prüft ob ein Index existiert
 * Wird nur definiert, falls nicht bereits in universal-schema.php definiert
 */
if (!function_exists('indexExists')) {
function indexExists($conn, $table, $indexName) {
    try {
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'mysql') {
            $stmt = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
            return $stmt->rowCount() > 0;
        } elseif ($driver === 'pgsql') {
            $stmt = $conn->prepare("
                SELECT indexname 
                FROM pg_indexes 
                WHERE tablename = ? AND indexname = ?
            ");
            $stmt->execute([$table, $indexName]);
            return $stmt->rowCount() > 0;
        } elseif ($driver === 'sqlite') {
            $stmt = $conn->query("PRAGMA index_list({$table})");
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($indexes as $idx) {
                if ($idx['name'] === $indexName) {
                    return true;
                }
            }
            return false;
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}
} // end if !function_exists('indexExists')

if (!function_exists('createTables')) {
function createTables() {
    // Verwende universelles Schema falls verfügbar (prioritär)
    if (function_exists('ensureAllTables')) {
        return ensureAllTables();
    }
    
    // Fallback: Alte Implementierung wenn universal-schema.php nicht verfügbar ist
    
    $conn = getDBConnection();
    $dbInfo = getDBSpecificSQL($conn);
    
    // Basis-Tabellen ohne Foreign Keys (werden später hinzugefügt falls unterstützt)
    // Für PostgreSQL: SERIAL ist bereits ein Datentyp
    // Für MySQL: INT AUTO_INCREMENT ist bereits vollständig
    // Für SQLite: INTEGER PRIMARY KEY AUTOINCREMENT enthält bereits PRIMARY KEY
    $idColumn = $dbInfo['auto_increment'];
    // Für SQLite: PRIMARY KEY ist Teil von INTEGER PRIMARY KEY AUTOINCREMENT
    $primaryKeySuffix = ($dbInfo['driver'] === 'sqlite') ? '' : ' PRIMARY KEY';
    
    // Backticks werden von MySQL benötigt, andere DBs ignorieren sie normalerweise
    $baseTables = [
        "users" => "CREATE TABLE IF NOT EXISTS `users` (
            `id` {$idColumn}{$primaryKeySuffix},
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `first_name` VARCHAR(100) DEFAULT NULL,
            `last_name` VARCHAR(100) DEFAULT NULL,
            `street` VARCHAR(255) DEFAULT NULL,
            `house_number` VARCHAR(20) DEFAULT NULL,
            `postcode` VARCHAR(20) DEFAULT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `avatar` VARCHAR(255) DEFAULT NULL,
            `completed_helps` INT DEFAULT 0,
            `encryption_public_key` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "angebote" => "CREATE TABLE IF NOT EXISTS `angebote` (
            `id` {$idColumn}{$primaryKeySuffix},
            `user_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `category` VARCHAR(50) NOT NULL,
            `start_date` DATE NOT NULL,
            `end_date` DATE NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `address` VARCHAR(500) NOT NULL,
            `lat` DECIMAL(10, 8) NOT NULL,
            `lng` DECIMAL(11, 8) NOT NULL,
            `required_persons` INT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "angebote_images" => "CREATE TABLE IF NOT EXISTS `angebote_images` (
            `id` {$idColumn}{$primaryKeySuffix},
            `angebot_id` INT NOT NULL,
            `image_path` VARCHAR(500) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "messages" => "CREATE TABLE IF NOT EXISTS `messages` (
            `id` {$idColumn}{$primaryKeySuffix},
            `chat_id` INT NULL,
            `sender_id` INT NOT NULL,
            `receiver_id` INT NOT NULL,
            `message` " . ($dbInfo['driver'] === 'mysql' ? 'LONGTEXT' : 'TEXT') . " NOT NULL,
            `encrypted` {$dbInfo['boolean_type']} DEFAULT 0,
            `file_path` VARCHAR(500) NULL,
            `file_type` VARCHAR(20) NULL,
            `read_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "anfragen" => "CREATE TABLE IF NOT EXISTS `anfragen` (
            `id` {$idColumn}{$primaryKeySuffix},
            `angebot_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `message` TEXT DEFAULT NULL,
            `status` {$dbInfo['enum_type']} DEFAULT 'pending',
            `confirmed_at` TIMESTAMP NULL DEFAULT NULL,
            `completed_by_helper` TIMESTAMP NULL DEFAULT NULL,
            `completed_by_requester` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP {$dbInfo['timestamp_update']},
            UNIQUE KEY `unique_anfrage` (`angebot_id`, `user_id`)
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "chats" => "CREATE TABLE IF NOT EXISTS `chats` (
            `id` {$idColumn}{$primaryKeySuffix},
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP {$dbInfo['timestamp_update']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "chat_participants" => "CREATE TABLE IF NOT EXISTS `chat_participants` (
            `id` {$idColumn}{$primaryKeySuffix},
            `chat_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `last_read_message_id` INT DEFAULT NULL,
            `last_read_at` TIMESTAMP NULL DEFAULT NULL,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_participant` (`chat_id`, `user_id`)
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "chat_metadata" => "CREATE TABLE IF NOT EXISTS `chat_metadata` (
            `chat_id` INT NOT NULL PRIMARY KEY,
            `last_message_id` INT NULL,
            `last_message_at` TIMESTAMP NULL,
            `unread_count_user_1` INT DEFAULT 0,
            `unread_count_user_2` INT DEFAULT 0
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "chat_requests" => "CREATE TABLE IF NOT EXISTS `chat_requests` (
            `id` {$idColumn}{$primaryKeySuffix},
            `anfrage_id` INT NOT NULL,
            `chat_id` INT NULL,
            `requester_id` INT NOT NULL,
            `helper_id` INT NOT NULL,
            `status` {$dbInfo['enum_type']} DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP {$dbInfo['timestamp_update']},
            UNIQUE KEY `unique_anfrage_chat` (`anfrage_id`)
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "user_activity" => "CREATE TABLE IF NOT EXISTS `user_activity` (
            `user_id` INT NOT NULL PRIMARY KEY,
            `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP {$dbInfo['timestamp_update']}" . 
            ($dbInfo['driver'] === 'mysql' ? ", INDEX `idx_last_activity` (`last_activity`)" : "") . "
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        "chat_favorites" => "CREATE TABLE IF NOT EXISTS `chat_favorites` (
            `id` {$idColumn}{$primaryKeySuffix},
            `user_id` INT NOT NULL,
            `contact_user_id` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP" .
            ($dbInfo['driver'] === 'mysql' ? ",
            UNIQUE KEY `unique_favorite` (`user_id`, `contact_user_id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_contact_user_id` (`contact_user_id`)" : "") . "
        ) {$dbInfo['engine']} {$dbInfo['charset']}"
    ];
    
    // Erstelle Basis-Tabellen
    foreach ($baseTables as $tableName => $sql) {
        try {
            $conn->exec($sql);
            
            // Für chat_favorites: Erstelle Indizes separat für nicht-MySQL Datenbanken
            if ($tableName === 'chat_favorites' && $dbInfo['driver'] !== 'mysql') {
                try {
                    if ($dbInfo['driver'] === 'pgsql') {
                        $conn->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_favorite ON chat_favorites(user_id, contact_user_id)");
                        $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON chat_favorites(user_id)");
                        $conn->exec("CREATE INDEX IF NOT EXISTS idx_contact_user_id ON chat_favorites(contact_user_id)");
                    } elseif ($dbInfo['driver'] === 'sqlite') {
                        // Check if indexes exist
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
                    }
                } catch(PDOException $e) {
                    // Ignoriere Fehler wenn Index bereits existiert
                    error_log("Fehler beim Erstellen der Indizes für {$tableName}: " . $e->getMessage());
                }
            }
        } catch(PDOException $e) {
            // Ignoriere Fehler wenn Tabelle bereits existiert
            error_log("Fehler beim Erstellen der Tabelle {$tableName}: " . $e->getMessage());
        }
    }
    
    // Create index for user_activity.last_activity for non-MySQL databases
    if ($dbInfo['driver'] !== 'mysql') {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'pgsql') {
                $conn->exec("CREATE INDEX IF NOT EXISTS idx_user_activity_last_activity ON user_activity(last_activity)");
            } elseif ($driver === 'sqlite') {
                // Check if index exists
                $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_user_activity_last_activity'");
                if ($stmt->rowCount() === 0) {
                    $conn->exec("CREATE INDEX idx_user_activity_last_activity ON user_activity(last_activity)");
                }
            }
        } catch(PDOException $e) {
            // Ignore errors if index already exists
            error_log("Fehler beim Erstellen des Index für user_activity: " . $e->getMessage());
        }
    }
    
    // Füge Foreign Keys hinzu (nur wenn unterstützt und nachdem alle Tabellen erstellt sind)
    if ($dbInfo['supports_foreign_keys']) {
        $foreignKeys = [
            ['angebote', 'user_id', 'users', 'id', 'fk_angebote_user', 'CASCADE'],
            ['angebote_images', 'angebot_id', 'angebote', 'id', 'fk_angebote_images_angebot', 'CASCADE'],
            ['messages', 'sender_id', 'users', 'id', 'fk_messages_sender', 'CASCADE'],
            ['messages', 'receiver_id', 'users', 'id', 'fk_messages_receiver', 'CASCADE'],
            ['messages', 'chat_id', 'chats', 'id', 'fk_messages_chat', 'CASCADE'],
            ['anfragen', 'angebot_id', 'angebote', 'id', 'fk_anfragen_angebot', 'CASCADE'],
            ['anfragen', 'user_id', 'users', 'id', 'fk_anfragen_user', 'CASCADE'],
            ['chat_participants', 'chat_id', 'chats', 'id', 'fk_chat_participants_chat', 'CASCADE'],
            ['chat_participants', 'user_id', 'users', 'id', 'fk_chat_participants_user', 'CASCADE'],
            ['chat_metadata', 'chat_id', 'chats', 'id', 'fk_chat_metadata_chat', 'CASCADE'],
            ['chat_requests', 'anfrage_id', 'anfragen', 'id', 'fk_chat_requests_anfrage', 'CASCADE'],
            ['chat_requests', 'chat_id', 'chats', 'id', 'fk_chat_requests_chat', 'SET NULL'],
            ['chat_requests', 'requester_id', 'users', 'id', 'fk_chat_requests_requester', 'CASCADE'],
            ['chat_requests', 'helper_id', 'users', 'id', 'fk_chat_requests_helper', 'CASCADE'],
            ['user_activity', 'user_id', 'users', 'id', 'fk_user_activity_user', 'CASCADE']
        ];
        
        foreach ($foreignKeys as $fk) {
            try {
                // Prüfe ob Foreign Key bereits existiert
                $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                $fkExists = false;
                
                if ($driver === 'mysql') {
                    $stmt = $conn->query("
                        SELECT CONSTRAINT_NAME 
                        FROM information_schema.KEY_COLUMN_USAGE 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = '{$fk[0]}' 
                        AND CONSTRAINT_NAME = '{$fk[4]}'
                    ");
                    $fkExists = $stmt->rowCount() > 0;
                } elseif ($driver === 'pgsql') {
                    $stmt = $conn->prepare("
                        SELECT constraint_name 
                        FROM information_schema.table_constraints 
                        WHERE table_name = ? AND constraint_name = ?
                    ");
                    $stmt->execute([$fk[0], $fk[4]]);
                    $fkExists = $stmt->rowCount() > 0;
                }
                
                if (!$fkExists) {
                    $fkSql = "ALTER TABLE `{$fk[0]}` ADD CONSTRAINT `{$fk[4]}` FOREIGN KEY (`{$fk[1]}`) REFERENCES `{$fk[2]}`(`{$fk[3]}`) ON DELETE {$fk[5]}";
                    $conn->exec($fkSql);
                }
            } catch(PDOException $e) {
                // Ignoriere Fehler wenn Foreign Key bereits existiert oder nicht unterstützt wird
            }
        }
    }
    
    // Füge zusätzliche Spalten hinzu falls sie noch nicht existieren
    $columnsToAdd = [
        'users' => [
            ['completed_helps', 'INT DEFAULT 0'],
            ['encryption_public_key', 'TEXT NULL'],
            ['first_name', 'VARCHAR(100) DEFAULT NULL'],
            ['last_name', 'VARCHAR(100) DEFAULT NULL'],
            ['street', 'VARCHAR(255) DEFAULT NULL'],
            ['house_number', 'VARCHAR(20) DEFAULT NULL'],
            ['postcode', 'VARCHAR(20) DEFAULT NULL'],
            ['city', 'VARCHAR(100) DEFAULT NULL'],
            ['avatar', 'VARCHAR(255) DEFAULT NULL']
        ],
        'angebote' => [
            ['required_persons', 'INT DEFAULT 1']
        ],
        'messages' => [
            ['message', ($dbInfo['driver'] === 'mysql' ? 'LONGTEXT' : 'TEXT') . ' NULL'],
            ['chat_id', 'INT NULL'],
            ['encrypted', "{$dbInfo['boolean_type']} DEFAULT 0"],
            ['file_path', 'VARCHAR(500) NULL'],
            ['file_type', 'VARCHAR(20) NULL'],
            ['read_at', 'TIMESTAMP NULL DEFAULT NULL']
        ],
        'anfragen' => [
            ['message', 'TEXT DEFAULT NULL'],
            ['status', "{$dbInfo['enum_type']} DEFAULT 'pending'"],
            ['confirmed_at', 'TIMESTAMP NULL DEFAULT NULL'],
            ['completed_by_helper', 'TIMESTAMP NULL DEFAULT NULL'],
            ['completed_by_requester', 'TIMESTAMP NULL DEFAULT NULL'],
            ['updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP {$dbInfo['timestamp_update']}"]
        ]
    ];
    
    foreach ($columnsToAdd as $table => $columns) {
        foreach ($columns as $column) {
            if (!columnExists($conn, $table, $column[0])) {
                try {
                    $conn->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column[0]}` {$column[1]}");
                } catch(PDOException $e) {
                    // Ignoriere Fehler
                }
            }
        }
    }
    
    // Modify existing columns if needed (migrations)
    // Upgrade message column from TEXT to LONGTEXT to support base64 image data
    if (columnExists($conn, 'messages', 'message')) {
        try {
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            
            if ($driver === 'mysql') {
                // MySQL: Upgrade TEXT to LONGTEXT to support large base64 image data
                // This will fail silently if already LONGTEXT, which is fine
                $conn->exec("ALTER TABLE `messages` MODIFY COLUMN `message` LONGTEXT NOT NULL");
            } elseif ($driver === 'pgsql') {
                // PostgreSQL TEXT can already hold large data, but ensure it's not VARCHAR
                $stmt = $conn->prepare("
                    SELECT data_type 
                    FROM information_schema.columns 
                    WHERE table_name = 'messages' AND column_name = 'message'
                ");
                $stmt->execute();
                $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($colInfo && $colInfo['data_type'] === 'character varying') {
                    $conn->exec("ALTER TABLE `messages` ALTER COLUMN `message` TYPE TEXT");
                }
            }
            // SQLite TEXT can already hold large data (up to 2GB), no modification needed
        } catch(PDOException $e) {
            // Ignoriere Fehler (z.B. wenn Spalte bereits den richtigen Typ hat)
            // oder wenn die Modifikation aus anderen Gründen fehlschlägt
        }
    }
    
    // Wenn message-Spalte als NULL hinzugefügt wurde, ändere sie zu NOT NULL wenn Tabelle leer ist
    if (columnExists($conn, 'messages', 'message')) {
        try {
            // Prüfe ob Tabelle leer ist
            $stmt = $conn->query("SELECT COUNT(*) as count FROM `messages`");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $isEmpty = ($result['count'] == 0);
            
            // Prüfe ob Spalte NULL erlaubt
            $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            $isNullable = false;
            
            if ($driver === 'mysql') {
                $stmt = $conn->query("SHOW COLUMNS FROM `messages` WHERE Field = 'message'");
                $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $isNullable = ($colInfo && $colInfo['Null'] === 'YES');
            } elseif ($driver === 'pgsql') {
                $stmt = $conn->prepare("
                    SELECT is_nullable 
                    FROM information_schema.columns 
                    WHERE table_name = 'messages' AND column_name = 'message'
                ");
                $stmt->execute();
                $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                $isNullable = ($colInfo && $colInfo['is_nullable'] === 'YES');
            }
            
            // Wenn Tabelle leer ist und Spalte NULL erlaubt, ändere zu NOT NULL
            if ($isEmpty && $isNullable) {
                if ($driver === 'mysql') {
                    $conn->exec("ALTER TABLE `messages` MODIFY COLUMN `message` LONGTEXT NOT NULL");
                } elseif ($driver === 'pgsql') {
                    $conn->exec("ALTER TABLE `messages` ALTER COLUMN `message` SET NOT NULL");
                }
            }
        } catch(PDOException $e) {
            // Ignoriere Fehler
        }
    }
    
    // Füge Indizes hinzu für bessere Performance
    $indexesToAdd = [
        'anfragen' => [
            ['idx_status', '(`status`)'],
            ['idx_angebot_status', '(`angebot_id`, `status`)'],
            ['idx_user_id', '(`user_id`)'],
            ['idx_created_at', '(`created_at`)']
        ],
        'messages' => [
            ['idx_chat_id', '(`chat_id`)'],
            ['idx_sender_receiver', '(`sender_id`, `receiver_id`, `created_at`)'],
            ['idx_receiver_sender', '(`receiver_id`, `sender_id`, `created_at`)'],
            ['idx_created_at', '(`created_at`)'],
            ['idx_read_at', '(`read_at`)']
        ],
        'chats' => [
            ['idx_updated_at', '(`updated_at`)']
        ],
        'chat_participants' => [
            ['idx_user_chat', '(`user_id`, `chat_id`)'],
            ['idx_chat_user', '(`chat_id`, `user_id`)']
        ],
        'chat_metadata' => [
            ['idx_last_message_at', '(`last_message_at`)']
        ],
        'chat_requests' => [
            ['idx_status', '(`status`)'],
            ['idx_requester', '(`requester_id`)'],
            ['idx_helper', '(`helper_id`)']
        ],
        'angebote' => [
            ['idx_user_id', '(`user_id`)'],
            ['idx_created_at', '(`created_at`)']
        ],
        'angebote_images' => [
            ['idx_angebot_id', '(`angebot_id`)']
        ]
    ];
    
    foreach ($indexesToAdd as $table => $indexes) {
        foreach ($indexes as $index) {
            if (!indexExists($conn, $table, $index[0])) {
                try {
                    $conn->exec("ALTER TABLE `{$table}` ADD INDEX `{$index[0]}` {$index[1]}");
                } catch(PDOException $e) {
                    // Ignoriere Fehler wenn Index bereits existiert
                }
            }
        }
    }
}
} // end if !function_exists('createTables')

// Nur ausführen wenn direkt aufgerufen (nicht wenn eingebunden)
if (basename($_SERVER['PHP_SELF']) === 'create-tables.php') {
    if (function_exists('createTables')) {
        createTables();
        echo "Alle Tabellen wurden erstellt/überprüft!";
    }
} else {
    // Stille Ausführung wenn eingebunden
    if (function_exists('createTables')) {
        createTables();
    }
}
?>

