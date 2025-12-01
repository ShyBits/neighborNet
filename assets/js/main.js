// Haupt JavaScript Datei
// Hier können später weitere Funktionen hinzugefügt werden

/**
 * Gibt den Base-Pfad zurück basierend auf der aktuellen URL
 * Einfache und zuverlässige Methode, die auf jedem Server funktioniert
 */
function getBasePath() {
    // Einfache Methode: Extrahiere den Pfad bis zum letzten / aus der aktuellen URL
    // Diese Methode funktioniert immer, unabhängig von PHP-Konfiguration
    const path = window.location.pathname;
    const lastSlashIndex = path.lastIndexOf('/');
    
    // Wenn wir im Root sind (z.B. /index.php oder /), gibt es keinen basePath
    if (lastSlashIndex <= 0) {
        return '';
    }
    
    // Gib den Pfad bis zum letzten / zurück (z.B. /subfolder/index.php wird zu /subfolder/)
    // Stelle sicher, dass der Pfad mit / beginnt und endet
    let basePath = path.substring(0, lastSlashIndex + 1);
    
    // Stelle sicher, dass der Pfad mit / beginnt
    if (!basePath.startsWith('/')) {
        basePath = '/' + basePath;
    }
    
    return basePath;
}