<?php
// Datenbankinitialisierung
// Diese Datei erstellt alle Tabellen, falls sie nicht existieren

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/create-tables.php';

// Verwende universelles Schema falls verfügbar
if (file_exists(__DIR__ . '/universal-schema.php')) {
    require_once __DIR__ . '/universal-schema.php';
}

initDatabase();

// Verwende ensureAllTables wenn verfügbar, sonst createTables
if (function_exists('ensureAllTables')) {
    $result = ensureAllTables();
    if (php_sapi_name() === 'cli') {
        echo "Datenbank erfolgreich initialisiert!\n";
        echo "Driver: " . ($result['driver'] ?? 'unknown') . "\n";
        echo "Tabellen erstellt: " . count($result['created'] ?? []) . "\n";
    }
} else {
    createTables();
    if (php_sapi_name() === 'cli') {
        echo "Datenbank erfolgreich initialisiert!\n";
    }
}
?>

