<?php
/**
 * Universal Database Schema Creator
 * Erstellt alle benötigten Tabellen für NeighborNet
 * Funktioniert mit MySQL, PostgreSQL und SQLite
 * 
 * Verwendung: php sql/universal-schema.php
 * Oder: require_once 'sql/universal-schema.php'; ensureAllTables();
 */

require_once __DIR__ . '/db.php';

/**
 * Erkennt die Datenbank-Engine und gibt datenbank-spezifische SQL-Syntax zurück
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
        'enum_type' => 'VARCHAR(20)',
        'boolean_type' => 'TINYINT(1)',
        'text_type' => 'TEXT',
        'longtext_type' => 'LONGTEXT',
        'quote_char' => '`',
        'now_function' => 'NOW()',
        'current_timestamp' => 'CURRENT_TIMESTAMP'
    ];
    
    switch ($driver) {
        case 'mysql':
            $dbInfo['auto_increment'] = 'INT AUTO_INCREMENT';
            $dbInfo['engine'] = 'ENGINE=InnoDB';
            $dbInfo['charset'] = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $dbInfo['timestamp_update'] = 'ON UPDATE CURRENT_TIMESTAMP';
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'TINYINT(1)';
            $dbInfo['text_type'] = 'TEXT';
            $dbInfo['longtext_type'] = 'LONGTEXT';
            $dbInfo['quote_char'] = '`';
            $dbInfo['now_function'] = 'NOW()';
            break;
        case 'pgsql':
            $dbInfo['auto_increment'] = 'SERIAL';
            $dbInfo['engine'] = '';
            $dbInfo['charset'] = '';
            $dbInfo['timestamp_update'] = ''; // PostgreSQL verwendet Triggers
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'BOOLEAN';
            $dbInfo['text_type'] = 'TEXT';
            $dbInfo['longtext_type'] = 'TEXT'; // PostgreSQL TEXT kann sehr groß sein
            $dbInfo['quote_char'] = '"';
            $dbInfo['now_function'] = 'NOW()';
            break;
        case 'sqlite':
            $dbInfo['auto_increment'] = 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $dbInfo['engine'] = '';
            $dbInfo['charset'] = '';
            $dbInfo['timestamp_update'] = '';
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'INTEGER';
            $dbInfo['text_type'] = 'TEXT';
            $dbInfo['longtext_type'] = 'TEXT';
            $dbInfo['quote_char'] = '';
            $dbInfo['now_function'] = "datetime('now')";
            $dbInfo['current_timestamp'] = "datetime('now')";
            $dbInfo['supports_foreign_keys'] = true; // SQLite 3.6.19+ unterstützt FKs (muss aktiviert werden)
            break;
        default:
            // Fallback
            $dbInfo['auto_increment'] = 'INT AUTO_INCREMENT';
            $dbInfo['enum_type'] = 'VARCHAR(20)';
            $dbInfo['boolean_type'] = 'TINYINT(1)';
            $dbInfo['quote_char'] = '`';
    }
    
    return $dbInfo;
}
} // end if !function_exists('getDBSpecificSQL')

/**
 * Prüft ob eine Tabelle existiert
 */
if (!function_exists('tableExists')) {
function tableExists($conn, $tableName) {
    try {
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        if ($driver === 'mysql') {
            $stmt = $conn->query("SHOW TABLES LIKE '{$tableName}'");
            return $stmt->rowCount() > 0;
        } elseif ($driver === 'pgsql') {
            $stmt = $conn->prepare("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = 'public' AND table_name = ?
            ");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } elseif ($driver === 'sqlite') {
            $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$tableName}'");
            return $stmt->rowCount() > 0;
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}
} // end if !function_exists('tableExists')

/**
 * Prüft ob eine Spalte existiert
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
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}
} // end if !function_exists('columnExists')

/**
 * Prüft ob ein Index existiert
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

/**
 * Erstellt alle benötigten Tabellen für NeighborNet
 * Garantiert konsistente Struktur über alle Datenbanken hinweg
 */
if (!function_exists('ensureAllTables')) {
function ensureAllTables() {
    $conn = getDBConnection();
    $dbInfo = getDBSpecificSQL($conn);
    $q = $dbInfo['quote_char'];
    
    // Aktiviere Foreign Keys für SQLite
    if ($dbInfo['driver'] === 'sqlite') {
        try {
            $conn->exec('PRAGMA foreign_keys = ON');
        } catch(PDOException $e) {
            // Ignoriere Fehler
        }
    }
    
    // ID-Spalte basierend auf Datenbank
    $idColumn = $dbInfo['auto_increment'];
    $primaryKeySuffix = ($dbInfo['driver'] === 'sqlite') ? '' : ' PRIMARY KEY';
    
    // Tabellendefinitionen - in der richtigen Reihenfolge (abhängige Tabellen zuletzt)
    $tables = [
        // 1. users - Basis-Tabelle für alle anderen
        'users' => "CREATE TABLE IF NOT EXISTS {$q}users{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}username{$q} VARCHAR(100) NOT NULL UNIQUE,
            {$q}email{$q} VARCHAR(255) NOT NULL UNIQUE,
            {$q}password{$q} VARCHAR(255) NOT NULL,
            {$q}first_name{$q} VARCHAR(100) DEFAULT NULL,
            {$q}last_name{$q} VARCHAR(100) DEFAULT NULL,
            {$q}street{$q} VARCHAR(255) DEFAULT NULL,
            {$q}house_number{$q} VARCHAR(20) DEFAULT NULL,
            {$q}postcode{$q} VARCHAR(20) DEFAULT NULL,
            {$q}city{$q} VARCHAR(100) DEFAULT NULL,
            {$q}avatar{$q} VARCHAR(255) DEFAULT NULL,
            {$q}completed_helps{$q} INT DEFAULT 0,
            {$q}encryption_public_key{$q} TEXT NULL,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 2. angebote - Angebote/Anzeigen
        'angebote' => "CREATE TABLE IF NOT EXISTS {$q}angebote{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}user_id{$q} INT NOT NULL,
            {$q}title{$q} VARCHAR(255) NOT NULL,
            {$q}description{$q} {$dbInfo['text_type']} NOT NULL,
            {$q}category{$q} VARCHAR(50) NOT NULL,
            {$q}start_date{$q} DATE NOT NULL,
            {$q}end_date{$q} DATE NOT NULL,
            {$q}start_time{$q} TIME NOT NULL,
            {$q}end_time{$q} TIME NOT NULL,
            {$q}address{$q} VARCHAR(500) NOT NULL,
            {$q}lat{$q} DECIMAL(10, 8) NOT NULL,
            {$q}lng{$q} DECIMAL(11, 8) NOT NULL,
            {$q}required_persons{$q} INT DEFAULT 1,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 3. angebote_images - Bilder zu Angeboten
        'angebote_images' => "CREATE TABLE IF NOT EXISTS {$q}angebote_images{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}angebot_id{$q} INT NOT NULL,
            {$q}image_path{$q} VARCHAR(500) NOT NULL,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 4. chats - Chat-Gruppen
        'chats' => "CREATE TABLE IF NOT EXISTS {$q}chats{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']},
            {$q}updated_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']} {$dbInfo['timestamp_update']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 5. chat_participants - Chat-Teilnehmer
        'chat_participants' => "CREATE TABLE IF NOT EXISTS {$q}chat_participants{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}chat_id{$q} INT NOT NULL,
            {$q}user_id{$q} INT NOT NULL,
            {$q}last_read_message_id{$q} INT DEFAULT NULL,
            {$q}last_read_at{$q} TIMESTAMP NULL DEFAULT NULL,
            {$q}joined_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']},
            UNIQUE({$q}chat_id{$q}, {$q}user_id{$q})
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 6. chat_metadata - Chat-Metadaten
        'chat_metadata' => "CREATE TABLE IF NOT EXISTS {$q}chat_metadata{$q} (
            {$q}chat_id{$q} INT NOT NULL PRIMARY KEY,
            {$q}last_message_id{$q} INT NULL,
            {$q}last_message_at{$q} TIMESTAMP NULL,
            {$q}unread_count_user_1{$q} INT DEFAULT 0,
            {$q}unread_count_user_2{$q} INT DEFAULT 0
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 7. messages - Chat-Nachrichten
        'messages' => "CREATE TABLE IF NOT EXISTS {$q}messages{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}chat_id{$q} INT NULL,
            {$q}sender_id{$q} INT NOT NULL,
            {$q}receiver_id{$q} INT NOT NULL,
            {$q}message{$q} {$dbInfo['longtext_type']} NOT NULL,
            {$q}encrypted{$q} {$dbInfo['boolean_type']} DEFAULT 0,
            {$q}file_path{$q} VARCHAR(500) NULL,
            {$q}file_type{$q} VARCHAR(20) NULL,
            {$q}read_at{$q} TIMESTAMP NULL DEFAULT NULL,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 8. anfragen - Anfragen auf Angebote
        'anfragen' => "CREATE TABLE IF NOT EXISTS {$q}anfragen{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}angebot_id{$q} INT NOT NULL,
            {$q}user_id{$q} INT NOT NULL,
            {$q}message{$q} {$dbInfo['text_type']} DEFAULT NULL,
            {$q}status{$q} {$dbInfo['enum_type']} DEFAULT 'pending',
            {$q}confirmed_at{$q} TIMESTAMP NULL DEFAULT NULL,
            {$q}completed_by_helper{$q} TIMESTAMP NULL DEFAULT NULL,
            {$q}completed_by_requester{$q} TIMESTAMP NULL DEFAULT NULL,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']},
            {$q}updated_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']} {$dbInfo['timestamp_update']},
            UNIQUE({$q}angebot_id{$q}, {$q}user_id{$q})
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 9. chat_requests - Chat-Anfragen
        'chat_requests' => "CREATE TABLE IF NOT EXISTS {$q}chat_requests{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}anfrage_id{$q} INT NOT NULL,
            {$q}chat_id{$q} INT NULL,
            {$q}requester_id{$q} INT NOT NULL,
            {$q}helper_id{$q} INT NOT NULL,
            {$q}status{$q} {$dbInfo['enum_type']} DEFAULT 'pending',
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']},
            {$q}updated_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']} {$dbInfo['timestamp_update']},
            UNIQUE({$q}anfrage_id{$q})
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 10. user_activity - User-Aktivität für Online-Status
        'user_activity' => "CREATE TABLE IF NOT EXISTS {$q}user_activity{$q} (
            {$q}user_id{$q} INT NOT NULL PRIMARY KEY,
            {$q}last_activity{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']} {$dbInfo['timestamp_update']}
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 11. chat_favorites - Favorisierte Chat-Kontakte
        'chat_favorites' => "CREATE TABLE IF NOT EXISTS {$q}chat_favorites{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}user_id{$q} INT NOT NULL,
            {$q}contact_user_id{$q} INT NOT NULL,
            {$q}created_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}" .
            ($dbInfo['driver'] === 'mysql' ? ",
            UNIQUE KEY {$q}unique_favorite{$q} ({$q}user_id{$q}, {$q}contact_user_id{$q}),
            INDEX {$q}idx_user_id{$q} ({$q}user_id{$q}),
            INDEX {$q}idx_contact_user_id{$q} ({$q}contact_user_id{$q})" : "") . "
        ) {$dbInfo['engine']} {$dbInfo['charset']}",
        
        // 12. chat_archived - Archivierte Chats
        'chat_archived' => "CREATE TABLE IF NOT EXISTS {$q}chat_archived{$q} (
            {$q}id{$q} {$idColumn}{$primaryKeySuffix},
            {$q}user_id{$q} INT NOT NULL,
            {$q}chat_id{$q} INT NOT NULL,
            {$q}archived_at{$q} TIMESTAMP DEFAULT {$dbInfo['current_timestamp']}" .
            ($dbInfo['driver'] === 'mysql' ? ",
            UNIQUE KEY {$q}unique_user_chat{$q} ({$q}user_id{$q}, {$q}chat_id{$q}),
            INDEX {$q}idx_user_id{$q} ({$q}user_id{$q}),
            INDEX {$q}idx_chat_id{$q} ({$q}chat_id{$q})" : "") . "
        ) {$dbInfo['engine']} {$dbInfo['charset']}"
    ];
    
    // Erstelle alle Tabellen
    $created = [];
    $errors = [];
    
    foreach ($tables as $tableName => $sql) {
        try {
            $conn->exec($sql);
            $created[] = $tableName;
        } catch(PDOException $e) {
            $errors[$tableName] = $e->getMessage();
        }
    }
    
    // Erstelle Indizes
    $indexes = [
        // users - keine zusätzlichen Indizes nötig (UNIQUE auf username/email)
        
        // angebote
        ['angebote', 'idx_angebote_user', "({$q}user_id{$q})"],
        ['angebote', 'idx_angebote_created', "({$q}created_at{$q})"],
        
        // angebote_images
        ['angebote_images', 'idx_angebote_images_angebot', "({$q}angebot_id{$q})"],
        
        // chats
        ['chats', 'idx_chats_updated', "({$q}updated_at{$q})"],
        
        // chat_participants
        ['chat_participants', 'idx_chat_participants_user', "({$q}user_id{$q}, {$q}chat_id{$q})"],
        ['chat_participants', 'idx_chat_participants_chat', "({$q}chat_id{$q}, {$q}user_id{$q})"],
        
        // chat_metadata
        ['chat_metadata', 'idx_chat_metadata_last_message', "({$q}last_message_at{$q})"],
        
        // messages
        ['messages', 'idx_messages_chat', "({$q}chat_id{$q})"],
        ['messages', 'idx_messages_sender_receiver', "({$q}sender_id{$q}, {$q}receiver_id{$q}, {$q}created_at{$q})"],
        ['messages', 'idx_messages_receiver_sender', "({$q}receiver_id{$q}, {$q}sender_id{$q}, {$q}created_at{$q})"],
        ['messages', 'idx_messages_created', "({$q}created_at{$q})"],
        
        // anfragen
        ['anfragen', 'idx_anfragen_status', "({$q}status{$q})"],
        ['anfragen', 'idx_anfragen_angebot_status', "({$q}angebot_id{$q}, {$q}status{$q})"],
        ['anfragen', 'idx_anfragen_user', "({$q}user_id{$q})"],
        
        // chat_requests
        ['chat_requests', 'idx_chat_requests_status', "({$q}status{$q})"],
        ['chat_requests', 'idx_chat_requests_requester', "({$q}requester_id{$q})"],
        ['chat_requests', 'idx_chat_requests_helper', "({$q}helper_id{$q})"],
        
        // user_activity
        ['user_activity', 'idx_user_activity_last_activity', "({$q}last_activity{$q})"],
        
        // chat_favorites
        ['chat_favorites', 'unique_favorite', "({$q}user_id{$q}, {$q}contact_user_id{$q})"],
        ['chat_favorites', 'idx_user_id', "({$q}user_id{$q})"],
        ['chat_favorites', 'idx_contact_user_id', "({$q}contact_user_id{$q})"],
        
        // chat_archived
        ['chat_archived', 'unique_user_chat', "({$q}user_id{$q}, {$q}chat_id{$q})"],
        ['chat_archived', 'idx_user_id', "({$q}user_id{$q})"],
        ['chat_archived', 'idx_chat_id', "({$q}chat_id{$q})"]
    ];
    
    foreach ($indexes as $index) {
        if (!indexExists($conn, $index[0], $index[1])) {
            try {
                // Für UNIQUE-Indexe verwende CREATE UNIQUE INDEX
                if ($index[1] === 'unique_favorite') {
                    $conn->exec("CREATE UNIQUE INDEX {$q}{$index[1]}{$q} ON {$q}{$index[0]}{$q} {$index[2]}");
                } else {
                    $conn->exec("CREATE INDEX {$q}{$index[1]}{$q} ON {$q}{$index[0]}{$q} {$index[2]}");
                }
            } catch(PDOException $e) {
                // Ignoriere Fehler
            }
        }
    }
    
    // Füge Foreign Keys hinzu (nur wenn unterstützt)
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
            ['user_activity', 'user_id', 'users', 'id', 'fk_user_activity_user', 'CASCADE'],
            ['chat_favorites', 'user_id', 'users', 'id', 'fk_chat_favorites_user', 'CASCADE'],
            ['chat_favorites', 'contact_user_id', 'users', 'id', 'fk_chat_favorites_contact', 'CASCADE'],
            ['chat_archived', 'user_id', 'users', 'id', 'fk_chat_archived_user', 'CASCADE'],
            ['chat_archived', 'chat_id', 'chats', 'id', 'fk_chat_archived_chat', 'CASCADE']
        ];
        
        foreach ($foreignKeys as $fk) {
            try {
                // Prüfe ob Foreign Key bereits existiert
                $fkExists = false;
                $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                
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
                } elseif ($driver === 'sqlite') {
                    // SQLite speichert FK-Constraints anders
                    $stmt = $conn->query("PRAGMA foreign_key_list({$fk[0]})");
                    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($fks as $existingFk) {
                        if ($existingFk['table'] === $fk[2] && $existingFk['from'] === $fk[1]) {
                            $fkExists = true;
                            break;
                        }
                    }
                }
                
                if (!$fkExists) {
                    $fkSql = "ALTER TABLE {$q}{$fk[0]}{$q} ADD CONSTRAINT {$q}{$fk[4]}{$q} FOREIGN KEY ({$q}{$fk[1]}{$q}) REFERENCES {$q}{$fk[2]}{$q}({$q}{$fk[3]}{$q}) ON DELETE {$fk[5]}";
                    $conn->exec($fkSql);
                }
            } catch(PDOException $e) {
                // Ignoriere Fehler wenn Foreign Key bereits existiert
            }
        }
    }
    
    // Migrations: Füge fehlende Spalten hinzu
    $migrations = [
        'users' => [
            ['completed_helps', 'INT DEFAULT 0'],
            ['encryption_public_key', 'TEXT NULL'],
        ],
        'angebote' => [
            ['required_persons', 'INT DEFAULT 1'],
        ],
        'messages' => [
            ['chat_id', 'INT NULL'],
            ['encrypted', "{$dbInfo['boolean_type']} DEFAULT 0"],
            ['file_path', 'VARCHAR(500) NULL'],
            ['file_type', 'VARCHAR(20) NULL'],
            ['read_at', 'TIMESTAMP NULL DEFAULT NULL'],
        ],
        'anfragen' => [
            ['status', "{$dbInfo['enum_type']} DEFAULT 'pending'"],
            ['confirmed_at', 'TIMESTAMP NULL DEFAULT NULL'],
            ['completed_by_helper', 'TIMESTAMP NULL DEFAULT NULL'],
            ['completed_by_requester', 'TIMESTAMP NULL DEFAULT NULL'],
            ['updated_at', "TIMESTAMP DEFAULT {$dbInfo['current_timestamp']} {$dbInfo['timestamp_update']}"],
        ],
    ];
    
    foreach ($migrations as $table => $columns) {
        foreach ($columns as $column) {
            if (!columnExists($conn, $table, $column[0])) {
                try {
                    $alterSql = "ALTER TABLE {$q}{$table}{$q} ADD COLUMN {$q}{$column[0]}{$q} {$column[1]}";
                    $conn->exec($alterSql);
                } catch(PDOException $e) {
                    // Ignoriere Fehler
                }
            }
        }
    }
    
    // Upgrade message column to LONGTEXT for MySQL
    if ($dbInfo['driver'] === 'mysql' && columnExists($conn, 'messages', 'message')) {
        try {
            $conn->exec("ALTER TABLE {$q}messages{$q} MODIFY COLUMN {$q}message{$q} LONGTEXT NOT NULL");
        } catch(PDOException $e) {
            // Ignoriere Fehler
        }
    }
    
    return [
        'success' => true,
        'created' => $created,
        'errors' => $errors,
        'driver' => $dbInfo['driver']
    ];
}
} // end if !function_exists('ensureAllTables')

// Wenn direkt aufgerufen
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'universal-schema.php') {
    try {
        $result = ensureAllTables();
        
        if (php_sapi_name() === 'cli') {
            echo "Database schema created successfully!\n";
            echo "Driver: {$result['driver']}\n";
            echo "Tables created: " . implode(', ', $result['created']) . "\n";
            if (!empty($result['errors'])) {
                echo "Errors: " . print_r($result['errors'], true) . "\n";
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode($result);
        }
    } catch(Exception $e) {
        if (php_sapi_name() === 'cli') {
            echo "Error: " . $e->getMessage() . "\n";
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

