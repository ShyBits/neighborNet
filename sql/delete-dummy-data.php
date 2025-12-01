<?php
require_once 'db.php';

$conn = getDBConnection();

try {
    $conn->beginTransaction();
    
    $conn->exec("DELETE FROM anfragen");
    $conn->exec("DELETE FROM messages");
    $conn->exec("DELETE FROM angebote_images");
    $conn->exec("DELETE FROM angebote");
    $conn->exec("DELETE FROM users");
    
    $conn->commit();
    
    echo "Alle Dummy-Daten wurden erfolgreich gelöscht!\n";
    echo "Gelöscht:\n";
    echo "- Alle Benutzer\n";
    echo "- Alle Angebote\n";
    echo "- Alle Bilder\n";
    echo "- Alle Nachrichten\n";
    echo "- Alle Anfragen\n";
    
} catch(PDOException $e) {
    $conn->rollBack();
    echo "Fehler beim Löschen der Daten: " . $e->getMessage() . "\n";
}
?>


