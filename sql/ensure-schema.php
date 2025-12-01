<?php
/**
 * Ensure Schema - Stellt sicher, dass alle Tabellen vorhanden sind
 * Kann von überall aufgerufen werden
 * 
 * Verwendung: 
 * - Direkt: php sql/ensure-schema.php
 * - Via Web: http://your-domain/sql/ensure-schema.php
 * - Via Include: require_once 'sql/ensure-schema.php'; ensureDatabaseSchema();
 */

require_once __DIR__ . '/universal-schema.php';

/**
 * Hauptfunktion zum Aufruf von überall
 */
function ensureDatabaseSchema() {
    try {
        $result = ensureAllTables();
        return $result;
    } catch(Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Wenn direkt aufgerufen
if (basename($_SERVER['PHP_SELF']) === 'ensure-schema.php') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $result = ensureDatabaseSchema();
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

