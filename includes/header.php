<?php
// Lade Session-Konfiguration
require_once __DIR__ . '/../config/config.php';

// Bestimme basePath dynamisch basierend auf der aufrufenden Datei
// basePath ist der relative Pfad vom aktuellen Verzeichnis zurück zum Projekt-Root
// 
// Das Projekt-Root ist dort, wo die Hauptdateien (index.php, qa.php, etc.) liegen
$basePath = '';

if (isset($_SERVER['PHP_SELF'])) {
    $scriptPath = $_SERVER['PHP_SELF'];
    $scriptDir = dirname($scriptPath);
    $scriptFile = basename($scriptPath);
    
    // Liste der Dateien, die im Projekt-Root liegen
    $rootFiles = ['index.php', 'qa.php', 'angebote-karte.php', 'flohmarkt.php', 'profile.php', 'finanzen.php'];
    
    // Wenn die aktuelle Datei eine Root-Datei ist, sind wir im Projekt-Root
    if (in_array($scriptFile, $rootFiles)) {
        $basePath = '';
    } else {
        // Wir sind in einem Unterverzeichnis
        // Berechne die Tiefe relativ zum Projekt-Root
        
        // Verwende __DIR__ um den absoluten Pfad zu bekommen
        // header.php ist in includes/, also ist das Projekt-Root ein Level darüber
        $headerDir = __DIR__; // includes/
        $projectRoot = dirname($headerDir); // Projekt-Root
        
        // Hole den absoluten Pfad des aktuellen Scripts
        $currentScript = $_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? '';
        
        if (!empty($currentScript)) {
            // Normalisiere Pfade für Vergleich (verwende immer /)
            $projectRootNorm = str_replace('\\', '/', $projectRoot);
            $currentDir = dirname(str_replace('\\', '/', $currentScript));
            
            // Berechne den relativen Pfad vom aktuellen Verzeichnis zum Projekt-Root
            if (strpos($currentDir, $projectRootNorm) === 0) {
                $relativePath = substr($currentDir, strlen($projectRootNorm));
                $relativePath = trim($relativePath, '/');
                
                if (!empty($relativePath)) {
                    // Zähle die Verzeichnisebenen (verwende / als Separator)
                    $depth = substr_count($relativePath, '/') + 1;
                    $basePath = str_repeat('../', $depth);
                }
            }
        }
        
        // Fallback: Wenn basePath noch leer ist, verwende PHP_SELF
        if (empty($basePath)) {
            $dirPath = trim($scriptDir, '/');
            if (!empty($dirPath)) {
                $depth = substr_count($dirPath, '/') + 1;
                $basePath = str_repeat('../', $depth);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?php echo $basePath; ?>assets/images/logo.png">
    <title>
        <?php
        // Setze den Titel der Seite
        if (isset($pageTitle)) {
            echo $pageTitle . ' - NeighborNet';
        } else {
            echo 'NeighborNet';
        }
        ?>
    </title>
    
    <!-- Google Fonts - Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS Dateien laden -->
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/footer.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>features/navigation/navigation.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>features/navigation/user-actions.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>features/auth/auth-modal.css">
    <?php
    // Lade CSS nur wenn benötigt
    if (isset($pageTitle)) {
        if ($pageTitle === 'Nachbarschaftshilfe' || $pageTitle === 'Home') {
            echo '<link rel="stylesheet" href="' . $basePath . 'features/menu/home/home.css">';
        } elseif ($pageTitle === 'Q&A' || $pageTitle === 'Über uns') {
            echo '<link rel="stylesheet" href="' . $basePath . 'features/menu/ueber-uns/ueber-uns.css">';
        } elseif ($pageTitle === 'Anfragen & Karte' || $pageTitle === 'Angebote & Karte') {
            echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">';
            echo '<link rel="stylesheet" href="' . $basePath . 'features/menu/angebote-karte/angebote-karte.css">';
            echo '<link rel="stylesheet" href="' . $basePath . 'features/menu/angebote-karte/add-modal/add-modal.css">';
        } elseif ($pageTitle === 'Profil') {
            echo '<link rel="stylesheet" href="' . $basePath . 'features/profile/profile.css">';
        } elseif ($pageTitle === 'Anmelden' || $pageTitle === 'Registrieren') {
            echo '<link rel="stylesheet" href="' . $basePath . 'features/auth/auth.css">';
        } elseif ($pageTitle === 'Flohmarkt') {
            echo '<link rel="stylesheet" href="' . $basePath . 'features/menu/flohmarkt/flohmarkt.css">';
        }
        
        // Load chat styles if user is logged in
        if (isset($_SESSION['user_id']) && !isset($_SESSION['is_guest'])) {
            echo '<link rel="stylesheet" href="' . $basePath . 'features/chat/chat-box.css">';
        }
        
        // Load finanzen styles globally
        echo '<link rel="stylesheet" href="' . $basePath . 'features/menu/finanzen/finanzen.css">';
    }
    ?>
    
    <!-- Setze basePath für JavaScript -->
    <script>
        // Setze BASE_PATH für JavaScript, damit getBasePath() funktioniert
        window.BASE_PATH = '<?php echo addslashes($basePath); ?>';
    </script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
        }
    </style>
</head>
<body>
